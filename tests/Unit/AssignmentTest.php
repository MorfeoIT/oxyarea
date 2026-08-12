<?php
/**
 * Assignments.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use OxyArea\Access\Assignment;
use OxyArea\Access\Subject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OxyArea\Access\Assignment
 */
final class AssignmentTest extends TestCase {

	public function test_an_assignment_allows_unless_told_otherwise(): void {
		$assignment = new Assignment( Subject::role( 'customer' ) );

		$this->assertFalse( $assignment->is_deny() );
		$this->assertSame( Assignment::ALLOW, $assignment->effect() );
	}

	public function test_a_deny_says_so(): void {
		$this->assertTrue( ( new Assignment( Subject::role( 'customer' ), Assignment::DENY ) )->is_deny() );
	}

	public function test_an_effect_that_is_neither_is_refused(): void {
		// Not a silent fallback to allow: a rule nobody can read must not become
		// a rule that grants something.
		$this->expectException( InvalidArgumentException::class );

		new Assignment( Subject::role( 'customer' ), 'maybe' );
	}

	public function test_a_rule_without_a_window_always_counts(): void {
		$assignment = new Assignment( Subject::role( 'customer' ) );

		$this->assertTrue( $assignment->applies_at( $this->moment( '2001-01-01 00:00:00' ) ) );
		$this->assertTrue( $assignment->applies_at( $this->moment( '2099-01-01 00:00:00' ) ) );
	}

	public function test_a_rule_does_not_count_before_it_starts(): void {
		$assignment = new Assignment(
			Subject::role( 'customer' ),
			Assignment::ALLOW,
			10,
			$this->moment( '2026-08-12 12:00:00' )
		);

		$this->assertFalse( $assignment->applies_at( $this->moment( '2026-08-12 11:59:59' ) ) );
		$this->assertTrue( $assignment->applies_at( $this->moment( '2026-08-12 12:00:00' ) ) );
	}

	public function test_a_rule_stops_counting_after_it_ends(): void {
		$assignment = new Assignment(
			Subject::role( 'customer' ),
			Assignment::ALLOW,
			10,
			null,
			$this->moment( '2026-08-12 12:00:00' )
		);

		$this->assertTrue( $assignment->applies_at( $this->moment( '2026-08-12 12:00:00' ) ) );
		$this->assertFalse( $assignment->applies_at( $this->moment( '2026-08-12 12:00:01' ) ) );
	}

	public function test_both_ends_of_a_window_are_inclusive(): void {
		$assignment = new Assignment(
			Subject::role( 'customer' ),
			Assignment::ALLOW,
			10,
			$this->moment( '2026-08-01 00:00:00' ),
			$this->moment( '2026-08-31 23:59:59' )
		);

		$this->assertTrue( $assignment->applies_at( $this->moment( '2026-08-01 00:00:00' ) ) );
		$this->assertTrue( $assignment->applies_at( $this->moment( '2026-08-31 23:59:59' ) ) );
		$this->assertFalse( $assignment->applies_at( $this->moment( '2026-09-01 00:00:00' ) ) );
	}

	public function test_a_window_that_ends_before_it_starts_never_counts(): void {
		// Corrupt data. The safe reading of a rule nobody can make sense of is
		// that it grants nothing.
		$assignment = new Assignment(
			Subject::role( 'customer' ),
			Assignment::ALLOW,
			10,
			$this->moment( '2026-09-01 00:00:00' ),
			$this->moment( '2026-08-01 00:00:00' )
		);

		$this->assertFalse( $assignment->applies_at( $this->moment( '2026-08-15 00:00:00' ) ) );
		$this->assertFalse( $assignment->applies_at( $this->moment( '2026-10-15 00:00:00' ) ) );
	}

	public function test_it_keeps_its_subject_and_priority(): void {
		$assignment = new Assignment( Subject::role( 'agent' ), Assignment::ALLOW, 5 );

		$this->assertSame( 'role:agent', $assignment->subject()->key() );
		$this->assertSame( 5, $assignment->priority() );
	}

	/**
	 * A UTC moment.
	 *
	 * @param string $when The moment.
	 * @return DateTimeImmutable
	 */
	private function moment( string $when ): DateTimeImmutable {
		return new DateTimeImmutable( $when, new DateTimeZone( 'UTC' ) );
	}
}
