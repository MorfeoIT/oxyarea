<?php
/**
 * Messages that survive a redirect, in the admin.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Admin;

/**
 * Holds a message for the screen the browser is about to land on.
 *
 * Kept against the user rather than passed through the URL, because a message in
 * a query string is a message an attacker can choose. Everything stored here is
 * already escaped, by the same rule the whole plugin follows: escaped once, by
 * whoever knows it is going into HTML.
 *
 * Not to be confused with OxyArea\Auth\Notices, which does the same job for
 * visitors who are not signed in and therefore have nothing to key a transient
 * to. The two exist separately because the constraints are different, and
 * pretending otherwise would mean a session cookie for a sentence of feedback.
 */
final class Notices {

	/**
	 * How long a message waits to be read.
	 */
	private const LIFETIME = MINUTE_IN_SECONDS;

	/**
	 * Keep a message for the next screen.
	 *
	 * @param string $type    'success' or 'error'.
	 * @param string $message What to say, already escaped for HTML.
	 * @return void
	 */
	public static function remember( string $type, string $message ): void {
		set_transient(
			self::key(),
			array(
				'type'    => 'error' === $type ? 'error' : 'success',
				'message' => $message,
			),
			self::LIFETIME
		);
	}

	/**
	 * Show the held message, and forget it.
	 *
	 * @return void
	 */
	public static function show(): void {
		$notice = get_transient( self::key() );

		if ( ! is_array( $notice ) || ! isset( $notice['message'] ) ) {
			return;
		}

		delete_transient( self::key() );

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			'error' === ( $notice['type'] ?? '' ) ? 'error' : 'success',
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by every caller of remember(); see the class comment.
			(string) $notice['message']
		);
	}

	/**
	 * Where this user's message is kept.
	 *
	 * @return string
	 */
	private static function key(): string {
		return 'oxyarea_notice_' . get_current_user_id();
	}
}
