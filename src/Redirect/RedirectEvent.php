<?php
/**
 * The moments a rule can act on.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Redirect;

/**
 * The four points at which OxyArea decides where somebody goes next.
 *
 * A closed list, and deliberately short. Every event here is a moment when
 * WordPress would otherwise pick a destination of its own, usually the admin
 * dashboard, which is the single most common complaint about running a customer
 * portal on WordPress.
 */
final class RedirectEvent {

	/**
	 * Just signed in.
	 */
	public const LOGIN = 'login';

	/**
	 * Just signed out.
	 */
	public const LOGOUT = 'logout';

	/**
	 * Just registered an account.
	 */
	public const REGISTRATION = 'registration';

	/**
	 * Just finished setting a new password.
	 */
	public const PASSWORD_RESET = 'password-reset';

	/**
	 * Every event, in the order they are shown.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::LOGIN,
			self::LOGOUT,
			self::REGISTRATION,
			self::PASSWORD_RESET,
		);
	}

	/**
	 * Whether this is an event OxyArea knows about.
	 *
	 * @param string $event The candidate.
	 * @return bool
	 */
	public static function exists( string $event ): bool {
		return in_array( $event, self::all(), true );
	}
}
