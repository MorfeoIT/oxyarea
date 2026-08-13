<?php
/**
 * A rule that stops on a date has to still stop on a date after being saved.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use OxyArea\Access\Assignment;
use OxyArea\Access\ProtectedResource;
use OxyArea\Access\Subject;
use OxyArea\Persistence\AssignmentRepository;
use OxyArea\Tests\Support\CastTestCase;

/**
 * The round trip nobody had ever asked for.
 *
 * Assignments have carried a window since the first sprint. The value object
 * validates it, the resolver filters on it, the repository reads it back out of
 * the database — and the repository's *write* path put null in both columns,
 * because the value object had no way to be asked. Every expiry a caller set was
 * silently discarded.
 *
 * Nothing caught it because nothing in the free plugin sets a window: the
 * restriction box has no date field, and every test built assignments without
 * one. The first caller that did was OxyArea PRO's file vault, where "this
 * client may read the contract until the end of the month" is the feature.
 *
 * So this file exists to make the round trip a thing the suite checks, rather
 * than a thing three separate layers each assume the others are doing.
 */
final class AssignmentWindowTest extends CastTestCase {

	/**
	 * The page under test.
	 *
	 * @var int
	 */
	private int $page;

	/**
	 * Where the rules live.
	 *
	 * @var AssignmentRepository
	 */
	private AssignmentRepository $repository;

	public function set_up(): void {
		parent::set_up();

		$this->page       = self::factory()->post->create();
		$this->repository = new AssignmentRepository();
	}

	public function test_an_end_date_survives_being_stored(): void {
		$ends = new DateTimeImmutable( '2030-06-30 23:59:59', new DateTimeZone( 'UTC' ) );

		$this->store( new Assignment( Subject::role( 'customer' ), Assignment::ALLOW, 10, null, $ends ) );

		$stored = $this->stored();

		$this->assertNotNull( $stored->ends_at() );
		$this->assertSame( '2030-06-30 23:59:59', $stored->ends_at()->format( 'Y-m-d H:i:s' ) );
	}

	public function test_a_start_date_survives_being_stored(): void {
		$starts = new DateTimeImmutable( '2030-01-01 00:00:00', new DateTimeZone( 'UTC' ) );

		$this->store( new Assignment( Subject::role( 'customer' ), Assignment::ALLOW, 10, $starts, null ) );

		$stored = $this->stored();

		$this->assertNotNull( $stored->starts_at() );
		$this->assertSame( '2030-01-01 00:00:00', $stored->starts_at()->format( 'Y-m-d H:i:s' ) );
	}

	public function test_a_rule_with_no_window_still_has_none(): void {
		$this->store( new Assignment( Subject::role( 'customer' ) ) );

		$stored = $this->stored();

		$this->assertNull( $stored->starts_at() );
		$this->assertNull( $stored->ends_at() );
	}

	public function test_a_rule_that_has_expired_no_longer_grants_anything(): void {
		// The assertion that would have caught it. Before the fix this passed
		// only because the expiry was thrown away, which is the opposite of
		// passing.
		$past = new DateTimeImmutable( '2020-01-01 00:00:00', new DateTimeZone( 'UTC' ) );

		$this->store( new Assignment( Subject::role( 'customer' ), Assignment::ALLOW, 10, null, $past ) );

		$this->assertFalse(
			$this->resolver()->can_view( $this->alice, ProtectedResource::post( $this->page ) ),
			'A rule whose window has closed must grant nothing.'
		);
	}

	public function test_a_rule_that_has_not_started_grants_nothing_yet(): void {
		$future = new DateTimeImmutable( '2099-01-01 00:00:00', new DateTimeZone( 'UTC' ) );

		$this->store( new Assignment( Subject::role( 'customer' ), Assignment::ALLOW, 10, $future, null ) );

		$this->assertFalse( $this->resolver()->can_view( $this->alice, ProtectedResource::post( $this->page ) ) );
	}

	public function test_a_rule_inside_its_window_grants_normally(): void {
		$this->store(
			new Assignment(
				Subject::role( 'customer' ),
				Assignment::ALLOW,
				10,
				new DateTimeImmutable( '2020-01-01 00:00:00', new DateTimeZone( 'UTC' ) ),
				new DateTimeImmutable( '2099-01-01 00:00:00', new DateTimeZone( 'UTC' ) )
			)
		);

		$this->assertTrue( $this->resolver()->can_view( $this->alice, ProtectedResource::post( $this->page ) ) );
		$this->assertFalse( $this->resolver()->can_view( $this->carol, ProtectedResource::post( $this->page ) ) );
	}

	public function test_a_moment_in_another_timezone_is_stored_as_the_same_moment(): void {
		// A site set to Europe/Rome and a rule built in UTC must agree about
		// when the rule ends, or "expires at midnight" means one thing to the
		// person who typed it and another to the query that enforces it.
		$rome = new DateTimeImmutable( '2030-06-30 02:00:00', new DateTimeZone( 'Europe/Rome' ) );

		$this->store( new Assignment( Subject::role( 'customer' ), Assignment::ALLOW, 10, null, $rome ) );

		$stored = $this->stored();

		$this->assertNotNull( $stored->ends_at() );
		$this->assertSame( $rome->getTimestamp(), $stored->ends_at()->getTimestamp() );
	}

	/**
	 * Put one rule on the page.
	 *
	 * @param Assignment $assignment The rule.
	 * @return void
	 */
	private function store( Assignment $assignment ): void {
		$this->repository->replace_for_resource(
			ProtectedResource::post( $this->page ),
			array( $assignment )
		);
	}

	/**
	 * Read it back, through a fresh repository so nothing is answered from memory.
	 *
	 * @return Assignment
	 */
	private function stored(): Assignment {
		$assignments = ( new AssignmentRepository() )->for_resource( ProtectedResource::post( $this->page ) );

		$this->assertCount( 1, $assignments );

		return $assignments[0];
	}
}
