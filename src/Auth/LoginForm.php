<?php
/**
 * Signing in.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Auth;

use OxyArea\Infrastructure\Settings;
use OxyArea\Infrastructure\Templates;
use WP_Error;

/**
 * The frontend login form.
 *
 * Authentication itself is WordPress's, through wp_signon(). Nothing here
 * touches a password hash, compares a credential or writes a cookie: those are
 * solved problems and every reimplementation of them is a new vulnerability.
 *
 * What this class adds is the three things the built-in form gets wrong for a
 * private area: it lives on a page of the site rather than wp-login.php, it does
 * not tell a stranger which usernames exist, and it will not follow a
 * destination somebody attached to the URL.
 */
final class LoginForm extends Form {

	/**
	 * Error codes from WordPress that say whether an account exists.
	 *
	 * WordPress distinguishes "there is no such user" from "that is the wrong
	 * password", which is friendly and, on a site whose whole purpose is telling
	 * customers apart, an account enumeration oracle. A stranger with a list of
	 * email addresses learns which of your customers you have.
	 *
	 * These four become one message. Anything else — a two-factor plugin's
	 * challenge, a "this account is blocked" from somewhere else — is passed
	 * through, because those are answers the person needs.
	 */
	private const LEAKY_CODES = array(
		'invalid_username',
		'invalid_email',
		'incorrect_password',
		'invalidcombo',
	);

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Build the form.
	 *
	 * @param Templates  $templates Rendering.
	 * @param FormErrors $errors    Where failures go.
	 * @param Settings   $settings  The settings.
	 */
	public function __construct( Templates $templates, FormErrors $errors, Settings $settings ) {
		parent::__construct( $templates, $errors );

		$this->settings = $settings;
	}

	/**
	 * The value of the hidden action field.
	 *
	 * @return string
	 */
	public function action(): string {
		return 'login';
	}

	/**
	 * Sign somebody in.
	 *
	 * @return void
	 */
	public function handle(): void {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, $this->nonce_action() ) ) {
			$this->errors->add( $this->action(), $this->expired_message() );

			return;
		}

		if ( is_user_logged_in() ) {
			return;
		}

		$user = wp_signon(
			array(
				'user_login'    => $this->posted( 'user_login' ),
				'user_password' => $this->posted_password( 'user_password' ),
				'remember'      => '' !== $this->posted( 'remember' ),
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			$this->errors->add( $this->action(), $this->readable_failure( $user ) );

			return;
		}

		wp_set_current_user( $user->ID );

		// Whether they asked for somewhere in particular matters to whoever
		// filters this: a redirect rule should decide where people go by
		// default, not overrule the "sign in to read this, we will bring you
		// back" link they followed to get here.
		$requested   = Destination::requested( '' );
		$destination = '' !== $requested
			? $requested
			: Destination::after_login( (string) $this->settings->get( 'default_login_redirect', '' ) );

		/**
		 * Filters where somebody lands after signing in through OxyArea.
		 *
		 * The value returned is passed through the same safety check as the one
		 * that came from the request, so a filter cannot introduce an off-site
		 * redirect either.
		 *
		 * @since 0.1.0
		 *
		 * @param string   $destination Where they are about to go.
		 * @param \WP_User $user        Who just signed in.
		 * @param string   $requested   What the request asked for, empty if it asked for nothing.
		 */
		$destination = (string) apply_filters( 'oxyarea_login_destination', $destination, $user, $requested );

		wp_safe_redirect( Destination::make_safe( $destination, home_url( '/' ) ) );

		exit;
	}

	/**
	 * The form.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( array $attributes = array() ): string {
		if ( is_user_logged_in() ) {
			return $this->templates->render(
				'auth/signed-in',
				$this->context(
					array(
						'user'       => wp_get_current_user(),
						'logout_url' => wp_logout_url( $this->current_url() ),
					)
				)
			);
		}

		return $this->templates->render(
			'auth/login',
			$this->context(
				array(
					'redirect_to'       => Destination::requested( '' ),
					'lost_password_url' => wp_lostpassword_url( $this->current_url() ),
					'show_remember'     => (bool) ( $attributes['showRemember'] ?? true ),
					'show_lost'         => (bool) ( $attributes['showLostPassword'] ?? true ),
					'label'             => (string) ( $attributes['label'] ?? __( 'Sign in', 'oxyarea' ) ),
				)
			)
		);
	}

	/**
	 * Turn a sign-in failure into something safe to say out loud.
	 *
	 * @param WP_Error $error What WordPress reported.
	 * @return string Plain text.
	 */
	private function readable_failure( WP_Error $error ): string {
		$code = (string) $error->get_error_code();

		if ( in_array( $code, self::LEAKY_CODES, true ) ) {
			return __( 'That username or password is not right.', 'oxyarea' );
		}

		if ( 'empty_username' === $code || 'empty_password' === $code ) {
			return __( 'Please fill in both your username and your password.', 'oxyarea' );
		}

		$message = wp_strip_all_tags( (string) $error->get_error_message() );

		return '' !== $message
			? $message
			: __( 'That username or password is not right.', 'oxyarea' );
	}
}
