<?php
/**
 * Keeping the password flow on the site.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Auth;

use OxyArea\Infrastructure\Registrable;
use OxyArea\Infrastructure\Settings;
use WP_User;

/**
 * Points the lost-password links and the reset email at the site's own pages.
 *
 * Without this the flow works and then abandons the person on wp-login.php,
 * which is the one screen a private client area exists to keep them away from.
 *
 * It only acts when a page has been chosen in the settings. With no page
 * configured WordPress behaves exactly as it always has, which is the right
 * default for a plugin that has just been activated and knows nothing about the
 * site's pages yet.
 */
final class PasswordResetLinks implements Registrable {

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Build the service.
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
		add_filter( 'lostpassword_url', array( $this, 'lost_password_url' ), 10, 2 );
		add_filter( 'retrieve_password_message', array( $this, 'reset_message' ), 10, 4 );
	}

	/**
	 * Send "lost your password?" to the site's own page.
	 *
	 * @param string $url      Where WordPress was going to send them.
	 * @param string $redirect Where they were headed before this interruption.
	 * @return string
	 */
	public function lost_password_url( string $url, string $redirect ): string {
		$page = $this->page_url();

		if ( '' === $page ) {
			return $url;
		}

		if ( '' !== $redirect ) {
			$page = add_query_arg( Destination::FIELD, rawurlencode( $redirect ), $page );
		}

		return $page;
	}

	/**
	 * Rewrite the reset email so its link lands on the site's own page.
	 *
	 * The whole message is replaced rather than patched. WordPress's default is a
	 * translated block of prose with a bare URL in the middle of it, and finding
	 * that URL again with a regular expression is the kind of cleverness that
	 * breaks in a language nobody on the team reads.
	 *
	 * @param string  $message   The email WordPress was going to send.
	 * @param string  $key       The reset key.
	 * @param string  $login     The account it belongs to.
	 * @param WP_User $user_data The account.
	 * @return string
	 */
	public function reset_message( string $message, string $key, string $login, $user_data ): string {
		$page = $this->page_url();

		if ( '' === $page ) {
			return $message;
		}

		$link = add_query_arg(
			array(
				ResetPasswordForm::KEY_PARAMETER   => rawurlencode( $key ),
				ResetPasswordForm::LOGIN_PARAMETER => rawurlencode( $login ),
			),
			$page
		);

		$name = $user_data instanceof WP_User ? $user_data->display_name : $login;

		$lines = array(
			sprintf(
				/* translators: %s: the person's display name. */
				__( 'Hello %s,', 'oxyarea' ),
				$name
			),
			'',
			sprintf(
				/* translators: %s: the site name. */
				__( 'Somebody asked to set a new password for your account on %s.', 'oxyarea' ),
				wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES )
			),
			'',
			__( 'If that was not you, you can ignore this email and nothing will change.', 'oxyarea' ),
			'',
			__( 'To set a new password, open this link:', 'oxyarea' ),
			$link,
			'',
		);

		/**
		 * Filters the password reset email OxyArea sends.
		 *
		 * @since 0.1.0
		 *
		 * @param string $body  The message.
		 * @param string $link  The reset link it contains.
		 * @param string $login The account.
		 */
		return (string) apply_filters( 'oxyarea_password_reset_email', implode( "\r\n", $lines ), $link, $login );
	}

	/**
	 * The configured page, or an empty string when there is not one.
	 *
	 * @return string
	 */
	private function page_url(): string {
		$page_id = (int) $this->settings->get( 'login_page', 0 );

		if ( $page_id <= 0 ) {
			return '';
		}

		$permalink = get_permalink( $page_id );

		return is_string( $permalink ) ? $permalink : '';
	}
}
