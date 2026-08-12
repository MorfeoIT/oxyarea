<?php
/**
 * PHPUnit bootstrap.
 *
 * Serves two kinds of test from one file.
 *
 * The **unit** suite runs without WordPress: the Access, Redirect, Dashboard and
 * Tools layers are plain PHP by design, so they load through Composer's
 * autoloader alone and run anywhere, in a fraction of a second.
 *
 * The **integration** and **security** suites need a real WordPress and a real
 * database. They are switched on by WP_PHPUNIT__TESTS_CONFIG, which points at a
 * wp-tests-config.php. Without it this bootstrap does nothing more, so
 * `composer test` keeps working on a machine with no database at all.
 *
 * The switch is deliberately *not* WP_PHPUNIT__DIR. That variable looks like the
 * obvious one and is useless for the purpose: the wp-phpunit package sets it
 * itself, from a Composer autoload file, the moment the autoloader runs. Keying
 * off it made the unit suite try to boot WordPress.
 *
 * @package OxyArea
 */

declare(strict_types=1);

$oxyarea_autoload = __DIR__ . '/../vendor/autoload.php';

if ( ! file_exists( $oxyarea_autoload ) ) {
	fwrite( STDERR, "Run 'composer install' before the test suite.\n" );
	exit( 1 );
}

require $oxyarea_autoload;

$oxyarea_config = getenv( 'WP_PHPUNIT__TESTS_CONFIG' );

if ( false === $oxyarea_config || '' === $oxyarea_config ) {
	// No WordPress asked for. The unit suite is all that will run, and it needs
	// nothing else.
	return;
}

if ( ! is_readable( $oxyarea_config ) ) {
	fwrite( STDERR, "WP_PHPUNIT__TESTS_CONFIG does not point at a readable file: {$oxyarea_config}" . PHP_EOL );
	exit( 1 );
}

$oxyarea_wp_tests = (string) getenv( 'WP_PHPUNIT__DIR' );

if ( ! is_readable( $oxyarea_wp_tests . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WP_PHPUNIT__DIR does not point at the WordPress test library: {$oxyarea_wp_tests}\n" );
	exit( 1 );
}

require_once $oxyarea_wp_tests . '/includes/functions.php';

/**
 * Load the plugin before WordPress finishes starting.
 *
 * muplugins_loaded is early enough that the plugin's own `plugins_loaded` hook
 * still fires, so the object graph is built exactly as it is on a real site
 * rather than by hand in a test.
 */
tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__ ) . '/oxyarea.php';
	}
);

/**
 * Create the tables and grant the capabilities, as activation would.
 *
 * The test suite installs WordPress from scratch and never runs an activation
 * hook, so anything that happens only on activation has to be asked for here.
 */
tests_add_filter(
	'wp_install',
	static function (): void {
		( new OxyArea\Infrastructure\Migrator() )->migrate();

		OxyArea\Roles\Capabilities::ensure_granted();
	}
);

require $oxyarea_wp_tests . '/includes/bootstrap.php';
