<?php
/**
 * What to say after a redirect.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Auth;

/**
 * Turns a flag in the URL into a message.
 *
 * A successful submission redirects, so the message has to survive that. Most of
 * the people these forms serve are not signed in at the moment it matters —
 * somebody asking for a password reset by definition is not — so there is no
 * user to key a transient to, and a session cookie for a sentence of feedback is
 * a poor trade.
 *
 * So the URL carries a flag, and the flag is looked up in a fixed list. What
 * lands on the page is a string from this file, never a string from the query,
 * which is the difference between a notice and a cross-site scripting hole.
 */
final class Notices {

	/**
	 * The query parameter that carries the flag.
	 */
	public const PARAMETER = 'oxyarea-notice';

	/**
	 * A reset email has been requested.
	 */
	public const RESET_REQUESTED = 'reset-requested';

	/**
	 * A password has been changed.
	 */
	public const PASSWORD_CHANGED = 'password-changed';

	/**
	 * A profile has been saved.
	 */
	public const PROFILE_SAVED = 'profile-saved';

	/**
	 * Somebody has signed out.
	 */
	public const SIGNED_OUT = 'signed-out';

	/**
	 * The message for a flag, or an empty string if the flag is not one of ours.
	 *
	 * @param string $flag The flag from the query string.
	 * @return string Plain text, to be escaped where it is printed.
	 */
	public static function message( string $flag ): string {
		switch ( $flag ) {
			case self::RESET_REQUESTED:
				// Deliberately says nothing about whether the account exists. See
				// LostPasswordForm for why.
				return __( 'If there is an account for that address, an email is on its way with a link to set a new password.', 'oxyarea' );

			case self::PASSWORD_CHANGED:
				return __( 'Your password has been changed. You can sign in with it now.', 'oxyarea' );

			case self::PROFILE_SAVED:
				return __( 'Your details have been saved.', 'oxyarea' );

			case self::SIGNED_OUT:
				return __( 'You are signed out.', 'oxyarea' );

			default:
				return '';
		}
	}

	/**
	 * The message for the flag in the current request, if any.
	 *
	 * @return string
	 */
	public static function current(): string {
		// Reading a flag to decide which fixed sentence to show. There is nothing
		// to protect with a nonce: it changes nothing, and the value is used only
		// to look up a string in the list above.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$flag = isset( $_GET[ self::PARAMETER ] ) ? sanitize_key( wp_unslash( $_GET[ self::PARAMETER ] ) ) : '';

		return self::message( $flag );
	}

	/**
	 * A URL carrying a flag.
	 *
	 * @param string $url  Where to go.
	 * @param string $flag Which message to show on arrival.
	 * @return string
	 */
	public static function url( string $url, string $flag ): string {
		return add_query_arg( self::PARAMETER, $flag, $url );
	}
}
