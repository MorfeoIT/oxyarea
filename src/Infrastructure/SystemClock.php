<?php
/**
 * The real clock.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The machine's clock, in UTC.
 *
 * UTC and not the site's timezone: stored dates are UTC, and a site that moves
 * timezone must not silently move every access window with it.
 */
final class SystemClock implements ClockInterface {

	/**
	 * The current moment, in UTC.
	 *
	 * @return DateTimeImmutable
	 */
	public function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}
}
