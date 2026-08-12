<?php
/**
 * The role editor.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Admin;

use OxyArea\Infrastructure\Brand;
use OxyArea\Infrastructure\Registrable;
use OxyArea\Roles\Capabilities;
use OxyArea\Roles\CapabilityCatalogue;
use OxyArea\Roles\ManagedRoles;
use OxyArea\Roles\RoleException;
use OxyArea\Roles\RoleManager;

/**
 * Lists roles, creates them, edits their capabilities and deletes them.
 *
 * Form handling goes through admin-post.php rather than the render callback: a
 * screen that mutates on GET is a screen that mutates when somebody's browser
 * prefetches a link.
 *
 * Every handler does the same three things before touching anything — verify the
 * nonce, check the capability, then ask the role manager, which refuses what it
 * must. The nonce is not the authorisation; it only proves the request came from
 * this screen.
 */
final class RolesScreen implements Registrable {

	/**
	 * The role manager.
	 *
	 * @var RoleManager
	 */
	private RoleManager $roles;

	/**
	 * Which roles OxyArea created.
	 *
	 * @var ManagedRoles
	 */
	private ManagedRoles $managed;

	/**
	 * Build the screen.
	 *
	 * @param RoleManager  $roles   The role manager.
	 * @param ManagedRoles $managed Which roles OxyArea created.
	 */
	public function __construct( RoleManager $roles, ManagedRoles $managed ) {
		$this->roles   = $roles;
		$this->managed = $managed;
	}

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_oxyarea_create_role', array( $this, 'handle_create' ) );
		add_action( 'admin_post_oxyarea_update_role', array( $this, 'handle_update' ) );
		add_action( 'admin_post_oxyarea_delete_role', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_oxyarea_assign_user', array( $this, 'handle_assign' ) );
	}

	/**
	 * Render whichever view was asked for.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_ROLES ) ) {
			wp_die( esc_html__( 'You are not allowed to edit roles.', 'oxyarea' ), '', array( 'response' => 403 ) );
		}

		// Reading which role to display. No nonce, because there is nothing to
		// protect: this changes nothing, and the capability check above has
		// already decided whether the reader may be here at all.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$editing = isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : '';

		echo '<div class="wrap">';

		$this->notice();

		if ( '' !== $editing && null !== get_role( $editing ) ) {
			$this->render_editor( $editing );
		} else {
			$this->render_list();
		}

		echo '</div>';
	}

	/**
	 * Create a role.
	 *
	 * @return void
	 */
	public function handle_create(): void {
		check_admin_referer( 'oxyarea_create_role' );
		$this->require_capability();

		try {
			$slug = $this->roles->create(
				isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '',
				isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '',
				array(),
				get_current_user_id()
			);

			$this->remember_notice(
				'success',
				sprintf(
					/* translators: %s: role slug. */
					esc_html__( 'The role "%s" has been created. Choose what it may do below.', 'oxyarea' ),
					esc_html( $slug )
				)
			);

			$this->go_back( $slug );
		} catch ( RoleException $e ) {
			$this->remember_notice( 'error', $e->getMessage() );
			$this->go_back();
		}
	}

	/**
	 * Save a role's capabilities.
	 *
	 * @return void
	 */
	public function handle_update(): void {
		check_admin_referer( 'oxyarea_update_role' );
		$this->require_capability();

		$slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';

		// array_values, because a posted array can carry any keys it likes and
		// what the role manager wants is a plain list.
		$granted = isset( $_POST['capabilities'] )
			? array_values( array_map( 'sanitize_key', (array) wp_unslash( $_POST['capabilities'] ) ) )
			: array();

		try {
			$this->roles->update_capabilities( $slug, $granted, get_current_user_id() );

			$this->remember_notice( 'success', esc_html__( 'Saved.', 'oxyarea' ) );
			$this->go_back( $slug );
		} catch ( RoleException $e ) {
			$this->remember_notice( 'error', $e->getMessage() );
			$this->go_back( $slug );
		}
	}

	/**
	 * Delete a role.
	 *
	 * @return void
	 */
	public function handle_delete(): void {
		check_admin_referer( 'oxyarea_delete_role' );
		$this->require_capability();

		$slug        = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
		$reassign_to = isset( $_POST['reassign_to'] ) ? sanitize_key( wp_unslash( $_POST['reassign_to'] ) ) : '';

		try {
			$moved = $this->roles->delete( $slug, $reassign_to, get_current_user_id() );

			$this->remember_notice(
				'success',
				sprintf(
					esc_html(
						/* translators: 1: role slug, 2: number of users moved, 3: destination role slug. */
						_n(
							'The role "%1$s" has been deleted. %2$d person now holds "%3$s" instead.',
							'The role "%1$s" has been deleted. %2$d people now hold "%3$s" instead.',
							$moved,
							'oxyarea'
						)
					),
					esc_html( $slug ),
					$moved,
					esc_html( $reassign_to )
				)
			);
		} catch ( RoleException $e ) {
			$this->remember_notice( 'error', $e->getMessage() );
		}

		$this->go_back();
	}

	/**
	 * Give a user a role.
	 *
	 * @return void
	 */
	public function handle_assign(): void {
		check_admin_referer( 'oxyarea_assign_user' );
		$this->require_capability();

		$login = isset( $_POST['user'] ) ? sanitize_text_field( wp_unslash( $_POST['user'] ) ) : '';
		$slug  = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';

		$user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );

		if ( false === $user ) {
			$this->remember_notice(
				'error',
				sprintf(
					/* translators: %s: what was typed in the user box. */
					esc_html__( 'No user found for "%s".', 'oxyarea' ),
					esc_html( $login )
				)
			);

			$this->go_back();
		}

		try {
			$this->roles->assign_user( (int) $user->ID, $slug, get_current_user_id() );

			$this->remember_notice(
				'success',
				sprintf(
					/* translators: 1: user login, 2: role slug. */
					esc_html__( '%1$s now holds the role "%2$s".', 'oxyarea' ),
					esc_html( $user->user_login ),
					esc_html( $slug )
				)
			);
		} catch ( RoleException $e ) {
			$this->remember_notice( 'error', $e->getMessage() );
		}

		$this->go_back();
	}

	/**
	 * The list of roles, with the forms that act on them.
	 *
	 * @return void
	 */
	private function render_list(): void {
		$counts = count_users();
		$counts = isset( $counts['avail_roles'] ) && is_array( $counts['avail_roles'] ) ? $counts['avail_roles'] : array();

		echo '<h1>' . esc_html__( 'Roles', 'oxyarea' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'A role says what kind of user somebody is. It is not the same as which company they belong to: one role serves every customer, however many companies there are.', 'oxyarea' ) . '</p>';

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Role', 'oxyarea' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Identifier', 'oxyarea' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'People', 'oxyarea' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Created by', 'oxyarea' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $this->all_roles() as $slug => $name ) {
			$is_ours     = $this->managed->contains( $slug );
			$is_editable = 'administrator' !== $slug;
			$edit_url    = add_query_arg(
				array(
					'page' => Brand::MENU_SLUG,
					'role' => $slug,
				),
				admin_url( 'admin.php' )
			);

			echo '<tr>';
			echo '<td><strong>';

			if ( $is_editable ) {
				echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $name ) . '</a>';
			} else {
				echo esc_html( $name );
			}

			echo '</strong></td>';
			echo '<td><code>' . esc_html( $slug ) . '</code></td>';
			echo '<td>' . esc_html( (string) ( $counts[ $slug ] ?? 0 ) ) . '</td>';
			echo '<td>' . esc_html( $is_ours ? Brand::name() : __( 'WordPress or another plugin', 'oxyarea' ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		$this->render_create_form();
		$this->render_assign_form();
		$this->render_delete_form();
	}

	/**
	 * The form that creates a role.
	 *
	 * @return void
	 */
	private function render_create_form(): void {
		echo '<h2>' . esc_html__( 'Add a role', 'oxyarea' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'oxyarea_create_role' );
		echo '<input type="hidden" name="action" value="oxyarea_create_role" />';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="oxyarea-role-name">' . esc_html__( 'Name', 'oxyarea' ) . '</label></th>';
		echo '<td><input name="display_name" id="oxyarea-role-name" type="text" class="regular-text" required /></td></tr>';

		echo '<tr><th scope="row"><label for="oxyarea-role-slug">' . esc_html__( 'Identifier', 'oxyarea' ) . '</label></th>';
		echo '<td><input name="slug" id="oxyarea-role-slug" type="text" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Optional. Derived from the name when left empty, and permanent once the role exists.', 'oxyarea' ) . '</p></td></tr>';

		echo '</tbody></table>';
		submit_button( __( 'Add role', 'oxyarea' ) );
		echo '</form>';
	}

	/**
	 * The form that gives somebody a role.
	 *
	 * @return void
	 */
	private function render_assign_form(): void {
		echo '<h2>' . esc_html__( 'Give somebody a role', 'oxyarea' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'oxyarea_assign_user' );
		echo '<input type="hidden" name="action" value="oxyarea_assign_user" />';
		echo '<table class="form-table" role="presentation"><tbody><tr>';
		echo '<th scope="row"><label for="oxyarea-assign-user">' . esc_html__( 'Username or email', 'oxyarea' ) . '</label></th>';
		echo '<td><input name="user" id="oxyarea-assign-user" type="text" class="regular-text" required /> ';
		$this->render_role_select( 'slug', 'oxyarea-assign-role' );
		echo '<p class="description">' . esc_html__( 'This replaces the roles the person currently holds.', 'oxyarea' ) . '</p>';
		echo '</td></tr></tbody></table>';
		submit_button( __( 'Assign role', 'oxyarea' ), 'secondary' );
		echo '</form>';
	}

	/**
	 * The form that deletes a role OxyArea created.
	 *
	 * @return void
	 */
	private function render_delete_form(): void {
		$ours = $this->managed->all();

		if ( array() === $ours ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Delete a role', 'oxyarea' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Only roles OxyArea created can be deleted here. Everyone holding the role keeps their account and receives the role you choose instead.', 'oxyarea' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Delete this role? The people who hold it keep their accounts.', 'oxyarea' ) ) . '\');">';
		wp_nonce_field( 'oxyarea_delete_role' );
		echo '<input type="hidden" name="action" value="oxyarea_delete_role" />';

		echo '<select name="slug">';

		foreach ( $ours as $slug ) {
			$role = get_role( $slug );

			if ( null === $role ) {
				continue;
			}

			echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $this->role_name( $slug ) ) . '</option>';
		}

		echo '</select> ';
		echo '<span>' . esc_html__( 'and move its people to', 'oxyarea' ) . '</span> ';
		$this->render_role_select( 'reassign_to', 'oxyarea-reassign-role' );
		submit_button( __( 'Delete role', 'oxyarea' ), 'delete' );
		echo '</form>';
	}

	/**
	 * The capability editor for one role.
	 *
	 * @param string $slug Role slug.
	 * @return void
	 */
	private function render_editor( string $slug ): void {
		$role = get_role( $slug );

		if ( null === $role || 'administrator' === $slug ) {
			echo '<h1>' . esc_html__( 'Roles', 'oxyarea' ) . '</h1>';
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The administrator role is the way back into a site and OxyArea will not change it.', 'oxyarea' ) . '</p></div>';

			return;
		}

		echo '<h1>';
		printf(
			/* translators: %s: role name. */
			esc_html__( 'What %s may do', 'oxyarea' ),
			'<em>' . esc_html( $this->role_name( $slug ) ) . '</em>'
		);
		echo '</h1>';

		echo '<p><a href="' . esc_url( add_query_arg( 'page', Brand::MENU_SLUG, admin_url( 'admin.php' ) ) ) . '">&larr; ' . esc_html__( 'All roles', 'oxyarea' ) . '</a></p>';
		echo '<p class="description">' . esc_html__( 'Capabilities this role holds from other plugins are not shown and are not touched by saving.', 'oxyarea' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'oxyarea_update_role' );
		echo '<input type="hidden" name="action" value="oxyarea_update_role" />';
		echo '<input type="hidden" name="slug" value="' . esc_attr( $slug ) . '" />';

		foreach ( CapabilityCatalogue::groups() as $group => $capabilities ) {
			$dangerous = CapabilityCatalogue::GROUP_DANGEROUS === $group;

			echo '<h2>' . esc_html( $this->group_label( $group ) ) . '</h2>';

			if ( $dangerous ) {
				echo '<p class="description">' . esc_html__( 'Each of these lets somebody take over the site, directly or in one more step. You can only grant what you hold yourself.', 'oxyarea' ) . '</p>';
			}

			echo '<fieldset>';

			foreach ( $capabilities as $capability ) {
				$held      = ! empty( $role->capabilities[ $capability ] );
				$grantable = current_user_can( $capability );
				$id        = 'oxyarea-cap-' . $capability;

				echo '<p><label for="' . esc_attr( $id ) . '">';
				echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="capabilities[]" value="' . esc_attr( $capability ) . '"';
				checked( $held );
				disabled( ! $grantable );
				echo ' /> <code>' . esc_html( $capability ) . '</code>';

				if ( ! $grantable ) {
					echo ' <span class="description">' . esc_html__( '(you do not hold this yourself)', 'oxyarea' ) . '</span>';
				}

				// A checkbox that is disabled submits nothing, which would read as
				// "remove it". This carries the current state through untouched.
				if ( ! $grantable && $held ) {
					echo '<input type="hidden" name="capabilities[]" value="' . esc_attr( $capability ) . '" />';
				}

				echo '</label></p>';
			}

			echo '</fieldset>';
		}

		submit_button( __( 'Save capabilities', 'oxyarea' ) );
		echo '</form>';
	}

	/**
	 * A select of every role on the site.
	 *
	 * @param string $name Field name.
	 * @param string $id   Field id.
	 * @return void
	 */
	private function render_role_select( string $name, string $id ): void {
		echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '">';

		foreach ( $this->all_roles() as $slug => $label ) {
			echo '<option value="' . esc_attr( (string) $slug ) . '">' . esc_html( $label ) . '</option>';
		}

		echo '</select>';
	}

	/**
	 * Every role on the site, slug to display name.
	 *
	 * @return array<string, string>
	 */
	private function all_roles(): array {
		$roles = wp_roles()->get_names();
		$names = array();

		foreach ( (array) $roles as $slug => $name ) {
			$names[ (string) $slug ] = translate_user_role( (string) $name );
		}

		return $names;
	}

	/**
	 * A role's display name.
	 *
	 * @param string $slug Role slug.
	 * @return string
	 */
	private function role_name( string $slug ): string {
		$names = $this->all_roles();

		return $names[ $slug ] ?? $slug;
	}

	/**
	 * The heading for a capability group.
	 *
	 * @param string $group Group key.
	 * @return string
	 */
	private function group_label( string $group ): string {
		switch ( $group ) {
			case CapabilityCatalogue::GROUP_READING:
				return __( 'Reading', 'oxyarea' );
			case CapabilityCatalogue::GROUP_POSTS:
				return __( 'Posts', 'oxyarea' );
			case CapabilityCatalogue::GROUP_PAGES:
				return __( 'Pages', 'oxyarea' );
			case CapabilityCatalogue::GROUP_SITE:
				return __( 'Media, comments and categories', 'oxyarea' );
			case CapabilityCatalogue::GROUP_OXYAREA:
				return __( 'Administering the private area', 'oxyarea' );
			case CapabilityCatalogue::GROUP_DANGEROUS:
				return __( 'Handing over the site', 'oxyarea' );
			default:
				return $group;
		}
	}

	/**
	 * Refuse anybody who may not edit roles.
	 *
	 * Called by every handler *after* its nonce check. The nonce proves the
	 * request came from this screen; this proves the person is allowed to make
	 * it. Neither substitutes for the other, and the role manager still refuses
	 * what it must after both have passed.
	 *
	 * @return void
	 */
	private function require_capability(): void {
		if ( ! current_user_can( Capabilities::MANAGE_ROLES ) ) {
			wp_die( esc_html__( 'You are not allowed to edit roles.', 'oxyarea' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Keep a message for the screen the browser is about to land on.
	 *
	 * Held for the user rather than passed through the URL: a message in a query
	 * string is a message an attacker can choose.
	 *
	 * The message must arrive **already escaped for HTML**, because that is how
	 * the role manager's messages arrive and mixing the two conventions is how a
	 * screen ends up either double-escaping or printing something raw that it
	 * should not have.
	 *
	 * @param string $type    'success' or 'error'.
	 * @param string $message What to say, escaped.
	 * @return void
	 */
	private function remember_notice( string $type, string $message ): void {
		set_transient(
			'oxyarea_notice_' . get_current_user_id(),
			array(
				'type'    => 'error' === $type ? 'error' : 'success',
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Show and forget the held message.
	 *
	 * @return void
	 */
	private function notice(): void {
		$key    = 'oxyarea_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) || ! isset( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );

		// The message arrives already escaped: every caller of remember_notice()
		// passes esc_html__() text with esc_html() values interpolated into it,
		// and the role manager throws the same way. Escaping it a second time
		// here is what would put &quot; on somebody's screen where they wrote a
		// quotation mark.
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			'error' === ( $notice['type'] ?? '' ) ? 'error' : 'success',
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by every caller; see above.
			(string) $notice['message']
		);
	}

	/**
	 * Send the browser back to the screen.
	 *
	 * @param string $role Role to open, if any.
	 * @return never
	 */
	private function go_back( string $role = '' ) {
		$args = array( 'page' => Brand::MENU_SLUG );

		if ( '' !== $role ) {
			$args['role'] = $role;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );

		exit;
	}
}
