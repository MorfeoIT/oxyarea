<?php
/**
 * The settings.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Admin;

use OxyArea\Auth\Destination;
use OxyArea\Content\Unauthorised;
use OxyArea\Infrastructure\Registrable;
use OxyArea\Infrastructure\Settings;
use OxyArea\Roles\Capabilities;

/**
 * Five settings, and one of them needs a warning.
 *
 * The sign-in page is the one that unblocks the rest: until a site says where
 * its sign-in form is, the password reset email keeps sending people to
 * wp-login.php and a refused page has nowhere to send them.
 *
 * "Delete everything on uninstall" is off, says plainly what it does, and is the
 * only control here that can lose somebody's work. It is worded as a sentence
 * rather than a label because a checkbox called "delete data" is one somebody
 * ticks while looking at something else.
 */
final class SettingsScreen implements Registrable {

	/**
	 * The page slug.
	 */
	public const SLUG = 'oxyarea-settings';

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Build the screen.
	 *
	 * @param Settings $settings The settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_oxyarea_save_settings', array( $this, 'handle_save' ) );
	}

	/**
	 * Draw the screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'oxyarea' ), '', array( 'response' => 403 ) );
		}

		$current = $this->settings->all();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Settings', 'oxyarea' ) . '</h1>';

		Notices::show();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'oxyarea_save_settings' );
		echo '<input type="hidden" name="action" value="oxyarea_save_settings" />';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="oxyarea-login-page">' . esc_html__( 'Sign-in page', 'oxyarea' ) . '</label></th><td>';
		wp_dropdown_pages(
			array(
				'name'              => 'login_page',
				'id'                => 'oxyarea-login-page',
				'selected'          => (int) $current['login_page'],
				'show_option_none'  => esc_html__( '— none chosen —', 'oxyarea' ),
				'option_none_value' => '0',
			)
		);
		echo '<p class="description">' . esc_html__( 'The page carrying the sign-in block. Until this is set, the password reset email sends people to wp-login.php and a refused page has nowhere to send them.', 'oxyarea' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="oxyarea-default-redirect">' . esc_html__( 'Where people land after signing in', 'oxyarea' ) . '</label></th><td>';
		echo '<input name="default_login_redirect" id="oxyarea-default-redirect" type="text" class="regular-text" value="' . esc_attr( (string) $current['default_login_redirect'] ) . '" placeholder="/" />';
		echo '<p class="description">' . esc_html__( 'Only when no redirect rule matches. A path on this site; anything else is refused.', 'oxyarea' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="oxyarea-restricted-behaviour">' . esc_html__( 'When somebody may not see a page', 'oxyarea' ) . '</label></th><td>';
		echo '<select name="restricted_behaviour" id="oxyarea-restricted-behaviour">';

		foreach ( $this->behaviours() as $behaviour ) {
			echo '<option value="' . esc_attr( $behaviour['value'] ) . '"'
				. selected( (string) $current['restricted_behaviour'], $behaviour['value'], false ) . '>'
				. esc_html( $behaviour['label'] ) . '</option>';
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Only "not found" avoids confirming that the page is there at all. Somebody already signed in is shown the message rather than sent to sign in again.', 'oxyarea' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'The WordPress admin', 'oxyarea' ) . '</th><td>';
		echo '<label><input type="checkbox" name="block_admin_access" value="1"' . checked( (bool) $current['block_admin_access'], true, false ) . ' /> '
			. esc_html__( 'Send people who cannot write posts back to the front of the site', 'oxyarea' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Staff and anybody who administers OxyArea are unaffected.', 'oxyarea' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Deleting the plugin', 'oxyarea' ) . '</th><td>';
		echo '<label><input type="checkbox" name="delete_data_on_uninstall" value="1"' . checked( (bool) $current['delete_data_on_uninstall'], true, false ) . ' /> '
			. esc_html__( 'When OxyArea is deleted, also delete its dashboards, rules and settings', 'oxyarea' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Off by default. Deleting a plugin is often a way of testing something, and it should not cost a site its private area. Switching the plugin off never removes anything, whatever this says.', 'oxyarea' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Save the settings.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		check_admin_referer( 'oxyarea_save_settings' );

		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'oxyarea' ), '', array( 'response' => 403 ) );
		}

		$redirect = isset( $_POST['default_login_redirect'] )
			? sanitize_text_field( wp_unslash( $_POST['default_login_redirect'] ) )
			: '';

		$behaviour = isset( $_POST['restricted_behaviour'] )
			? sanitize_key( wp_unslash( $_POST['restricted_behaviour'] ) )
			: Unauthorised::LOGIN;

		$this->settings->update(
			array(
				'login_page'               => isset( $_POST['login_page'] ) ? absint( wp_unslash( $_POST['login_page'] ) ) : 0,
				// Checked here as well as when it is used. A destination that is
				// refused on the way in is one nobody has to wonder about later.
				'default_login_redirect'   => Destination::make_safe( $redirect, '' ),
				'restricted_behaviour'     => $behaviour,
				'block_admin_access'       => isset( $_POST['block_admin_access'] ),
				'delete_data_on_uninstall' => isset( $_POST['delete_data_on_uninstall'] ),
			)
		);

		if ( '' !== trim( $redirect ) && '' === Destination::make_safe( $redirect, '' ) ) {
			Notices::remember(
				'error',
				esc_html__( 'Saved, but the destination was left empty: it has to be a path on this site.', 'oxyarea' )
			);
		} else {
			Notices::remember( 'success', esc_html__( 'Saved.', 'oxyarea' ) );
		}

		wp_safe_redirect( add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) ) );

		exit;
	}

	/**
	 * How each refusal reads.
	 *
	 * A list of pairs rather than a map. Two of the four values are numeric
	 * strings, and PHP turns a numeric string key into an integer, so a map here
	 * could not honestly be typed as one.
	 *
	 * @return list<array{value: string, label: string}>
	 */
	private function behaviours(): array {
		return array(
			array(
				'value' => Unauthorised::LOGIN,
				'label' => __( 'Send them to the sign-in page', 'oxyarea' ),
			),
			array(
				'value' => Unauthorised::MESSAGE,
				'label' => __( 'Show the page with a short explanation', 'oxyarea' ),
			),
			array(
				'value' => Unauthorised::FORBIDDEN,
				'label' => __( 'Refuse, saying the page is not theirs (403)', 'oxyarea' ),
			),
			array(
				'value' => Unauthorised::NOT_FOUND,
				'label' => __( 'Answer as though the page does not exist (404)', 'oxyarea' ),
			),
		);
	}
}
