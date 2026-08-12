<?php
/**
 * Asking for a new password.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Auth;

/**
 * The "I have forgotten my password" form.
 *
 * The interesting decision here is what happens when the address is not one the
 * site knows. The answer is: exactly what happens when it is.
 *
 * A form that says "no account with that email" is a free membership check. On a
 * private client area that is worth something to whoever is asking: it confirms
 * that a particular person is a customer of this business. So the message, the
 * redirect and the time taken are the same either way, and the person who really
 * did forget their password finds the email in their inbox.
 */
final class LostPasswordForm extends Form {

	/**
	 * The value of the hidden action field.
	 *
	 * @return string
	 */
	public function action(): string {
		return 'lost-password';
	}

	/**
	 * Send a reset link, or appear to.
	 *
	 * @return void
	 */
	public function handle(): void {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, $this->nonce_action() ) ) {
			$this->errors->add( $this->action(), $this->expired_message() );

			return;
		}

		$identifier = $this->posted( 'user_login' );

		// An empty box is the one thing worth saying out loud: it is about what
		// they typed, not about who exists.
		if ( '' === $identifier ) {
			$this->errors->add(
				$this->action(),
				__( 'Enter the username or email address you use here.', 'oxyarea' )
			);

			return;
		}

		// The result is deliberately discarded. WordPress distinguishes "no such
		// account" from "sent", and passing that distinction on is the leak this
		// form exists to avoid.
		retrieve_password( $identifier );

		wp_safe_redirect( Notices::url( $this->current_url(), Notices::RESET_REQUESTED ) );

		exit;
	}

	/**
	 * The form.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( array $attributes = array() ): string {
		return $this->templates->render(
			'auth/lost-password',
			$this->context(
				array(
					'label' => (string) ( $attributes['label'] ?? __( 'Email me a link', 'oxyarea' ) ),
				)
			)
		);
	}
}
