<?php
/**
 * Plugin Name:       OxyArea – Private Client Area & User Portal
 * Plugin URI:        https://oxywp.com/oxyarea/
 * Description:       Build a complete private client area in WordPress: frontend login, roles, role dashboards, post-login redirects and content restriction, without assembling five plugins.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Oxysoft
 * Author URI:        https://oxysoft.it/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       oxyarea
 * Domain Path:       /languages
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea;

use OxyArea\Infrastructure\Activator;
use OxyArea\Infrastructure\Deactivator;

defined( 'ABSPATH' ) || exit;

const VERSION     = '0.1.0';
const PLUGIN_FILE = __FILE__;
const MIN_PHP     = '8.1';
const MIN_WP      = '6.5';

/**
 * Version of the extension contract, not of the plugin.
 *
 * An add-on needs to say "I require the OxyArea API" without pinning itself to a
 * release, because the two move at different speeds: OxyArea will ship bug fixes
 * and features weekly that change nothing an add-on can see, and must be free to
 * do so without every add-on declaring a new minimum.
 *
 * What this number covers is everything an add-on is invited to rely on: the
 * `oxyarea_*` actions and filters, the interfaces under `Access`, `Dashboard`,
 * `Redirect` and `Infrastructure`, the service container's identifiers, and the
 * shape of the values passed to and returned from all of them.
 *
 * Raise the major when something in that list is removed or changes meaning, so
 * that an add-on built against the old contract refuses to load rather than
 * failing halfway through a request. Raise the minor when something is added and
 * everything already written keeps working.
 */
const API_VERSION = '1.2';

/**
 * Absolute path to the plugin directory, with a trailing slash.
 *
 * @return string
 */
function plugin_dir(): string {
	return plugin_dir_path( __FILE__ );
}

/**
 * Public URL of the plugin directory, with a trailing slash.
 *
 * @return string
 */
function plugin_url(): string {
	return plugin_dir_url( __FILE__ );
}

/**
 * Minimal PSR-4 autoloader.
 *
 * OxyArea ships no third-party runtime dependency, so Composer's autoloader is a
 * development tool only and never has to be bundled. That also keeps the
 * distributed package free of a vendor directory the review team would have to
 * read through.
 *
 * @param string $class_name Fully qualified class name.
 * @return void
 */
function autoload( string $class_name ): void {
	$prefix = __NAMESPACE__ . '\\';

	if ( 0 !== strpos( $class_name, $prefix ) ) {
		return;
	}

	$relative = substr( $class_name, strlen( $prefix ) );
	$path     = plugin_dir() . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

	if ( is_readable( $path ) ) {
		require_once $path;
	}
}
spl_autoload_register( __NAMESPACE__ . '\\autoload' );

/**
 * Whether the environment satisfies the plugin's requirements.
 *
 * @return bool
 */
function requirements_met(): bool {
	return version_compare( PHP_VERSION, MIN_PHP, '>=' )
		&& version_compare( (string) get_bloginfo( 'version' ), MIN_WP, '>=' );
}

/**
 * Explain, once, why the plugin is not running.
 *
 * Only administrators see it: telling a subscriber that the private area plugin
 * failed to start is information they cannot act on.
 *
 * @return void
 */
function requirements_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: required PHP version, 2: required WordPress version. */
				__( 'OxyArea needs PHP %1$s or later and WordPress %2$s or later. It has not been started.', 'oxyarea' ),
				MIN_PHP,
				MIN_WP
			)
		)
	);
}

/**
 * Boot the plugin.
 *
 * No load_plugin_textdomain() call: WordPress has loaded translations for
 * directory-hosted plugins by itself since 4.6, and since 6.7 calling it this
 * early is what produces the "loaded too early" notice.
 *
 * @return void
 */
function bootstrap(): void {
	if ( ! requirements_met() ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\requirements_notice' );

		return;
	}

	( new Plugin() )->boot();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );

// Deactivation removes nothing. A site whose private area is switched off for an
// afternoon must find every dashboard, rule and assignment intact when it comes
// back on.
register_deactivation_hook( __FILE__, array( Deactivator::class, 'deactivate' ) );
