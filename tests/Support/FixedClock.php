<?php
/**
 * A clock that does not move.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use OxyArea\Infrastructure\ClockInterface;

/**
 * Reports whatever moment it was built with.
 */
final class FixedClock implements ClockInterface {

	/**
	 * The moment.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $now;

	/**
	 * Build the clock.
	 *
	 * @param string $now A datetime string, read as UTC.
	 */
	public function __construct( string $now = '2026-08-12 12:00:00' ) {
		$this->now = new DateTimeImmutable( $now, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * The current moment, in UTC.
	 *
	 * @return DateTimeImmutable
	 */
	public function now(): DateTimeImmutable {
		return $this->now;
	}
}
