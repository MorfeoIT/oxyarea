<?php
/**
 * The first five minutes.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Admin;

use OxyArea\Access\Subject;
use OxyArea\Dashboard\DashboardPostType;
use OxyArea\Infrastructure\Registrable;
use OxyArea\Infrastructure\Settings;
use OxyArea\Redirect\RedirectEvent;
use OxyArea\Redirect\RedirectRule;
use OxyArea\Redirect\RuleRepositoryInterface;
use OxyArea\Roles\Capabilities;
use OxyArea\Roles\RoleException;
use OxyArea\Roles\RoleManager;

/**
 * Builds a working private area from one form.
 *
 * The specification describes this as seven steps. It is one screen, and the
 * simplification is deliberate: seven steps means six chances to abandon it
 * halfway and a plugin that has to reason about being half configured. Every
 * field has a sensible default, so the honest version of "next, next, next" is
 * a single button.
 *
 * What it makes is the thing the plugin's definition of done describes: a role,
 * a sign-in page, a dashboard for that role, and a rule sending them to it. Then
 * it says what to check, because a wizard that reports success without anybody
 * having signed in has reported nothing.
 */
final class Wizard implements Registrable {

	/**
	 * The page slug.
	 */
	public const SLUG = 'oxyarea-setup';

	/**
	 * The option recording that somebody has been through this.
	 */
	private const DONE_OPTION = 'oxyarea_setup_done';

	/**
	 * The role manager.
	 *
	 * @var RoleManager
	 */
	private RoleManager $roles;

	/**
	 * The redirect rules.
	 *
	 * @var RuleRepositoryInterface
	 */
	private RuleRepositoryInterface $redirects;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Build the wizard.
	 *
	 * @param RoleManager             $roles     The role manager.
	 * @param RuleRepositoryInterface $redirects The redirect rules.
	 * @param Settings                $settings  The settings.
	 */
	public function __construct( RoleManager $roles, RuleRepositoryInterface $redirects, Settings $settings ) {
		$this->roles     = $roles;
		$this->redirects = $redirects;
		$this->settings  = $settings;
	}

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_oxyarea_setup', array( $this, 'handle' ) );
		add_action( 'admin_notices', array( $this, 'invitation' ) );
	}

	/**
	 * Point a new installation at the wizard, once.
	 *
	 * One notice, on OxyArea's own screens and the plugins list, and it goes away
	 * for good the moment the wizard is used. A plugin that keeps asking is a
	 * plugin somebody learns to ignore.
	 *
	 * @return void
	 */
	public function invitation(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) || get_option( self::DONE_OPTION ) ) {
			return;
		}

		$screen = get_current_screen();
		$id     = null === $screen ? '' : (string) $screen->id;

		if ( 'plugins' !== $id && false === strpos( $id, 'oxyarea' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'OxyArea can build you a working private area — a role, a sign-in page, a dashboard and the rule that connects them.', 'oxyarea' ),
			esc_url( add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) ) ),
			esc_html__( 'Set it up', 'oxyarea' )
		);
	}

	/**
	 * Draw the screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'oxyarea' ), '', array( 'response' => 403 ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Set up a private area', 'oxyarea' ) . '</h1>';

		Notices::show();

		if ( get_option( self::DONE_OPTION ) ) {
			$this->render_checklist();
		}

		echo '<p class="description">' . esc_html__( 'Everything here can be changed afterwards, and nothing is overwritten: if a role or a page already exists, it is used rather than replaced.', 'oxyarea' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'oxyarea_setup' );
		echo '<input type="hidden" name="action" value="oxyarea_setup" />';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="oxyarea-setup-role">' . esc_html__( 'What do you call the people who will sign in?', 'oxyarea' ) . '</label></th><td>';
		echo '<input name="role_name" id="oxyarea-setup-role" type="text" class="regular-text" value="' . esc_attr__( 'Customer', 'oxyarea' ) . '" required />';
		echo '<p class="description">' . esc_html__( 'This becomes a role. One role serves everybody of that kind, however many of them there are.', 'oxyarea' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="oxyarea-setup-page">' . esc_html__( 'The sign-in page', 'oxyarea' ) . '</label></th><td>';
		echo '<input name="page_title" id="oxyarea-setup-page" type="text" class="regular-text" value="' . esc_attr__( 'Sign in', 'oxyarea' ) . '" required />';
		echo '<p class="description">' . esc_html__( 'A new page carrying the sign-in and forgotten-password blocks.', 'oxyarea' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="oxyarea-setup-dashboard">' . esc_html__( 'Their dashboard', 'oxyarea' ) . '</label></th><td>';
		echo '<input name="dashboard_title" id="oxyarea-setup-dashboard" type="text" class="regular-text" value="' . esc_attr__( 'Customer area', 'oxyarea' ) . '" required />';
		echo '<p class="description">' . esc_html__( 'A dashboard for that role, with a greeting and an account summary to start from. It is created as a draft so nothing appears before you have looked at it.', 'oxyarea' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button( __( 'Build it', 'oxyarea' ) );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Build everything.
	 *
	 * @return void
	 */
	public function handle(): void {
		check_admin_referer( 'oxyarea_setup' );

		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'oxyarea' ), '', array( 'response' => 403 ) );
		}

		$role_name = isset( $_POST['role_name'] ) ? sanitize_text_field( wp_unslash( $_POST['role_name'] ) ) : '';
		$page_name = isset( $_POST['page_title'] ) ? sanitize_text_field( wp_unslash( $_POST['page_title'] ) ) : '';
		$dashboard = isset( $_POST['dashboard_title'] ) ? sanitize_text_field( wp_unslash( $_POST['dashboard_title'] ) ) : '';

		try {
			$role = $this->role( $role_name );
		} catch ( RoleException $e ) {
			Notices::remember( 'error', $e->getMessage() );

			$this->go_back();
		}

		$page_id      = $this->page( $page_name );
		$dashboard_id = $this->dashboard( $dashboard, $role );

		$this->settings->update( array( 'login_page' => $page_id ) );

		$this->redirects->save(
			new RedirectRule(
				RedirectEvent::LOGIN,
				Subject::role( $role ),
				(string) wp_make_link_relative( (string) get_permalink( $page_id ) )
			)
		);

		update_option( self::DONE_OPTION, gmdate( 'c' ), false );

		Notices::remember(
			'success',
			sprintf(
				/* translators: 1: role name, 2: page title, 3: dashboard title. */
				esc_html__( 'Built: the role "%1$s", the page "%2$s", and the draft dashboard "%3$s".', 'oxyarea' ),
				esc_html( $role ),
				esc_html( $page_name ),
				esc_html( $dashboard )
			)
		);

		unset( $dashboard_id );

		$this->go_back();
	}

	/**
	 * The role, made if it is not there.
	 *
	 * @param string $name What to call it.
	 * @return string The slug.
	 *
	 * @throws RoleException If it cannot be made.
	 */
	private function role( string $name ): string {
		$slug = sanitize_key( $name );

		if ( '' !== $slug && null !== get_role( $slug ) ) {
			return $slug;
		}

		return $this->roles->create( $name, '', array( 'read' ), get_current_user_id() );
	}

	/**
	 * The sign-in page, made if it is not there.
	 *
	 * @param string $title What to call it.
	 * @return int
	 */
	private function page( string $title ): int {
		$existing = get_page_by_path( sanitize_title( $title ) );

		if ( null !== $existing ) {
			return (int) $existing->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => "<!-- wp:oxyarea/login /-->\n<!-- wp:oxyarea/lost-password /-->\n<!-- wp:oxyarea/reset-password /-->",
			)
		);

		return is_wp_error( $page_id ) ? 0 : (int) $page_id;
	}

	/**
	 * The dashboard, as a draft.
	 *
	 * @param string $title What to call it.
	 * @param string $role  Who it is for.
	 * @return int
	 */
	private function dashboard( string $title, string $role ): int {
		$post_id = wp_insert_post(
			array(
				'post_type'    => DashboardPostType::POST_TYPE,
				'post_status'  => 'draft',
				'post_title'   => $title,
				'post_content' => "<!-- wp:oxyarea/welcome /-->\n<!-- wp:oxyarea/profile-summary /-->\n<!-- wp:oxyarea/logout /-->",
			)
		);

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		update_post_meta( (int) $post_id, DashboardPostType::AUDIENCE_META, 'role:' . $role );

		return (int) $post_id;
	}

	/**
	 * What to check before believing any of it.
	 *
	 * @return void
	 */
	private function render_checklist(): void {
		echo '<h2>' . esc_html__( 'Before you believe it works', 'oxyarea' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'A wizard reporting success has not proved anything. These four take two minutes.', 'oxyarea' ) . '</p>';
		echo '<ol>';
		echo '<li>' . esc_html__( 'Publish the dashboard: it was created as a draft, and a draft resolves to nothing.', 'oxyarea' ) . '</li>';
		echo '<li>' . esc_html__( 'Give somebody the role, on the Roles screen.', 'oxyarea' ) . '</li>';
		echo '<li>' . esc_html__( 'Sign in as them, in a private browser window, and check where you land.', 'oxyarea' ) . '</li>';
		echo '<li>' . esc_html__( 'Sign out and open the same address again: you should not see it.', 'oxyarea' ) . '</li>';
		echo '</ol>';
	}

	/**
	 * Back to the screen.
	 *
	 * @return never
	 */
	private function go_back() {
		wp_safe_redirect( add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) ) );

		exit;
	}
}
