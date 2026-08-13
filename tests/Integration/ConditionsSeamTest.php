<?php
/**
 * A rule that only fires sometimes.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Integration;

use OxyArea\Access\Subject;
use OxyArea\Conditions\ConditionInterface;
use OxyArea\Conditions\Context;
use OxyArea\Conditions\Registry;
use OxyArea\Persistence\RedirectRuleRepository;
use OxyArea\Redirect\RedirectEvent;
use OxyArea\Redirect\RedirectResolver;
use OxyArea\Redirect\RedirectRule;
use OxyArea\Tests\Support\CastTestCase;

/**
 * The seam an add-on uses to say "only when…".
 *
 * The free plugin ships no condition types at all, so everything here registers
 * one the way OxyArea PRO will: through the filter, from outside. That is the
 * point of the tests — not that a condition works, but that a condition
 * *somebody else wrote* is stored, judged and, when it cannot be judged, refused.
 *
 * The refusal is the assertion worth the most. A rule carrying a condition whose
 * add-on has been deactivated must stop applying, not start applying to
 * everybody: the second is how deactivating a plugin silently sends every
 * customer to the page one of them was meant to see.
 */
final class ConditionsSeamTest extends CastTestCase {

	/**
	 * Where the rules live.
	 *
	 * @var RedirectRuleRepository
	 */
	private RedirectRuleRepository $rules;

	public function set_up(): void {
		parent::set_up();

		$this->rules = new RedirectRuleRepository();
	}

	public function tear_down(): void {
		remove_all_filters( 'oxyarea_conditions' );

		parent::tear_down();
	}

	// -- The seam ------------------------------------------------------------

	public function test_the_free_plugin_ships_no_conditions_of_its_own(): void {
		// Deliberate, not unfinished. Every condition anybody has asked for —
		// first sign-in, the page they wanted, a value on their account, an
		// order in a shop — belongs to the paid edition or to somebody else's
		// plugin. What the free plugin owes them is the seam.
		$this->assertSame( array(), ( new Registry() )->all() );
	}

	public function test_an_add_on_can_register_one(): void {
		$this->offer( $this->condition( 'test/always', true ) );

		$registry = new Registry();

		$this->assertTrue( $registry->knows( 'test/always' ) );
		$this->assertCount( 1, $registry->all() );
	}

	public function test_something_that_is_not_a_condition_is_dropped(): void {
		add_filter(
			'oxyarea_conditions',
			static fn (): array => array( 'not a condition', 42, null )
		);

		$this->assertSame( array(), ( new Registry() )->all() );
	}

	public function test_two_add_ons_claiming_one_type_do_not_depend_on_load_order(): void {
		// First one wins, and it is written down: last-one-wins would make the
		// answer depend on the order plugins happen to load, which nothing
		// declares and nobody can debug.
		$this->offer( $this->condition( 'test/clash', true ) );
		$this->offer( $this->condition( 'test/clash', false ) );

		$this->assertTrue(
			( new Registry() )->satisfied( array( array( 'type' => 'test/clash', 'value' => '' ) ), $this->context() )
		);
	}

	// -- Judging -------------------------------------------------------------

	public function test_a_rule_with_no_conditions_applies_as_it_always_did(): void {
		$rule = new RedirectRule( RedirectEvent::LOGIN, Subject::role( 'customer' ), '/customers/' );

		$this->assertSame( '/customers/', $this->destination_for( array( $rule ) ) );
	}

	public function test_a_condition_that_holds_lets_the_rule_through(): void {
		$this->offer( $this->condition( 'test/yes', true ) );

		$rule = $this->rule_with( array( array( 'type' => 'test/yes', 'value' => '' ) ) );

		$this->assertSame( '/special/', $this->destination_for( array( $rule ) ) );
	}

	public function test_a_condition_that_does_not_hold_keeps_it_out(): void {
		$this->offer( $this->condition( 'test/no', false ) );

		$rule = $this->rule_with( array( array( 'type' => 'test/no', 'value' => '' ) ) );

		$this->assertSame( '/fallback/', $this->destination_for( array( $rule ) ) );
	}

	public function test_every_condition_has_to_hold(): void {
		// Joined with and, not or. Somebody writing two lines in a form means
		// both, and an "or" spelled out as two rules reads better than an "and"
		// spelled out as a nested expression.
		$this->offer( $this->condition( 'test/yes', true ) );
		$this->offer( $this->condition( 'test/no', false ) );

		$rule = $this->rule_with(
			array(
				array(
					'type'  => 'test/yes',
					'value' => '',
				),
				array(
					'type'  => 'test/no',
					'value' => '',
				),
			)
		);

		$this->assertSame( '/fallback/', $this->destination_for( array( $rule ) ) );
	}

	public function test_a_condition_nobody_can_judge_stops_the_rule_rather_than_widening_it(): void {
		// The assertion this whole design turns on. Deactivate the add-on that
		// provided "only on their first sign-in" and the alternative reading —
		// unanswerable means satisfied — sends every customer where only
		// first-timers were meant to go, with nothing on any screen saying why.
		$rule = $this->rule_with( array( array( 'type' => 'gone/first_login', 'value' => '' ) ) );

		$this->assertSame(
			'/fallback/',
			$this->destination_for( array( $rule ) ),
			'A rule the site cannot judge must not apply.'
		);
	}

	public function test_the_value_stored_with_a_condition_reaches_it(): void {
		$seen = array();

		$this->offer(
			$this->condition(
				'test/records',
				true,
				static function ( string $value ) use ( &$seen ): void {
					$seen[] = $value;
				}
			)
		);

		$rule = $this->rule_with( array( array( 'type' => 'test/records', 'value' => 'plan=gold' ) ) );

		$this->destination_for( array( $rule ) );

		$this->assertSame( array( 'plan=gold' ), $seen );
	}

	public function test_a_condition_is_asked_about_the_person_the_rule_is_being_resolved_for(): void {
		// Not about whoever is making the request. An administrator previewing
		// what a customer would get is asking about somebody else entirely.
		$seen = array();

		$this->offer(
			$this->condition(
				'test/whom',
				true,
				static function ( string $value, Context $context ) use ( &$seen ): void {
					unset( $value );

					$seen[] = $context->user_id();
				}
			)
		);

		$rule = $this->rule_with( array( array( 'type' => 'test/whom', 'value' => '' ) ) );

		$this->destination_for( array( $rule ), new Context( $this->bob, RedirectEvent::LOGIN ) );

		$this->assertSame( array( $this->bob ), $seen );
	}

	// -- Storage -------------------------------------------------------------

	public function test_conditions_survive_a_round_trip_through_the_database(): void {
		$stored = $this->rules->save(
			$this->rule_with(
				array(
					array(
						'type'  => 'test/one',
						'value' => 'a',
					),
					array(
						'type'  => 'test/two',
						'value' => '',
					),
				)
			)
		);

		$read = null;

		foreach ( ( new RedirectRuleRepository() )->for_event( RedirectEvent::LOGIN ) as $candidate ) {
			if ( $candidate->id() === $stored->id() ) {
				$read = $candidate;
			}
		}

		$this->assertNotNull( $read );
		$this->assertCount( 2, $read->conditions() );
		$this->assertSame( 'test/one', $read->conditions()[0]['type'] );
		$this->assertSame( 'a', $read->conditions()[0]['value'] );
	}

	public function test_a_rule_saved_without_conditions_reads_back_without_any(): void {
		// The compatibility assertion: every rule written before this column
		// existed has to behave exactly as it did.
		$stored = $this->rules->save( new RedirectRule( RedirectEvent::LOGIN, null, '/home/' ) );

		foreach ( ( new RedirectRuleRepository() )->for_event( RedirectEvent::LOGIN ) as $candidate ) {
			if ( $candidate->id() === $stored->id() ) {
				$this->assertSame( array(), $candidate->conditions() );

				return;
			}
		}

		$this->fail( 'The rule was not stored.' );
	}

	public function test_the_column_exists_after_the_migration(): void {
		global $wpdb;

		$table = \OxyArea\Infrastructure\Migrator::table( \OxyArea\Infrastructure\Migrator::TABLE_REDIRECT_RULES );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name from a constant; no user input.
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );

		$this->assertContains( 'conditions', $columns );
	}

	// -- Helpers -------------------------------------------------------------

	/**
	 * A rule about customers with the given conditions on it.
	 *
	 * @param list<array{type: string, value: string}> $conditions The conditions.
	 * @return RedirectRule
	 */
	private function rule_with( array $conditions ): RedirectRule {
		return new RedirectRule( RedirectEvent::LOGIN, Subject::role( 'customer' ), '/special/', 10, true, 0, $conditions );
	}

	/**
	 * Where Alice would be sent, given these rules.
	 *
	 * @param list<RedirectRule> $rules   The rules.
	 * @param Context|null       $context The facts, when a test wants its own.
	 * @return string
	 */
	private function destination_for( array $rules, ?Context $context = null ): string {
		$resolver = new RedirectResolver( new Registry() );

		return $resolver->resolve(
			RedirectEvent::LOGIN,
			array( Subject::authenticated(), Subject::role( 'customer' ) ),
			$rules,
			'/fallback/',
			$context ?? $this->context()
		)->destination();
	}

	/**
	 * A context for Alice signing in.
	 *
	 * @return Context
	 */
	private function context(): Context {
		return new Context( $this->alice, RedirectEvent::LOGIN, '/wanted/' );
	}

	/**
	 * Register a condition, the way an add-on does.
	 *
	 * @param ConditionInterface $condition The condition.
	 * @return void
	 */
	private function offer( ConditionInterface $condition ): void {
		add_filter(
			'oxyarea_conditions',
			static function ( $conditions ) use ( $condition ): array {
				$conditions   = is_array( $conditions ) ? $conditions : array();
				$conditions[] = $condition;

				return $conditions;
			}
		);
	}

	/**
	 * A condition that answers what it is told to, and reports what it saw.
	 *
	 * @param string        $type    Its type.
	 * @param bool          $answer  What it answers.
	 * @param callable|null $witness Called with the value and the context.
	 * @return ConditionInterface
	 */
	private function condition( string $type, bool $answer, ?callable $witness = null ): ConditionInterface {
		return new class( $type, $answer, $witness ) implements ConditionInterface {

			/**
			 * @param string        $type    Its type.
			 * @param bool          $answer  What it answers.
			 * @param callable|null $witness Called with the value and the context.
			 */
			public function __construct(
				private string $type,
				private bool $answer,
				private $witness
			) {
			}

			public function type(): string {
				return $this->type;
			}

			public function label(): string {
				return 'A condition for a test';
			}

			public function matches( string $value, Context $context ): bool {
				if ( null !== $this->witness ) {
					( $this->witness )( $value, $context );
				}

				return $this->answer;
			}
		};
	}
}
