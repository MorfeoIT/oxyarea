<?php
/**
 * The access decision.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Unit;

use OxyArea\Access\AccessResolver;
use OxyArea\Access\Assignment;
use OxyArea\Access\AudienceResolver;
use OxyArea\Access\ProtectedResource;
use OxyArea\Access\Subject;
use OxyArea\Tests\Support\FixedClock;
use OxyArea\Tests\Support\InMemoryAssignmentRepository;
use OxyArea\Tests\Support\StubAudienceProvider;
use OxyArea\Tests\Support\StubManagerCheck;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OxyArea\Access\AccessResolver
 */
final class AccessResolverTest extends TestCase {

	private const ALICE = 11;
	private const BOB   = 22;
	private const CAROL = 33;
	private const ADMIN = 99;

	/**
	 * The rules.
	 *
	 * @var InMemoryAssignmentRepository
	 */
	private InMemoryAssignmentRepository $assignments;

	/**
	 * The document under discussion.
	 *
	 * @var ProtectedResource
	 */
	private ProtectedResource $document;

	protected function setUp(): void {
		$this->assignments = new InMemoryAssignmentRepository();
		$this->document    = ProtectedResource::post( 500 );
	}

	public function test_a_resource_with_no_rules_is_refused(): void {
		$resolver = $this->resolver();

		$this->assertFalse( $resolver->can_view( self::ALICE, $this->document ) );
		$this->assertSame(
			'Nothing grants access to this resource',
			$resolver->explain( self::ALICE, $this->document )->summary()
		);
	}

	public function test_a_rule_for_a_role_the_user_holds_grants_access(): void {
		$this->rules( new Assignment( Subject::role( 'customer' ) ) );

		$this->assertTrue( $this->resolver()->can_view( self::ALICE, $this->document ) );
	}

	public function test_a_rule_for_a_role_the_user_does_not_hold_grants_nothing(): void {
		$this->rules( new Assignment( Subject::role( 'agent' ) ) );

		$this->assertFalse( $this->resolver()->can_view( self::ALICE, $this->document ) );
	}

	public function test_one_matching_role_out_of_several_is_enough(): void {
		// Carol is a customer and an agent. A rule for either must reach her.
		$this->rules( new Assignment( Subject::role( 'agent' ) ) );

		$this->assertTrue( $this->resolver()->can_view( self::CAROL, $this->document ) );
	}

	public function test_an_explicit_deny_beats_an_allow_the_same_user_matches(): void {
		// The customer role may see it; Carol's other role may not. Taking access
		// away has to be reliable or it is not worth offering.
		$this->rules(
			new Assignment( Subject::role( 'customer' ) ),
			new Assignment( Subject::role( 'agent' ), Assignment::DENY )
		);

		$this->assertFalse( $this->resolver()->can_view( self::CAROL, $this->document ) );
		$this->assertTrue( $this->resolver()->can_view( self::ALICE, $this->document ) );
	}

	public function test_a_deny_written_before_the_allow_still_wins(): void {
		$this->rules(
			new Assignment( Subject::role( 'agent' ), Assignment::DENY ),
			new Assignment( Subject::role( 'customer' ) )
		);

		$this->assertFalse( $this->resolver()->can_view( self::CAROL, $this->document ) );
	}

	public function test_a_signed_out_visitor_matches_the_anonymous_rule(): void {
		$this->rules( new Assignment( Subject::anonymous() ) );

		$this->assertTrue( $this->resolver()->can_view( 0, $this->document ) );
	}

	public function test_a_signed_out_visitor_does_not_match_a_rule_for_signed_in_users(): void {
		$this->rules( new Assignment( Subject::authenticated() ) );

		$this->assertFalse( $this->resolver()->can_view( 0, $this->document ) );
	}

	public function test_a_signed_in_user_does_not_inherit_the_anonymous_rule(): void {
		// "Anonymous" is an audience, not a shorthand for everybody. A rule meant
		// to reach both says so twice.
		$this->rules( new Assignment( Subject::anonymous() ) );

		$this->assertFalse( $this->resolver()->can_view( self::ALICE, $this->document ) );
	}

	public function test_alice_cannot_reach_a_rule_written_for_bob_alone(): void {
		$this->rules( new Assignment( new Subject( Subject::USER, (string) self::BOB ) ) );

		$this->assertTrue( $this->resolver()->can_view( self::BOB, $this->document ) );
		$this->assertFalse( $this->resolver()->can_view( self::ALICE, $this->document ) );
	}

	public function test_a_rule_that_has_not_started_yet_does_not_count(): void {
		$this->rules(
			new Assignment(
				Subject::role( 'customer' ),
				Assignment::ALLOW,
				10,
				new \DateTimeImmutable( '2026-09-01 00:00:00', new \DateTimeZone( 'UTC' ) )
			)
		);

		$this->assertFalse( $this->resolver()->can_view( self::ALICE, $this->document ) );
	}

	public function test_a_rule_that_has_expired_does_not_count(): void {
		$this->rules(
			new Assignment(
				Subject::role( 'customer' ),
				Assignment::ALLOW,
				10,
				null,
				new \DateTimeImmutable( '2026-08-01 00:00:00', new \DateTimeZone( 'UTC' ) )
			)
		);

		$this->assertFalse( $this->resolver()->can_view( self::ALICE, $this->document ) );
	}

	public function test_a_rule_inside_its_window_counts(): void {
		$this->rules(
			new Assignment(
				Subject::role( 'customer' ),
				Assignment::ALLOW,
				10,
				new \DateTimeImmutable( '2026-08-01 00:00:00', new \DateTimeZone( 'UTC' ) ),
				new \DateTimeImmutable( '2026-09-01 00:00:00', new \DateTimeZone( 'UTC' ) )
			)
		);

		$this->assertTrue( $this->resolver()->can_view( self::ALICE, $this->document ) );
	}

	public function test_an_expired_deny_stops_taking_access_away(): void {
		$this->rules(
			new Assignment( Subject::role( 'customer' ) ),
			new Assignment(
				Subject::role( 'customer' ),
				Assignment::DENY,
				10,
				null,
				new \DateTimeImmutable( '2026-08-01 00:00:00', new \DateTimeZone( 'UTC' ) )
			)
		);

		$this->assertTrue( $this->resolver()->can_view( self::ALICE, $this->document ) );
	}

	public function test_somebody_who_administers_oxyarea_may_read_what_it_protects(): void {
		$this->assertTrue( $this->resolver()->can_view( self::ADMIN, $this->document ) );
	}

	public function test_a_manager_is_not_stopped_by_an_explicit_deny(): void {
		// They could delete the rule and read it anyway; pretending otherwise on
		// the front end would buy nothing.
		$this->rules( new Assignment( Subject::role( 'customer' ), Assignment::DENY ) );

		$this->assertTrue( $this->resolver()->can_view( self::ADMIN, $this->document ) );
	}

	public function test_a_signed_out_visitor_is_never_treated_as_a_manager(): void {
		$resolver = new AccessResolver(
			$this->assignments,
			$this->audience(),
			new StubManagerCheck( array( 0 ) ),
			new FixedClock()
		);

		$this->assertFalse( $resolver->can_view( 0, $this->document ) );
	}

	public function test_the_explanation_reads_in_the_order_the_checks_happened(): void {
		$this->rules( new Assignment( Subject::role( 'customer' ) ) );

		$steps = $this->resolver()->explain( self::ALICE, $this->document )->steps();

		$this->assertSame(
			array(
				'Signed in',
				'Counts as: authenticated, role:customer',
				'A rule grants access to role:customer',
			),
			array_column( $steps, 'reason' )
		);
	}

	public function test_the_explanation_of_a_refusal_names_the_rule_that_refused(): void {
		$this->rules( new Assignment( Subject::role( 'customer' ), Assignment::DENY ) );

		$this->assertSame(
			'A rule explicitly denies role:customer',
			$this->resolver()->explain( self::ALICE, $this->document )->summary()
		);
	}

	public function test_can_view_and_explain_never_disagree(): void {
		$this->rules( new Assignment( Subject::role( 'agent' ) ) );

		foreach ( array( 0, self::ALICE, self::BOB, self::CAROL, self::ADMIN ) as $user_id ) {
			$resolver = $this->resolver();

			$this->assertSame(
				$resolver->explain( $user_id, $this->document )->is_allowed(),
				$resolver->can_view( $user_id, $this->document ),
				sprintf( 'The two answers differ for user %d.', $user_id )
			);
		}
	}

	public function test_rules_on_another_resource_are_not_consulted(): void {
		$this->assignments->replace_for_resource(
			ProtectedResource::post( 501 ),
			array( new Assignment( Subject::role( 'customer' ) ) )
		);

		$this->assertFalse( $this->resolver()->can_view( self::ALICE, $this->document ) );
	}

	/**
	 * Attach rules to the document under discussion.
	 *
	 * @param Assignment ...$assignments The rules.
	 * @return void
	 */
	private function rules( Assignment ...$assignments ): void {
		$this->assignments->replace_for_resource( $this->document, array_values( $assignments ) );
	}

	/**
	 * The cast: Alice a customer, Bob a customer, Carol both customer and agent.
	 *
	 * @return AudienceResolver
	 */
	private function audience(): AudienceResolver {
		return new AudienceResolver(
			array(
				new StubAudienceProvider(
					array(
						0            => array( Subject::anonymous() ),
						self::ALICE  => array( Subject::authenticated(), Subject::role( 'customer' ) ),
						self::BOB    => array(
							Subject::authenticated(),
							Subject::role( 'customer' ),
							new Subject( Subject::USER, (string) self::BOB ),
						),
						self::CAROL  => array(
							Subject::authenticated(),
							Subject::role( 'customer' ),
							Subject::role( 'agent' ),
						),
						self::ADMIN  => array( Subject::authenticated(), Subject::role( 'administrator' ) ),
					)
				),
			)
		);
	}

	/**
	 * A resolver with the cast, the rules, and a clock stopped on 12 August 2026.
	 *
	 * @return AccessResolver
	 */
	private function resolver(): AccessResolver {
		return new AccessResolver(
			$this->assignments,
			$this->audience(),
			new StubManagerCheck( array( self::ADMIN ) ),
			new FixedClock()
		);
	}
}
