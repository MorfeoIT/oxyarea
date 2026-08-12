<?php
/**
 * Constants PHPStan needs that only a running WordPress defines.
 *
 * @package OxyArea
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || define( 'ABSPATH', '/wordpress/' );

// Defined by wp-includes/default-constants.php and wp-db.php, neither of which
// is part of the stub package.
defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );
defined( 'ARRAY_A' ) || define( 'ARRAY_A', 'ARRAY_A' );
defined( 'ARRAY_N' ) || define( 'ARRAY_N', 'ARRAY_N' );
defined( 'OBJECT' ) || define( 'OBJECT', 'OBJECT' );
