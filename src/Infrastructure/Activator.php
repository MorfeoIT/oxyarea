<?php
/**
 * What happens the moment the plugin is switched on.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Infrastructure;

use OxyArea\Roles\Capabilities;

use const OxyArea\MIN_PHP;
use const OxyArea\MIN_WP;
use const OxyArea\PLUGIN_FILE;

/**
 * Prepares a site to hold a private area.
 *
 * Everything here is safe to run twice, because activation happens again after
 * every reactivation and on every new site of a network.
 */
final class Activator {

	/**
	 * The option recording when OxyArea was first activated on this site.
	 */
	public const INSTALLED_AT_OPTION = 'oxyarea_installed_at';

	/**
	 * Set the site up.
	 *
	 * @param bool $network_wide Whether the plugin was activated for a whole network.
	 * @return void
	 */
	public static function activate( bool $network_wide = false ): void {
		self::guard_requirements();

		if ( $network_wide && is_multisite() ) {
			$sites = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( (array) $sites as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_site();
				restore_current_blog();
			}

			return;
		}

		self::activate_site();
	}

	/**
	 * Set up the current site.
	 *
	 * @return void
	 */
	private static function activate_site(): void {
		( new Migrator() )->migrate();

		Capabilities::ensure_granted();

		if ( ! get_option( self::INSTALLED_AT_OPTION ) ) {
			add_option( self::INSTALLED_AT_OPTION, gmdate( 'Y-m-d H:i:s' ), '', false );
		}

		// The settings row is created now, with its defaults, so that the rest of
		// the plugin can read settings without every call site handling "not
		// installed yet".
		if ( false === get_option( Settings::OPTION, false ) ) {
			add_option( Settings::OPTION, Settings::sanitize( array() ) );
		}
	}

	/**
	 * Refuse to activate on an environment that cannot run the plugin.
	 *
	 * Failing here, loudly, is kinder than activating and leaving a site owner
	 * with a private area that silently lets everyone in.
	 *
	 * @return void
	 */
	private static function guard_requirements(): void {
		if ( version_compare( PHP_VERSION, MIN_PHP, '>=' )
			&& version_compare( (string) get_bloginfo( 'version' ), MIN_WP, '>=' ) ) {
			return;
		}

		deactivate_plugins( plugin_basename( PLUGIN_FILE ) );

		wp_die(
			esc_html(
				sprintf(
					/* translators: 1: required PHP version, 2: required WordPress version, 3: current PHP version, 4: current WordPress version. */
					__( 'OxyArea needs PHP %1$s or later and WordPress %2$s or later. This site runs PHP %3$s and WordPress %4$s.', 'oxyarea' ),
					MIN_PHP,
					MIN_WP,
					PHP_VERSION,
					(string) get_bloginfo( 'version' )
				)
			),
			esc_html__( 'OxyArea could not be activated', 'oxyarea' ),
			array( 'back_link' => true )
		);
	}
}
