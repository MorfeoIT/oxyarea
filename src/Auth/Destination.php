<?php
/**
 * Where a form sends somebody, in WordPress terms.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Auth;

/**
 * Reads a requested destination from the request and makes it safe.
 *
 * A thin layer over SafeRedirect, which does the deciding and knows nothing
 * about WordPress. This half knows where home is and what the fallback should
 * be, and nothing about what makes a URL dangerous.
 */
final class Destination {

	/**
	 * The field a form uses to say where it was going.
	 */
	public const FIELD = 'redirect_to';

	/**
	 * The destination this request asked for, if it is one we may use.
	 *
	 * @param string $fallback Where to go when the request asked for nothing usable.
	 * @return string
	 */
	public static function requested( string $fallback ): string {
		// Read from either method: a login form posts it, a link carries it. The
		// value is never trusted — that is what sanitise() is for — so there is
		// nothing here for a nonce to protect. Sanitising first can only make the
		// string tamer, and what survives still has to satisfy a whitelist.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = isset( $_REQUEST[ self::FIELD ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ self::FIELD ] ) ) : '';

		return self::make_safe( $raw, $fallback );
	}

	/**
	 * Make a destination safe, or replace it with the fallback.
	 *
	 * @param string $candidate Where the request asked to go.
	 * @param string $fallback  Where to go instead when it cannot be trusted.
	 * @return string
	 */
	public static function make_safe( string $candidate, string $fallback ): string {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return SafeRedirect::sanitise( $candidate, $fallback, $host );
	}

	/**
	 * The default place to land after signing in.
	 *
	 * Sprint D replaces this with the redirect engine and its per-role rules.
	 * Until then it is the front page, and the setting is honoured if one is set.
	 *
	 * @param string $configured The configured default, which may be empty.
	 * @return string
	 */
	public static function after_login( string $configured ): string {
		$home = home_url( '/' );

		return '' === trim( $configured ) ? $home : self::make_safe( $configured, $home );
	}
}
