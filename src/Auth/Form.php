<?php
/**
 * What every frontend form shares.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Auth;

use OxyArea\Infrastructure\Templates;

/**
 * The plumbing common to the authentication forms.
 *
 * Note what is *not* here: nonce verification. Each form checks its own, in its
 * own handle(), next to the fields the nonce protects. Hiding that behind an
 * inherited method saves five lines and costs the ability to see, at a glance at
 * one function, whether a submission is guarded.
 */
abstract class Form implements FormHandler {

	/**
	 * Rendering.
	 *
	 * @var Templates
	 */
	protected Templates $templates;

	/**
	 * Where failures go.
	 *
	 * @var FormErrors
	 */
	protected FormErrors $errors;

	/**
	 * Build the form.
	 *
	 * @param Templates  $templates Rendering.
	 * @param FormErrors $errors    Where failures go.
	 */
	public function __construct( Templates $templates, FormErrors $errors ) {
		$this->templates = $templates;
		$this->errors    = $errors;
	}

	/**
	 * The nonce action for this form.
	 *
	 * @return string
	 */
	public function nonce_action(): string {
		return 'oxyarea_' . $this->action();
	}

	/**
	 * A posted field, as a trimmed string.
	 *
	 * Only ever called after the caller has verified the nonce.
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	protected function posted( string $field ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the caller, which is the handle() of the form this field belongs to.
		$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';

		return trim( $value );
	}

	/**
	 * A posted password, untouched.
	 *
	 * Passwords are not trimmed and not sanitised. A trailing space somebody
	 * deliberately typed is part of their password, and "cleaning" it silently
	 * locks them out of their own account.
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	protected function posted_password( string $field ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by the caller; a password must reach wp_signon() exactly as it was typed, so it is unslashed and not sanitised.
		$value = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : '';

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Whether the posted nonce is missing or stale.
	 *
	 * Each handle() still reads the nonce itself; this only says what to do about
	 * the answer, so that the message is written once.
	 *
	 * @return string The message to show, in plain text.
	 */
	protected function expired_message(): string {
		return __( 'This form has been open too long and could not be submitted safely. Please try again.', 'oxyarea' );
	}

	/**
	 * The context every template gets.
	 *
	 * @param array<string, mixed> $extra Anything this particular form needs.
	 * @return array<string, mixed>
	 */
	protected function context( array $extra = array() ): array {
		return array_merge(
			array(
				'action'       => $this->action(),
				'nonce_action' => $this->nonce_action(),
				'field'        => FormController::FIELD,
				'errors'       => $this->errors->get( $this->action() ),
				'notice'       => Notices::current(),
			),
			$extra
		);
	}

	/**
	 * The path of the page the form is on, without our own notice flag.
	 *
	 * Read from the request rather than from WP::$request, which is what this
	 * used to do and which is empty here: forms are handled on `init`, and
	 * WordPress does not work out which page was asked for until `parse_request`,
	 * several hooks later. The symptom was quiet — a redirect to the site's front
	 * page instead of back to the form — and it survived every test until one
	 * followed the redirect.
	 *
	 * A path and not an absolute URL. It goes through the same guard as anything
	 * else, so what comes back is a path on this site or nothing at all, and
	 * wp_safe_redirect() and add_query_arg() both take a path happily.
	 *
	 * @return string
	 */
	protected function current_url(): string {
		// The URL of the page being displayed. Nothing is acted on: it is where
		// the browser already is, and it is made safe on the next line.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$requested = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';

		$safe = Destination::make_safe( $requested, '/' );

		// The flag has to go, or a message stays on screen through the next
		// submission, describing something that happened two clicks ago.
		return remove_query_arg( Notices::PARAMETER, $safe );
	}
}
