<?php
/**
 * Creating, editing and removing roles, safely.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Roles;

/**
 * The role editor's rules.
 *
 * Every method here refuses more than it accepts, and each refusal exists
 * because of a specific way a role editor can ruin a site:
 *
 * - **Nobody edits the administrator role.** It is the way back in.
 * - **Nobody deletes a role OxyArea did not create.** Sites acquire roles from
 *   themes, shops and plugins installed years ago; deleting one is destroying
 *   something that belongs to somebody else.
 * - **Nobody grants a capability they do not hold.** Otherwise an editor who has
 *   been given the role screen can mint a role with install_plugins, assign it
 *   to themselves, and own the site by lunchtime.
 * - **Nobody edits themselves out of the role screen.** The one action with no
 *   undo is the one that removes your ability to undo it.
 * - **Capabilities outside the catalogue are left alone.** A role carrying
 *   WooCommerce's capabilities must come out of OxyArea's editor still carrying
 *   them; an editor that saves only what it displays is an editor that quietly
 *   deletes what it does not understand.
 */
final class RoleManager {

	/**
	 * Roles that may never be edited or deleted through this plugin.
	 */
	private const PROTECTED_ROLES = array( 'administrator' );

	/**
	 * The capability somebody needs to keep, or they cannot come back.
	 */
	private const KEYSTONE_CAPABILITY = Capabilities::MANAGE_ROLES;

	/**
	 * Which roles this plugin created.
	 *
	 * @var ManagedRoles
	 */
	private ManagedRoles $managed;

	/**
	 * Build the manager.
	 *
	 * @param ManagedRoles $managed Which roles this plugin created.
	 */
	public function __construct( ManagedRoles $managed ) {
		$this->managed = $managed;
	}

	/**
	 * Create a role.
	 *
	 * @param string       $display_name   Human-readable name.
	 * @param string       $slug           Desired slug; derived from the name when empty.
	 * @param list<string> $capabilities   Capabilities to grant.
	 * @param int          $acting_user_id The administrator doing this.
	 * @return string The slug that was created.
	 *
	 * @throws RoleException If the name or slug is unusable, or the slug is taken.
	 */
	public function create( string $display_name, string $slug, array $capabilities, int $acting_user_id ): string {
		$display_name = trim( wp_strip_all_tags( $display_name ) );

		if ( '' === $display_name ) {
			throw new RoleException( esc_html__( 'A role needs a name.', 'oxyarea' ) );
		}

		$slug = $this->normalise_slug( '' !== trim( $slug ) ? $slug : $display_name );

		if ( null !== get_role( $slug ) ) {
			throw new RoleException(
				sprintf(
					/* translators: %s: role slug. */
					esc_html__( 'The role "%s" already exists.', 'oxyarea' ),
					esc_html( $slug )
				)
			);
		}

		$granted = $this->grantable( $capabilities, $acting_user_id );
		$map     = array();

		foreach ( $granted as $capability ) {
			$map[ $capability ] = true;
		}

		// Every role needs "read" or its holders cannot reach the admin bar, let
		// alone a private area.
		$map['read'] = true;

		add_role( $slug, $display_name, $map );

		$this->managed->add( $slug );

		/**
		 * Fires after OxyArea creates a role.
		 *
		 * @since 0.1.0
		 *
		 * @param string       $slug         The role slug.
		 * @param string       $display_name The role name.
		 * @param list<string> $granted      The capabilities actually granted.
		 */
		do_action( 'oxyarea_role_created', $slug, $display_name, $granted );

		return $slug;
	}

	/**
	 * Copy an existing role under a new name.
	 *
	 * The copy carries every capability of the original that the person doing the
	 * copying already holds. Cloning is not a way around the escalation guard.
	 *
	 * @param string $source_slug    The role to copy.
	 * @param string $display_name   Name of the copy.
	 * @param string $slug           Desired slug of the copy.
	 * @param int    $acting_user_id The administrator doing this.
	 * @return string The slug that was created.
	 *
	 * @throws RoleException If the source does not exist.
	 */
	public function clone_role( string $source_slug, string $display_name, string $slug, int $acting_user_id ): string {
		$source = get_role( $source_slug );

		if ( null === $source ) {
			throw new RoleException(
				sprintf(
					/* translators: %s: role slug. */
					esc_html__( 'There is no role called "%s" to copy.', 'oxyarea' ),
					esc_html( $source_slug )
				)
			);
		}

		$capabilities = array();

		foreach ( (array) $source->capabilities as $capability => $granted ) {
			if ( $granted ) {
				$capabilities[] = (string) $capability;
			}
		}

		return $this->create( $display_name, $slug, $capabilities, $acting_user_id );
	}

	/**
	 * Change what a role may do.
	 *
	 * Only capabilities the catalogue offers are touched. Anything else the role
	 * holds is left exactly as it was.
	 *
	 * @param string       $slug           The role to edit.
	 * @param list<string> $granted        The capabilities that should be granted.
	 * @param int          $acting_user_id The administrator doing this.
	 * @return void
	 *
	 * @throws RoleException If the role is protected, missing, or the change would lock the actor out.
	 */
	public function update_capabilities( string $slug, array $granted, int $acting_user_id ): void {
		$role = $this->editable_role( $slug );

		$granted = $this->grantable( $granted, $acting_user_id );

		if ( $this->would_lock_out( $acting_user_id, $slug, $granted ) ) {
			throw new RoleException(
				esc_html__( 'That change would remove your own access to the role editor, so it has not been saved.', 'oxyarea' )
			);
		}

		foreach ( CapabilityCatalogue::offered() as $capability ) {
			$should_have = in_array( $capability, $granted, true );
			$has         = ! empty( $role->capabilities[ $capability ] );

			if ( $should_have && ! $has ) {
				$role->add_cap( $capability );
			}

			if ( ! $should_have && $has ) {
				// Never take a capability the actor could not have granted in the
				// first place: that would turn the escalation guard into a way of
				// stripping rights the actor cannot see.
				if ( user_can( $acting_user_id, $capability ) ) {
					$role->remove_cap( $capability );
				}
			}
		}

		/**
		 * Fires after OxyArea changes a role's capabilities.
		 *
		 * @since 0.1.0
		 *
		 * @param string       $slug    The role slug.
		 * @param list<string> $granted The capabilities that should now be granted.
		 */
		do_action( 'oxyarea_role_updated', $slug, $granted );
	}

	/**
	 * Delete a role OxyArea created, moving its holders elsewhere.
	 *
	 * @param string $slug           The role to delete.
	 * @param string $reassign_to    The role its holders should get instead.
	 * @param int    $acting_user_id The administrator doing this.
	 * @return int How many users were moved.
	 *
	 * @throws RoleException If the role is not OxyArea's to delete, or the destination is unusable.
	 */
	public function delete( string $slug, string $reassign_to, int $acting_user_id ): int {
		$this->editable_role( $slug );

		if ( ! $this->managed->contains( $slug ) ) {
			throw new RoleException(
				sprintf(
					/* translators: %s: role slug. */
					esc_html__( 'The role "%s" was not created by OxyArea, so OxyArea will not delete it.', 'oxyarea' ),
					esc_html( $slug )
				)
			);
		}

		if ( null === get_role( $reassign_to ) ) {
			throw new RoleException(
				esc_html__( 'Choose an existing role for the people who hold this one.', 'oxyarea' )
			);
		}

		if ( $reassign_to === $slug ) {
			throw new RoleException(
				esc_html__( 'The people holding this role need a different role to move to.', 'oxyarea' )
			);
		}

		if ( $this->would_lock_out( $acting_user_id, $slug, array() ) ) {
			throw new RoleException(
				esc_html__( 'That would remove your own access to the role editor, so the role has not been deleted.', 'oxyarea' )
			);
		}

		$holders = get_users(
			array(
				'role'   => $slug,
				'fields' => 'ID',
				'number' => 0,
			)
		);

		$moved = 0;

		foreach ( (array) $holders as $holder_id ) {
			$user = get_userdata( (int) $holder_id );

			if ( false === $user ) {
				continue;
			}

			$user->remove_role( $slug );

			// Somebody whose only role was this one would otherwise be left with
			// none, which in WordPress means an account that can do nothing at all.
			if ( array() === array_values( (array) $user->roles ) ) {
				$user->add_role( $reassign_to );
			}

			++$moved;
		}

		remove_role( $slug );

		$this->managed->remove( $slug );

		/**
		 * Fires after OxyArea deletes a role.
		 *
		 * @since 0.1.0
		 *
		 * @param string $slug        The role slug.
		 * @param string $reassign_to Where its holders went.
		 * @param int    $moved       How many users were moved.
		 */
		do_action( 'oxyarea_role_deleted', $slug, $reassign_to, $moved );

		return $moved;
	}

	/**
	 * Give a user a role, replacing the ones they have.
	 *
	 * @param int    $user_id        The user.
	 * @param string $slug           The role they should hold.
	 * @param int    $acting_user_id The administrator doing this.
	 * @return void
	 *
	 * @throws RoleException If the user or role is missing, or this would strand the site without an administrator.
	 */
	public function assign_user( int $user_id, string $slug, int $acting_user_id ): void {
		$user = get_userdata( $user_id );

		if ( false === $user ) {
			throw new RoleException( esc_html__( 'That user no longer exists.', 'oxyarea' ) );
		}

		if ( null === get_role( $slug ) ) {
			throw new RoleException( esc_html__( 'That role no longer exists.', 'oxyarea' ) );
		}

		if ( 'administrator' === $slug && ! user_can( $acting_user_id, 'promote_users' ) ) {
			throw new RoleException(
				esc_html__( 'Making somebody an administrator needs the ability to promote users.', 'oxyarea' )
			);
		}

		if ( 'administrator' !== $slug && $this->is_last_administrator( $user_id ) ) {
			throw new RoleException(
				esc_html__( 'This is the only administrator on the site. Give somebody else that role first.', 'oxyarea' )
			);
		}

		$user->set_role( $slug );

		/**
		 * Fires after OxyArea changes a user's role.
		 *
		 * @since 0.1.0
		 *
		 * @param int    $user_id The user.
		 * @param string $slug    The role they now hold.
		 */
		do_action( 'oxyarea_user_role_assigned', $user_id, $slug );
	}

	/**
	 * Whether a role may be edited through this plugin at all.
	 *
	 * @param string $slug Role slug.
	 * @return \WP_Role
	 *
	 * @throws RoleException If the role is protected or does not exist.
	 */
	private function editable_role( string $slug ) {
		if ( in_array( $slug, self::PROTECTED_ROLES, true ) ) {
			throw new RoleException(
				esc_html__( 'The administrator role is the way back into a site and OxyArea will not change it.', 'oxyarea' )
			);
		}

		$role = get_role( $slug );

		if ( null === $role ) {
			throw new RoleException(
				sprintf(
					/* translators: %s: role slug. */
					esc_html__( 'There is no role called "%s".', 'oxyarea' ),
					esc_html( $slug )
				)
			);
		}

		return $role;
	}

	/**
	 * Narrow a wish list of capabilities to the ones this actor may actually give.
	 *
	 * @param list<string> $capabilities   The requested capabilities.
	 * @param int          $acting_user_id The administrator doing this.
	 * @return list<string>
	 */
	private function grantable( array $capabilities, int $acting_user_id ): array {
		$offered   = CapabilityCatalogue::offered();
		$grantable = array();

		foreach ( $capabilities as $capability ) {
			if ( ! is_string( $capability ) || ! in_array( $capability, $offered, true ) ) {
				continue;
			}

			if ( ! user_can( $acting_user_id, $capability ) ) {
				continue;
			}

			$grantable[] = $capability;
		}

		return array_values( array_unique( $grantable ) );
	}

	/**
	 * Whether a change would cost the actor their own way back to this screen.
	 *
	 * @param int          $acting_user_id The administrator doing this.
	 * @param string       $slug           The role being changed.
	 * @param list<string> $new_capabilities What the role would grant afterwards.
	 * @return bool
	 */
	private function would_lock_out( int $acting_user_id, string $slug, array $new_capabilities ): bool {
		$user = get_userdata( $acting_user_id );

		if ( false === $user || ! in_array( $slug, (array) $user->roles, true ) ) {
			return false;
		}

		foreach ( (array) $user->roles as $held ) {
			if ( $held === $slug ) {
				continue;
			}

			$other = get_role( (string) $held );

			if ( null !== $other && ! empty( $other->capabilities[ self::KEYSTONE_CAPABILITY ] ) ) {
				return false;
			}
		}

		return ! in_array( self::KEYSTONE_CAPABILITY, $new_capabilities, true );
	}

	/**
	 * Whether this user is the only administrator left.
	 *
	 * @param int $user_id The user.
	 * @return bool
	 */
	private function is_last_administrator( int $user_id ): bool {
		$user = get_userdata( $user_id );

		if ( false === $user || ! in_array( 'administrator', (array) $user->roles, true ) ) {
			return false;
		}

		$administrators = get_users(
			array(
				'role'   => 'administrator',
				'fields' => 'ID',
				'number' => 2,
			)
		);

		return count( (array) $administrators ) <= 1;
	}

	/**
	 * Turn a name or slug into a usable role key.
	 *
	 * @param string $value Name or slug.
	 * @return string
	 *
	 * @throws RoleException If nothing usable is left.
	 */
	private function normalise_slug( string $value ): string {
		$slug = sanitize_key( $value );
		$slug = substr( $slug, 0, 60 );

		if ( '' === $slug ) {
			throw new RoleException(
				esc_html__( 'That name leaves nothing usable as a role identifier. Use letters or numbers.', 'oxyarea' )
			);
		}

		return $slug;
	}
}
