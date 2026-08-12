<?php
/**
 * Setting a new password.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Auth;

use WP_User;

/**
 * The form somebody lands on from the email.
 *
 * The key is checked twice: once to decide whether to draw the form at all, and
 * again when the form comes back. Checking only the first time would leave a
 * window in which an expired key still sets a password, and the window is as
 * long as the person leaves the tab open.
 *
 * Nobody is signed in as a side effect of resetting. The person proved they can
 * read an inbox, which is not the same as proving they meant to start a session,
 * and WordPress's own flow does not do it either.
 */
final class ResetPasswordForm extends Form {

	/**
	 * The query parameter carrying the key from the email.
	 */
	public const KEY_PARAMETER = 'oxyarea-key';

	/**
	 * The query parameter carrying the account the key belongs to.
	 */
	public const LOGIN_PARAMETER = 'oxyarea-login';

	/**
	 * The value of the hidden action field.
	 *
	 * @return string
	 */
	public function action(): string {
		return 'reset-password';
	}

	/**
	 * Set the new password.
	 *
	 * @return void
	 */
	public function handle(): void {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, $this->nonce_action() ) ) {
			$this->errors->add( $this->action(), $this->expired_message() );

			return;
		}

		$user = check_password_reset_key( $this->posted( 'key' ), $this->posted( 'login' ) );

		if ( ! $user instanceof WP_User ) {
			$this->errors->add( $this->action(), $this->stale_link_message() );

			return;
		}

		$password = $this->posted_password( 'pass1' );
		$repeated = $this->posted_password( 'pass2' );

		if ( '' === $password ) {
			$this->errors->add( $this->action(), __( 'Choose a password.', 'oxyarea' ) );

			return;
		}

		if ( $password !== $repeated ) {
			$this->errors->add( $this->action(), __( 'The two passwords are not the same.', 'oxyarea' ) );

			return;
		}

		reset_password( $user, $password );

		/**
		 * Fires after somebody has set a new password through OxyArea.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_User $user Whose password it was.
		 */
		do_action( 'oxyarea_password_reset', $user );

		wp_safe_redirect( Notices::url( $this->current_url(), Notices::PASSWORD_CHANGED ) );

		exit;
	}

	/**
	 * The form, or an explanation of why there is not one.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( array $attributes = array() ): string {
		unset( $attributes );

		$key   = $this->from_query( self::KEY_PARAMETER );
		$login = $this->from_query( self::LOGIN_PARAMETER );

		$usable = '' !== $key
			&& '' !== $login
			&& check_password_reset_key( $key, $login ) instanceof WP_User;

		// The key and the login are always in the context, empty or not, so that
		// the template never has to invent them. A template that assigns its own
		// variables is a template writing to what PHP considers global scope.
		return $this->templates->render(
			'auth/reset-password',
			$this->context(
				array(
					'usable' => $usable,
					'key'    => $usable ? $key : '',
					'login'  => $usable ? $login : '',
				)
			)
		);
	}

	/**
	 * A value from the query string.
	 *
	 * @param string $parameter Parameter name.
	 * @return string
	 */
	private function from_query( string $parameter ): string {
		// The key is the credential here, and check_password_reset_key() is what
		// judges it. A nonce would prove the link came from this site, which is
		// exactly what a link in an email cannot prove.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET[ $parameter ] ) ? sanitize_text_field( wp_unslash( $_GET[ $parameter ] ) ) : '';
	}

	/**
	 * What to say about a link that no longer works.
	 *
	 * Says nothing about which of the several reasons applies: expired, already
	 * used, or never valid. None of them is information the person can act on
	 * differently, and one of them would confirm an account exists.
	 *
	 * @return string
	 */
	private function stale_link_message(): string {
		return __( 'This link cannot be used any more. Ask for a new one and use the most recent email.', 'oxyarea' );
	}
}
