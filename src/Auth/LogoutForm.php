<?php
/**
 * Signing out.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Auth;

/**
 * The frontend sign-out button.
 *
 * A button that posts, not a link. WordPress's own logout URL is a nonced GET
 * and works, but a link is something a browser, an email scanner or a prefetcher
 * can follow on its own, and being signed out by an image tag on another site is
 * a poor experience even when it is not a security problem.
 */
final class LogoutForm extends Form {

	/**
	 * The value of the hidden action field.
	 *
	 * @return string
	 */
	public function action(): string {
		return 'logout';
	}

	/**
	 * Sign somebody out.
	 *
	 * @return void
	 */
	public function handle(): void {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, $this->nonce_action() ) ) {
			$this->errors->add( $this->action(), $this->expired_message() );

			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$destination = Destination::requested( home_url( '/' ) );

		wp_logout();

		wp_safe_redirect( Notices::url( $destination, Notices::SIGNED_OUT ) );

		exit;
	}

	/**
	 * The button.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( array $attributes = array() ): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		return $this->templates->render(
			'auth/logout',
			$this->context(
				array(
					'label'       => (string) ( $attributes['label'] ?? __( 'Sign out', 'oxyarea' ) ),
					'redirect_to' => (string) ( $attributes['redirectTo'] ?? '' ),
				)
			)
		);
	}
}
