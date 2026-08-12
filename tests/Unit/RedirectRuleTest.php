<?php
/**
 * Redirect rules.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Unit;

use InvalidArgumentException;
use OxyArea\Access\Subject;
use OxyArea\Redirect\RedirectEvent;
use OxyArea\Redirect\RedirectRule;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OxyArea\Redirect\RedirectRule
 * @covers \OxyArea\Redirect\RedirectEvent
 */
final class RedirectRuleTest extends TestCase {

	public function test_an_event_nobody_has_heard_of_is_refused(): void {
		$this->expectException( InvalidArgumentException::class );

		new RedirectRule( 'after-lunch', null, '/somewhere/' );
	}

	public function test_a_rule_must_say_where_to_go(): void {
		$this->expectException( InvalidArgumentException::class );

		new RedirectRule( RedirectEvent::LOGIN, null, '   ' );
	}

	public function test_the_destination_is_trimmed(): void {
		$rule = new RedirectRule( RedirectEvent::LOGIN, null, '  /customers/  ' );

		$this->assertSame( '/customers/', $rule->destination() );
	}

	public function test_a_rule_with_no_subject_is_the_fallback(): void {
		$rule = new RedirectRule( RedirectEvent::LOGIN, null, '/shop/' );

		$this->assertTrue( $rule->is_fallback() );
		$this->assertSame( 'everybody', $rule->subject_key() );
		$this->assertSame( 0, $rule->specificity() );
	}

	public function test_a_rule_with_a_subject_is_not(): void {
		$rule = new RedirectRule( RedirectEvent::LOGIN, Subject::role( 'customer' ), '/customers/' );

		$this->assertFalse( $rule->is_fallback() );
		$this->assertSame( 'role:customer', $rule->subject_key() );
	}

	/**
	 * @dataProvider specificities
	 *
	 * @param Subject|null $subject  Who the rule is about.
	 * @param int          $expected How specific that makes it.
	 */
	public function test_specificity_runs_from_the_named_person_down_to_everybody( ?Subject $subject, int $expected ): void {
		$rule = new RedirectRule( RedirectEvent::LOGIN, $subject, '/somewhere/' );

		$this->assertSame( $expected, $rule->specificity() );
	}

	/**
	 * The ordering a site owner would guess.
	 *
	 * @return array<string, array{Subject|null, int}>
	 */
	public static function specificities(): array {
		return array(
			'a named person'       => array( new Subject( Subject::USER, '5' ), 50 ),
			'their company'        => array( new Subject( Subject::GROUP, '2' ), 40 ),
			'their role'           => array( Subject::role( 'customer' ), 30 ),
			'a capability'         => array( new Subject( Subject::CAPABILITY, 'edit_posts' ), 25 ),
			'everybody signed in'  => array( Subject::authenticated(), 20 ),
			'everybody signed out' => array( Subject::anonymous(), 20 ),
			'something PRO added'  => array( new Subject( 'invented-later', '1' ), 10 ),
			'everybody'            => array( null, 0 ),
		);
	}

	public function test_specificity_is_ordered_the_way_a_site_owner_would_guess(): void {
		$ranks = array_map(
			static fn ( array $case ): int => $case[1],
			array_values( self::specificities() )
		);

		$sorted = $ranks;
		rsort( $sorted );

		$this->assertSame( $sorted, $ranks, 'The cases are listed most specific first, and the numbers should agree.' );
	}

	public function test_a_rule_defaults_to_enabled_at_priority_ten(): void {
		$rule = new RedirectRule( RedirectEvent::LOGIN, null, '/shop/' );

		$this->assertTrue( $rule->is_enabled() );
		$this->assertSame( 10, $rule->priority() );
		$this->assertSame( 0, $rule->id() );
	}

	public function test_giving_it_an_id_changes_nothing_else(): void {
		$rule   = new RedirectRule( RedirectEvent::LOGIN, Subject::role( 'agent' ), '/agents/', 5, false );
		$stored = $rule->with_id( 12 );

		$this->assertSame( 12, $stored->id() );
		$this->assertSame( 0, $rule->id(), 'The original must not have been changed.' );
		$this->assertSame( '/agents/', $stored->destination() );
		$this->assertSame( 5, $stored->priority() );
		$this->assertFalse( $stored->is_enabled() );
	}

	public function test_the_four_events_are_the_ones_the_specification_names(): void {
		$this->assertSame(
			array( 'login', 'logout', 'registration', 'password-reset' ),
			RedirectEvent::all()
		);
	}

	public function test_an_unknown_event_does_not_exist(): void {
		$this->assertFalse( RedirectEvent::exists( 'after-lunch' ) );
		$this->assertTrue( RedirectEvent::exists( RedirectEvent::LOGIN ) );
	}
}
