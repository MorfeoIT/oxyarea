<?php
/**
 * What happens to somebody who may not see this.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Content;

/*
 * Pure. What to do about a refusal is a decision with four branches and a
 * handful of awkward cases, and it is worth being able to check all of them
 * without standing up a request.
 */

/**
 * Chooses what a refusal looks like.
 *
 * Four possibilities, and the differences between them matter more than they
 * look:
 *
 * - **login** sends somebody who is not signed in to the sign-in form, carrying
 *   where they were going so they arrive there afterwards. For somebody who *is*
 *   signed in it would be a loop — they have an account, it simply is not the
 *   right one — so it becomes a message instead.
 * - **message** leaves them on the page with an explanation and a 200. Honest,
 *   and it tells the world the page exists.
 * - **403** says "this exists and you may not have it".
 * - **404** says nothing at all, which is the only one of the four that does not
 *   confirm the page is there. For a site whose private area is the product,
 *   that is often what a site owner actually wants.
 */
final class Unauthorised {

	/**
	 * Send them to the sign-in form.
	 */
	public const LOGIN = 'login';

	/**
	 * Leave them where they are, with an explanation.
	 */
	public const MESSAGE = 'message';

	/**
	 * Refuse, and admit the page exists.
	 */
	public const FORBIDDEN = '403';

	/**
	 * Refuse, and admit nothing.
	 */
	public const NOT_FOUND = '404';

	/**
	 * Every behaviour, in the order a settings screen would offer them.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array( self::LOGIN, self::MESSAGE, self::FORBIDDEN, self::NOT_FOUND );
	}

	/**
	 * What should actually happen, given the configured behaviour.
	 *
	 * @param string $configured What the site asked for.
	 * @param bool   $signed_in  Whether the visitor is signed in.
	 * @param string $login_url  Where the sign-in form is, empty if there is not one.
	 * @return string One of the constants above.
	 */
	public static function decide( string $configured, bool $signed_in, string $login_url ): string {
		if ( ! in_array( $configured, self::all(), true ) ) {
			// A value nobody recognises is treated as the safest of the four
			// rather than the friendliest.
			return self::NOT_FOUND;
		}

		if ( self::LOGIN !== $configured ) {
			return $configured;
		}

		// Sending somebody to sign in when they are already signed in is a loop
		// dressed up as a redirect: they would arrive back here, be refused
		// again, and be sent to sign in again.
		if ( $signed_in ) {
			return self::MESSAGE;
		}

		// And there is nothing to send them to if the site has not said where the
		// sign-in form is.
		if ( '' === trim( $login_url ) ) {
			return self::MESSAGE;
		}

		return self::LOGIN;
	}
}
