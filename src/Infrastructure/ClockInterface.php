<?php
/**
 * What time it is.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Infrastructure;

use DateTimeImmutable;

/**
 * The current moment, injected rather than read.
 *
 * Access rules can carry a period during which they apply. A test that has to
 * wait for a real clock to pass a boundary is a test nobody runs, so the clock
 * is a dependency like any other.
 */
interface ClockInterface {

	/**
	 * The current moment, in UTC.
	 *
	 * @return DateTimeImmutable
	 */
	public function now(): DateTimeImmutable;
}
