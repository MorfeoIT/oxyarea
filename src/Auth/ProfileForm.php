<?php
/**
 * Editing your own details.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Auth;

/**
 * The frontend profile form.
 *
 * Name and email and password, and nothing else. A private area's profile form
 * is not the WordPress user editor: the fields that decide what somebody may see
 * are not theirs to change, and a form that lets them try is a form somebody
 * will eventually get wrong.
 *
 * Changing an email address or a password asks for the current password first.
 * Not because the session is doubted — they are signed in — but because an
 * unattended laptop is how accounts are taken over in practice, and the two
 * fields that let somebody keep an account are exactly these two.
 */
final class ProfileForm extends Form {

	/**
	 * The value of the hidden action field.
	 *
	 * @return string
	 */
	public function action(): string {
		return 'profile';
	}

	/**
	 * Save the changes.
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
			$this->errors->add( $this->action(), __( 'You need to be signed in to change your details.', 'oxyarea' ) );

			return;
		}

		$user    = wp_get_current_user();
		$changes = array( 'ID' => $user->ID );

		$first = $this->posted( 'first_name' );
		$last  = $this->posted( 'last_name' );

		$changes['first_name'] = $first;
		$changes['last_name']  = $last;

		$display = $this->posted( 'display_name' );

		if ( '' !== $display ) {
			$changes['display_name'] = $display;
		}

		$email    = $this->posted( 'user_email' );
		$password = $this->posted_password( 'pass1' );
		$repeated = $this->posted_password( 'pass2' );

		$wants_new_email    = '' !== $email && strtolower( $email ) !== strtolower( (string) $user->user_email );
		$wants_new_password = '' !== $password;

		if ( $wants_new_email || $wants_new_password ) {
			$current = $this->posted_password( 'current_password' );

			if ( '' === $current || ! wp_check_password( $current, (string) $user->user_pass, $user->ID ) ) {
				$this->errors->add(
					$this->action(),
					__( 'Enter your current password to change your email address or your password.', 'oxyarea' )
				);

				return;
			}
		}

		if ( $wants_new_email ) {
			if ( ! is_email( $email ) ) {
				$this->errors->add( $this->action(), __( 'That does not look like an email address.', 'oxyarea' ) );

				return;
			}

			$changes['user_email'] = $email;
		}

		if ( $wants_new_password ) {
			if ( $password !== $repeated ) {
				$this->errors->add( $this->action(), __( 'The two new passwords are not the same.', 'oxyarea' ) );

				return;
			}

			$changes['user_pass'] = $password;
		}

		$result = wp_update_user( $changes );

		if ( is_wp_error( $result ) ) {
			$this->errors->add( $this->action(), wp_strip_all_tags( (string) $result->get_error_message() ) );

			return;
		}

		// Changing a password ends every session, including this one. Somebody who
		// just proved they know the old password should not be thrown out for it.
		if ( $wants_new_password ) {
			wp_set_auth_cookie( $user->ID, true, is_ssl() );
			wp_set_current_user( $user->ID );
		}

		wp_safe_redirect( Notices::url( $this->current_url(), Notices::PROFILE_SAVED ) );

		exit;
	}

	/**
	 * The form.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( array $attributes = array() ): string {
		if ( ! is_user_logged_in() ) {
			return $this->templates->render(
				'auth/signed-out',
				$this->context()
			);
		}

		return $this->templates->render(
			'auth/profile',
			$this->context(
				array(
					'user'          => wp_get_current_user(),
					'show_password' => (bool) ( $attributes['showPassword'] ?? true ),
					'label'         => (string) ( $attributes['label'] ?? __( 'Save changes', 'oxyarea' ) ),
				)
			)
		);
	}
}
