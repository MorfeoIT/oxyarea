<?php
/**
 * PHPUnit bootstrap.
 *
 * The unit suite runs without WordPress: the Access layer and the container are
 * plain PHP by design, so they load through Composer's autoloader alone. The
 * integration and security suites load the WordPress test library on top of
 * this.
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
