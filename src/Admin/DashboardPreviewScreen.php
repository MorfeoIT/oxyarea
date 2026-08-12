<?php
/**
 * Seeing what a role will see.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Admin;

use OxyArea\Dashboard\DashboardRenderer;
use OxyArea\Infrastructure\Registrable;
use OxyArea\Roles\Capabilities;

/**
 * Shows which dashboard a role resolves to, and what it looks like.
 *
 * **Not impersonation.** Nothing signs in as anybody, no session is touched and
 * no capability is borrowed. The screen answers "which template would this role
 * get, and how does it read" — which is the question an administrator is
 * actually asking — and it fills the placeholders with the administrator's own
 * details, clearly labelled, because the alternative is borrowing a customer's
 * name to show it.
 *
 * The specification puts true impersonation out of scope for the free plugin,
 * and it is right to: signing in as somebody else is a serious capability with a
 * serious audit trail, and it is not the price of previewing a layout.
 */
final class DashboardPreviewScreen implements Registrable {

	/**
	 * The page slug.
	 */
	public const SLUG = 'oxyarea-dashboard-preview';

	/**
	 * The renderer.
	 *
	 * @var DashboardRenderer
	 */
	private DashboardRenderer $renderer;

	/**
	 * Build the screen.
	 *
	 * @param DashboardRenderer $renderer The renderer.
	 */
	public function __construct( DashboardRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Add the hooks.
	 *
	 * Nothing to hook: the screen only reads. It is Registrable so that the
	 * container treats it like every other service.
	 *
	 * @return void
	 */
	public function register(): void {
	}

	/**
	 * Draw the screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_DASHBOARDS ) ) {
			wp_die( esc_html__( 'You are not allowed to see this.', 'oxyarea' ), '', array( 'response' => 403 ) );
		}

		// Choosing which role to look at. Nothing is changed, so there is nothing
		// for a nonce to protect, and the capability check above has already
		// decided whether the reader may be here.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$chosen = isset( $_GET['oxyarea_role'] ) ? sanitize_key( wp_unslash( $_GET['oxyarea_role'] ) ) : '';

		$roles = wp_roles()->get_names();

		if ( '' === $chosen || ! isset( $roles[ $chosen ] ) ) {
			$chosen = (string) array_key_first( $roles );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Dashboard preview', 'oxyarea' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Which dashboard a role resolves to, and how it reads. Nobody is signed in as anybody: the placeholders below are filled with your own details.', 'oxyarea' ) . '</p>';

		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		echo '<label for="oxyarea-preview-role">' . esc_html__( 'Show me what this role gets:', 'oxyarea' ) . '</label> ';
		echo '<select name="oxyarea_role" id="oxyarea-preview-role">';

		foreach ( $roles as $slug => $name ) {
			echo '<option value="' . esc_attr( (string) $slug ) . '"' . selected( $chosen, (string) $slug, false ) . '>'
				. esc_html( translate_user_role( (string) $name ) ) . '</option>';
		}

		echo '</select> ';
		submit_button( __( 'Show me', 'oxyarea' ), 'secondary', 'submit', false );
		echo '</form>';

		$dashboard = $this->renderer->resolve_for_role( $chosen );

		if ( null === $dashboard ) {
			echo '<div class="notice notice-warning inline"><p>';
			printf(
				/* translators: %s: role name. */
				esc_html__( 'Nothing resolves for %s: neither a dashboard of its own nor a site default.', 'oxyarea' ),
				'<strong>' . esc_html( translate_user_role( (string) ( $roles[ $chosen ] ?? $chosen ) ) ) . '</strong>'
			);
			echo '</p></div>';
			echo '</div>';

			return;
		}

		echo '<h2>';
		printf(
			/* translators: 1: dashboard title, 2: who it is for. */
			esc_html__( '%1$s — for %2$s', 'oxyarea' ),
			'<em>' . esc_html( $dashboard->title() ) . '</em>',
			'<code>' . esc_html( $dashboard->subject_key() ) . '</code>'
		);
		echo ' <a class="button button-small" href="' . esc_url( (string) get_edit_post_link( $dashboard->id(), 'url' ) ) . '">'
			. esc_html__( 'Edit', 'oxyarea' ) . '</a></h2>';

		echo '<div class="oxyarea-preview" style="padding:1rem;border:1px solid #c3c4c7;background:#fff">';
		// The dashboard's own content, rendered by the same code that renders it
		// on the front of the site: do_blocks, shortcodes and placeholders, each
		// of which escapes what it emits. Escaping the result again here would
		// print the markup instead of the page.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->renderer->render( $dashboard, get_current_user_id() );
		echo '</div>';

		echo '</div>';
	}
}
