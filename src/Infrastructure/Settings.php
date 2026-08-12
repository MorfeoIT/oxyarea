<?php
/**
 * The plugin's own settings.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Infrastructure;

/**
 * Reads and writes the single option OxyArea stores its configuration in.
 *
 * One option rather than a dozen: the settings are read together on nearly every
 * request, and one autoloaded row is cheaper than twelve.
 */
final class Settings {

	/**
	 * The option name.
	 */
	public const OPTION = 'oxyarea_settings';

	/**
	 * Defaults, which are also the full list of recognised keys.
	 *
	 * @return array<string, scalar>
	 */
	public static function defaults(): array {
		return array(
			// Deleting the plugin does not delete the site's private area. An
			// administrator who wants that turns this on first, and means it.
			'delete_data_on_uninstall' => false,

			// Where a user is sent when no rule matches. Empty means "leave
			// WordPress to do what it would have done".
			'default_login_redirect'   => '',

			// The page carrying the login and password blocks. 0 means the site
			// has not said, and WordPress keeps its own wp-login.php flow, which
			// is the right default for a plugin that was activated a minute ago
			// and knows nothing about this site's pages.
			'login_page'               => 0,

			// What an unauthorised visitor gets by default: login, message, 403
			// or 404. Sprint F is what reads this.
			'restricted_behaviour'     => 'login',

			// Whether wp-admin is kept for users who have no business there.
			// Off until the redirect engine exists to enforce it safely.
			'block_admin_access'       => false,
		);
	}

	/**
	 * Every setting, defaults filled in.
	 *
	 * @return array<string, scalar>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		// Unknown keys are dropped rather than carried: a setting removed in an
		// upgrade should not linger in the row forever.
		return array_merge( self::defaults(), array_intersect_key( $stored, self::defaults() ) );
	}

	/**
	 * One setting.
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $default_value Returned when the setting is not recognised.
	 * @return mixed
	 */
	public function get( string $key, $default_value = null ) {
		$all = $this->all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default_value;
	}

	/**
	 * Write settings, merging over what is already stored.
	 *
	 * @param array<string, mixed> $changes Settings to change.
	 * @return void
	 */
	public function update( array $changes ): void {
		update_option( self::OPTION, self::sanitize( array_merge( $this->all(), $changes ) ) );
	}

	/**
	 * Coerce a settings array into the shape the plugin expects.
	 *
	 * Pure by design: it takes an array and returns an array, calls nothing, and
	 * is therefore the one part of the settings that can be tested without
	 * WordPress. Anything not recognised is discarded, which is what stops a
	 * crafted settings import from planting keys of its own.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array<string, scalar>
	 */
	public static function sanitize( array $input ): array {
		$defaults = self::defaults();
		$clean    = array();

		foreach ( $defaults as $key => $default_value ) {
			$value = $input[ $key ] ?? $default_value;

			if ( is_bool( $default_value ) ) {
				$clean[ $key ] = (bool) $value;

				continue;
			}

			if ( is_int( $default_value ) ) {
				// A post ID that is not a number, or is negative, means "not set"
				// rather than "guess". absint would turn -3 into 3 and point the
				// login flow at whatever post that is.
				$clean[ $key ] = is_numeric( $value ) && (int) $value > 0 ? (int) $value : $default_value;

				continue;
			}

			$clean[ $key ] = is_scalar( $value ) ? (string) $value : (string) $default_value;
		}

		// A behaviour outside the known set is a typo or an attack; either way the
		// safe reading is the most restrictive one that still tells the user what
		// happened.
		if ( ! in_array( $clean['restricted_behaviour'], array( 'login', 'message', '403', '404' ), true ) ) {
			$clean['restricted_behaviour'] = 'login';
		}

		return $clean;
	}
}
