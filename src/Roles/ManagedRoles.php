<?php
/**
 * Which roles OxyArea made.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Roles;

/**
 * Remembers the roles this plugin created.
 *
 * The distinction decides what may be deleted. A role OxyArea created is
 * OxyArea's to remove; "editor" is not, whatever the screen lets somebody click.
 * Sites acquire roles from themes, from WooCommerce, from a membership plugin
 * installed two years ago, and a private-area plugin that deletes one of those
 * has destroyed something it did not own.
 */
final class ManagedRoles {

	/**
	 * The option holding the list.
	 */
	public const OPTION = 'oxyarea_managed_roles';

	/**
	 * Every role OxyArea created on this site.
	 *
	 * @return list<string>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		return array_values(
			array_unique(
				array_filter(
					array_map( 'strval', $stored ),
					static fn ( string $slug ): bool => '' !== $slug
				)
			)
		);
	}

	/**
	 * Whether OxyArea created this role.
	 *
	 * @param string $slug Role slug.
	 * @return bool
	 */
	public function contains( string $slug ): bool {
		return in_array( $slug, $this->all(), true );
	}

	/**
	 * Record that OxyArea created a role.
	 *
	 * @param string $slug Role slug.
	 * @return void
	 */
	public function add( string $slug ): void {
		$roles = $this->all();

		if ( in_array( $slug, $roles, true ) ) {
			return;
		}

		$roles[] = $slug;

		update_option( self::OPTION, $roles, false );
	}

	/**
	 * Forget a role.
	 *
	 * @param string $slug Role slug.
	 * @return void
	 */
	public function remove( string $slug ): void {
		$roles = array_values(
			array_filter(
				$this->all(),
				static fn ( string $stored ): bool => $stored !== $slug
			)
		);

		update_option( self::OPTION, $roles, false );
	}
}
