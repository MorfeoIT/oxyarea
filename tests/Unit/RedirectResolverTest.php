<?php
/**
 * Which redirect rule wins, and why.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Unit;

use OxyArea\Access\Subject;
use OxyArea\Redirect\RedirectEvent;
use OxyArea\Redirect\RedirectResolver;
use OxyArea\Redirect\RedirectRule;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OxyArea\Redirect\RedirectResolver
 * @covers \OxyArea\Redirect\Resolution
 */
final class RedirectResolverTest extends TestCase {

	private const FALLBACK = '/';

	/**
	 * The engine.
	 *
	 * @var RedirectResolver
	 */
	private RedirectResolver $resolver;

	protected function setUp(): void {
		$this->resolver = new RedirectResolver();
	}

	public function test_with_no_rules_at_all_the_fallback_is_used(): void {
		$resolution = $this->resolve( array(), $this->customer() );

		$this->assertSame( self::FALLBACK, $resolution->destination() );
		$this->assertFalse( $resolution->was_decided_by_a_rule() );
	}

	public function test_a_rule_for_a_role_the_person_holds_is_used(): void {
		$rules = array( $this->rule( Subject::role( 'customer' ), '/customers/' ) );

		$this->assertSame( '/customers/', $this->resolve( $rules, $this->customer() )->destination() );
	}

	public function test_a_rule_for_a_role_they_do_not_hold_is_not(): void {
		$rules = array( $this->rule( Subject::role( 'agent' ), '/agents/' ) );

		$this->assertSame( self::FALLBACK, $this->resolve( $rules, $this->customer() )->destination() );
	}

	public function test_a_rule_about_a_role_beats_one_about_everybody_signed_in(): void {
		$rules = array(
			$this->rule( Subject::authenticated(), '/members/' ),
			$this->rule( Subject::role( 'customer' ), '/customers/' ),
		);

		$this->assertSame( '/customers/', $this->resolve( $rules, $this->customer() )->destination() );
	}

	public function test_a_rule_about_a_role_beats_the_events_fallback_rule(): void {
		$rules = array(
			$this->rule( null, '/shop/' ),
			$this->rule( Subject::role( 'customer' ), '/customers/' ),
		);

		$this->assertSame( '/customers/', $this->resolve( $rules, $this->customer() )->destination() );
	}

	public function test_specificity_wins_even_when_the_vaguer_rule_has_the_better_priority(): void {
		// The point of putting specificity first: somebody who writes "agents go
		// to the agent dashboard" alongside "everybody goes to the shop" gets what
		// they plainly meant, without having to discover the priority field.
		$rules = array(
			$this->rule( null, '/shop/', 1 ),
			$this->rule( Subject::role( 'customer' ), '/customers/', 99 ),
		);

		$this->assertSame( '/customers/', $this->resolve( $rules, $this->customer() )->destination() );
	}

	public function test_when_somebody_holds_two_roles_priority_settles_it(): void {
		// Carol is a customer and an agent, and there is a rule for each. This is
		// the ordinary conflict, and the field exists precisely for it.
		$rules = array(
			$this->rule( Subject::role( 'customer' ), '/customers/', 20 ),
			$this->rule( Subject::role( 'agent' ), '/agents/', 10 ),
		);

		$this->assertSame( '/agents/', $this->resolve( $rules, $this->both() )->destination() );
	}

	public function test_a_lower_priority_number_wins_like_a_wordpress_hook(): void {
		$rules = array(
			$this->rule( Subject::role( 'customer' ), '/late/', 50 ),
			$this->rule( Subject::role( 'agent' ), '/early/', 5 ),
		);

		$this->assertSame( '/early/', $this->resolve( $rules, $this->both() )->destination() );
	}

	public function test_two_rules_alike_in_every_way_are_settled_by_age(): void {
		// Nothing here says the older rule deserves to win. It says that something
		// has to decide, and a tie broken by whatever order the database felt like
		// returning is a bug that shows up once a fortnight and never reproduces.
		$rules = array(
			$this->rule( Subject::role( 'agent' ), '/second/', 10, true, 7 ),
			$this->rule( Subject::role( 'customer' ), '/first/', 10, true, 3 ),
		);

		$this->assertSame( '/first/', $this->resolve( $rules, $this->both() )->destination() );
	}

	public function test_the_order_the_rules_arrive_in_changes_nothing(): void {
		$a = $this->rule( Subject::role( 'agent' ), '/second/', 10, true, 7 );
		$b = $this->rule( Subject::role( 'customer' ), '/first/', 10, true, 3 );

		$this->assertSame(
			$this->resolve( array( $a, $b ), $this->both() )->destination(),
			$this->resolve( array( $b, $a ), $this->both() )->destination()
		);
	}

	public function test_a_disabled_rule_is_not_considered(): void {
		$rules = array(
			$this->rule( Subject::role( 'customer' ), '/customers/', 10, false ),
			$this->rule( null, '/shop/' ),
		);

		$this->assertSame( '/shop/', $this->resolve( $rules, $this->customer() )->destination() );
	}

	public function test_a_rule_for_another_moment_is_not_considered(): void {
		$rules = array(
			new RedirectRule( RedirectEvent::LOGOUT, Subject::role( 'customer' ), '/goodbye/' ),
		);

		$this->assertSame( self::FALLBACK, $this->resolve( $rules, $this->customer() )->destination() );
	}

	public function test_a_signed_out_visitor_matches_the_anonymous_rule(): void {
		$rules = array( $this->rule( Subject::anonymous(), '/welcome/' ) );

		$this->assertSame(
			'/welcome/',
			$this->resolve( $rules, array( Subject::anonymous() ) )->destination()
		);
	}

	public function test_a_subject_type_from_an_add_on_outranks_the_fallback(): void {
		// PRO introduces subject types this plugin has never heard of. They should
		// beat "everybody" without the free plugin needing to know what they are.
		$rules = array(
			$this->rule( null, '/shop/', 1 ),
			$this->rule( new Subject( 'something-pro-invented', '42' ), '/theirs/', 99 ),
		);

		$subjects = array( Subject::authenticated(), new Subject( 'something-pro-invented', '42' ) );

		$this->assertSame( '/theirs/', $this->resolve( $rules, $subjects )->destination() );
	}

	public function test_rubbish_in_the_rule_list_is_stepped_over(): void {
		$rules = array(
			'not a rule',
			null,
			$this->rule( Subject::role( 'customer' ), '/customers/' ),
		);

		$this->assertSame( '/customers/', $this->resolve( $rules, $this->customer() )->destination() );
	}

	public function test_the_resolution_names_the_rule_that_decided(): void {
		$winner     = $this->rule( Subject::role( 'customer' ), '/customers/' );
		$resolution = $this->resolve( array( $winner ), $this->customer() );

		$this->assertTrue( $resolution->was_decided_by_a_rule() );
		$this->assertSame( $winner, $resolution->rule() );
	}

	public function test_a_single_match_is_not_a_contest(): void {
		$resolution = $this->resolve(
			array( $this->rule( Subject::role( 'customer' ), '/customers/' ) ),
			$this->customer()
		);

		$this->assertFalse( $resolution->was_contested() );
	}

	public function test_two_matches_are(): void {
		$rules = array(
			$this->rule( Subject::role( 'customer' ), '/customers/', 20 ),
			$this->rule( Subject::role( 'agent' ), '/agents/', 10 ),
		);

		$resolution = $this->resolve( $rules, $this->both() );

		$this->assertTrue( $resolution->was_contested() );
		$this->assertCount( 2, $resolution->candidates() );
	}

	public function test_the_candidates_come_back_best_first(): void {
		$rules = array(
			$this->rule( null, '/shop/' ),
			$this->rule( Subject::authenticated(), '/members/' ),
			$this->rule( Subject::role( 'customer' ), '/customers/' ),
		);

		$destinations = array_map(
			static fn ( RedirectRule $rule ): string => $rule->destination(),
			$this->resolve( $rules, $this->customer() )->candidates()
		);

		$this->assertSame( array( '/customers/', '/members/', '/shop/' ), $destinations );
	}

	/**
	 * Resolve a sign-in.
	 *
	 * @param array<int, mixed> $rules    The rules.
	 * @param list<Subject>     $subjects What the person counts as.
	 * @return \OxyArea\Redirect\Resolution
	 */
	private function resolve( array $rules, array $subjects ) {
		return $this->resolver->resolve( RedirectEvent::LOGIN, $subjects, $rules, self::FALLBACK );
	}

	/**
	 * A sign-in rule.
	 *
	 * @param Subject|null $subject     Who it is about.
	 * @param string       $destination Where they land.
	 * @param int          $priority    Tie-breaker.
	 * @param bool         $enabled     Whether it counts.
	 * @param int          $id          Identifier.
	 * @return RedirectRule
	 */
	private function rule( ?Subject $subject, string $destination, int $priority = 10, bool $enabled = true, int $id = 0 ): RedirectRule {
		return new RedirectRule( RedirectEvent::LOGIN, $subject, $destination, $priority, $enabled, $id );
	}

	/**
	 * Alice: a customer.
	 *
	 * @return list<Subject>
	 */
	private function customer(): array {
		return array( Subject::authenticated(), Subject::role( 'customer' ) );
	}

	/**
	 * Carol: a customer and an agent.
	 *
	 * @return list<Subject>
	 */
	private function both(): array {
		return array( Subject::authenticated(), Subject::role( 'customer' ), Subject::role( 'agent' ) );
	}
}
