<?php
/**
 * Conditions: what is stored, and what an unanswerable one does.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Unit\Conditions;

use OxyArea\Conditions\Context;
use OxyArea\Conditions\Specifications;
use PHPUnit\Framework\TestCase;

/**
 * The half of the conditions seam that owes nothing to WordPress.
 *
 * The encoding is read on the hottest path there is — every sign-in — and the
 * column it comes from can be edited by hand, truncated by a migration, or
 * written by a version that has since changed its mind. So what it does with
 * rubbish matters as much as what it does with a rule.
 */
final class ConditionsTest extends TestCase {

	public function test_a_rule_with_no_conditions_stores_as_nothing(): void {
		// Not "[]". A rule with no conditions has to look in the database
		// exactly like every rule written before this column existed, which is
		// what makes the migration a column addition and nothing else.
		$this->assertSame( '', Specifications::encode( array() ) );
	}

	public function test_what_is_stored_reads_back_the_same(): void {
		$conditions = array(
			array(
				'type'  => 'oxyarea_pro/first_login',
				'value' => '',
			),
			array(
				'type'  => 'oxyarea_pro/user_meta',
				'value' => 'plan=gold',
			),
		);

		$this->assertSame( $conditions, Specifications::decode( Specifications::encode( $conditions ) ) );
	}

	/**
	 * @dataProvider rubbish
	 *
	 * @param mixed $stored Something that is not a list of conditions.
	 */
	public function test_rubbish_in_the_column_reads_as_no_conditions( $stored ): void {
		$this->assertSame( array(), Specifications::decode( $stored ) );
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function rubbish(): array {
		return array(
			'null'            => array( null ),
			'empty'           => array( '' ),
			'spaces'          => array( '   ' ),
			'not json'        => array( 'first_login' ),
			'a json string'   => array( '"first_login"' ),
			'a json number'   => array( '7' ),
			'truncated'       => array( '[{"type":"first_' ),
			'an object'       => array( '{"type":"a"}' ),
			'a list of nulls' => array( '[null,null]' ),
		);
	}

	public function test_an_entry_with_no_type_is_dropped_rather_than_kept(): void {
		// A condition with no type is one nothing can judge, and keeping it
		// would make the rule never fire for a reason no screen could show.
		$decoded = Specifications::decode( '[{"value":"x"},{"type":"good","value":"y"}]' );

		$this->assertSame(
			array(
				array(
					'type'  => 'good',
					'value' => 'y',
				),
			),
			$decoded
		);
	}

	public function test_a_value_that_is_not_a_string_becomes_one(): void {
		$decoded = Specifications::decode( '[{"type":"n","value":42}]' );

		$this->assertSame( '42', $decoded[0]['value'] );
	}

	public function test_a_value_that_is_a_structure_is_dropped_not_flattened(): void {
		// Flattening would produce "Array" as a value, which would then be
		// compared against something and quietly never match.
		$decoded = Specifications::decode( '[{"type":"n","value":{"a":1}}]' );

		$this->assertSame( '', $decoded[0]['value'] );
	}

	public function test_encoding_drops_an_entry_with_no_type(): void {
		$encoded = Specifications::encode(
			array(
				array(
					'type'  => '',
					'value' => 'x',
				),
			)
		);

		$this->assertSame( '', $encoded );
	}

	// -- The context ---------------------------------------------------------

	public function test_a_context_carries_the_facts_it_was_given(): void {
		$context = new Context( 7, 'login', '/checkout/', array( 'order' => 118 ) );

		$this->assertSame( 7, $context->user_id() );
		$this->assertSame( 'login', $context->event() );
		$this->assertSame( '/checkout/', $context->requested() );
		$this->assertSame( 118, $context->get( 'order' ) );
	}

	public function test_a_fact_nobody_put_there_answers_with_the_fallback(): void {
		$context = new Context( 7 );

		$this->assertNull( $context->get( 'order' ) );
		$this->assertSame( 'none', $context->get( 'order', 'none' ) );
	}

	public function test_a_negative_user_is_a_signed_out_visitor(): void {
		$this->assertSame( 0, ( new Context( -3 ) )->user_id() );
	}

	public function test_adding_facts_makes_a_new_context_rather_than_changing_this_one(): void {
		// Immutable, so a condition cannot change what the next one is judged
		// against. A condition that could edit the context would make the order
		// they are evaluated in part of the answer, and nothing declares that
		// order.
		$original = new Context( 7, 'login', '', array( 'a' => 1 ) );

		$extended = $original->with( array( 'b' => 2 ) );

		$this->assertNull( $original->get( 'b' ) );
		$this->assertSame( 2, $extended->get( 'b' ) );
		$this->assertSame( 1, $extended->get( 'a' ) );
		$this->assertSame( 7, $extended->user_id() );
	}
}
