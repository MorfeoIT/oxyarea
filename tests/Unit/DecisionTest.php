<?php
/**
 * Access decisions.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Unit;

use OxyArea\Access\Decision;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OxyArea\Access\Decision
 */
final class DecisionTest extends TestCase {

	public function test_an_allow_is_allowed(): void {
		$this->assertTrue( Decision::allow( 'role matches' )->is_allowed() );
	}

	public function test_a_deny_is_not(): void {
		$this->assertFalse( Decision::deny( 'no rule matched' )->is_allowed() );
	}

	public function test_the_summary_is_the_reason_that_settled_it(): void {
		$decision = Decision::deny( 'no rule matched' )->with_step( true, 'signed in' );

		$this->assertSame( 'no rule matched', $decision->summary() );
	}

	public function test_earlier_steps_are_recorded_before_the_one_that_decided(): void {
		$decision = Decision::deny( 'required company: ACME' )
			->with_step( true, 'role: Customer' )
			->with_step( true, 'signed in' );

		$this->assertSame(
			array( 'signed in', 'role: Customer', 'required company: ACME' ),
			array_column( $decision->steps(), 'reason' )
		);
	}

	public function test_adding_a_step_does_not_change_the_verdict(): void {
		$decision = Decision::deny( 'no rule matched' )->with_step( true, 'signed in' );

		$this->assertFalse( $decision->is_allowed() );
	}

	public function test_a_decision_is_immutable(): void {
		$original = Decision::allow( 'role matches' );
		$derived  = $original->with_step( true, 'signed in' );

		$this->assertCount( 1, $original->steps() );
		$this->assertCount( 2, $derived->steps() );
		$this->assertNotSame( $original, $derived );
	}

	public function test_it_records_whether_each_step_passed(): void {
		$steps = Decision::deny( 'required company: ACME' )
			->with_step( true, 'signed in' )
			->steps();

		$this->assertSame( array( true, false ), array_column( $steps, 'passed' ) );
	}
}
