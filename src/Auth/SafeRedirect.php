<?php
/**
 * Where a form is allowed to send somebody.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Auth;

/*
 * Pure by design, like the Access layer: an open-redirect guard that cannot be
 * exercised exhaustively is not a guard. No WordPress call belongs here.
 */

/**
 * Decides whether a destination may be used, and falls back when it may not.
 *
 * Every login form on the internet carries a "where was I going" parameter, and
 * every one of them is a phishing vector if it is believed. The attack is dull
 * and effective: send somebody a link to the real site with
 * `?redirect_to=https://not-the-real-site.example`, they sign in to the genuine
 * login form, and land somewhere else entirely with their guard down.
 *
 * So the rule is a whitelist, not a blacklist. A destination is used only if it
 * is a plain path on this site, or an absolute http(s) URL whose host is this
 * host. Everything else, including everything nobody has thought of yet, becomes
 * the fallback.
 */
final class SafeRedirect {

	/**
	 * The schemes an absolute destination may use.
	 */
	private const ALLOWED_SCHEMES = array( 'http', 'https' );

	/**
	 * Return the destination if it is safe, or the fallback if it is not.
	 *
	 * @param string $candidate Where the request asked to go.
	 * @param string $fallback  Where to go instead when the request cannot be trusted.
	 * @param string $host      This site's host, without scheme or port.
	 * @return string
	 */
	public static function sanitise( string $candidate, string $fallback, string $host ): string {
		$candidate = trim( $candidate );

		if ( '' === $candidate ) {
			return $fallback;
		}

		// Control characters, including the newlines used to smuggle a second
		// header in, and the tabs and nulls browsers have historically stripped
		// before following a URL.
		if ( 1 === preg_match( '/[\x00-\x1F\x7F]/', $candidate ) ) {
			return $fallback;
		}

		// Backslashes: several browsers treat them as slashes, so "/\evil.example"
		// is read as protocol-relative by the browser and as a harmless path by
		// anything doing naive string checks. Nothing legitimate needs one.
		if ( false !== strpos( $candidate, '\\' ) ) {
			return $fallback;
		}

		// Protocol-relative. The scheme is inherited, so parse_url() reports no
		// scheme at all and the host check below would never run.
		if ( 0 === strpos( $candidate, '//' ) ) {
			return $fallback;
		}

		// wp_parse_url() exists to smooth over how old PHP handled URLs that begin
		// with "//", and those are refused on the line above before this runs. Its
		// absence is what keeps this class free of WordPress and therefore
		// testable, which matters more here than anywhere else in the plugin.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$parts = parse_url( $candidate );

		if ( false === $parts ) {
			return $fallback;
		}

		if ( isset( $parts['scheme'] ) ) {
			if ( ! in_array( strtolower( $parts['scheme'] ), self::ALLOWED_SCHEMES, true ) ) {
				return $fallback;
			}

			// An absolute URL must name this host, and must name it in the host
			// position: "https://this.example@evil.example/" parses with a host of
			// evil.example, which is the point of writing it that way.
			if ( ! isset( $parts['host'] ) || strtolower( $parts['host'] ) !== strtolower( $host ) ) {
				return $fallback;
			}

			return $candidate;
		}

		if ( isset( $parts['host'] ) ) {
			return $fallback;
		}

		// A relative path only, and only one anchored at the root. "foo/bar"
		// resolves against whatever page it is used on, which makes where it
		// leads a function of where the form was, and that is not something to
		// reason about at a security boundary.
		if ( 0 !== strpos( $candidate, '/' ) ) {
			return $fallback;
		}

		return $candidate;
	}
}
