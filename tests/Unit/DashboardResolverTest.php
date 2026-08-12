<?php
/**
 * Which dashboard somebody gets.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Unit;

use InvalidArgumentException;
use OxyArea\Access\Subject;
use OxyArea\Dashboard\Dashboard;
use OxyArea\Dashboard\DashboardResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OxyArea\Dashboard\DashboardResolver
 * @covers \OxyArea\Dashboard\Dashboard
 */
final class DashboardResolverTest extends TestCase {

	/**
	 * The resolver.
	 *
	 * @var DashboardResolver
	 */
	private DashboardResolver $resolver;

	protected function setUp(): void {
		$this->resolver = new DashboardResolver();
	}

	public function test_a_dashboard_needs_a_real_post_identifier(): void {
		$this->expectException( InvalidArgumentException::class );

		new Dashboard( 0, 'Nowhere' );
	}

	public function test_with_nothing_defined_nobody_gets_anything(): void {
		$this->assertNull( $this->resolver->resolve( $this->customer(), array() ) );
	}

	public function test_a_role_dashboard_goes_to_that_role(): void {
		$customers = new Dashboard( 10, 'Customers', Subject::role( 'customer' ) );

		$this->assertSame( $customers, $this->resolver->resolve( $this->customer(), array( $customers ) ) );
	}

	public function test_a_role_dashboard_does_not_go_to_another_role(): void {
		$agents = new Dashboard( 10, 'Agents', Subject::role( 'agent' ) );

		$this->assertNull( $this->resolver->resolve( $this->customer(), array( $agents ) ) );
	}

	public function test_the_default_serves_whoever_has_nothing_of_their_own(): void {
		$default = new Dashboard( 5, 'Everybody' );

		$this->assertSame( $default, $this->resolver->resolve( $this->customer(), array( $default ) ) );
	}

	public function test_a_role_dashboard_beats_the_default(): void {
		$default   = new Dashboard( 5, 'Everybody' );
		$customers = new Dashboard( 10, 'Customers', Subject::role( 'customer' ) );

		$this->assertSame(
			$customers,
			$this->resolver->resolve( $this->customer(), array( $default, $customers ) )
		);
	}

	public function test_the_order_they_arrive_in_changes_nothing(): void {
		$default   = new Dashboard( 5, 'Everybody' );
		$customers = new Dashboard( 10, 'Customers', Subject::role( 'customer' ) );

		$this->assertSame(
			$this->resolver->resolve( $this->customer(), array( $default, $customers ) ),
			$this->resolver->resolve( $this->customer(), array( $customers, $default ) )
		);
	}

	public function test_a_signed_out_visitor_does_not_get_the_default(): void {
		// Serving the default to somebody who is not signed in would put whatever
		// a site owner wrote for their customers on the open internet.
		$default = new Dashboard( 5, 'Everybody' );

		$this->assertNull(
			$this->resolver->resolve( array( Subject::anonymous() ), array( $default ) )
		);
	}

	public function test_a_signed_out_visitor_gets_nothing_at_all(): void {
		$default   = new Dashboard( 5, 'Everybody' );
		$customers = new Dashboard( 10, 'Customers', Subject::role( 'customer' ) );

		$this->assertNull(
			$this->resolver->resolve( array( Subject::anonymous() ), array( $default, $customers ) )
		);
	}

	public function test_two_dashboards_for_the_same_role_resolve_to_the_older_one(): void {
		// Two dashboards for one role is a mistake being made, not a preference
		// being expressed. There is no tie-break field to make it configurable;
		// the answer is simply stable, so the mistake is visible and repeatable.
		$first  = new Dashboard( 10, 'First', Subject::role( 'customer' ) );
		$second = new Dashboard( 20, 'Second', Subject::role( 'customer' ) );

		$this->assertSame(
			$first,
			$this->resolver->resolve( $this->customer(), array( $second, $first ) )
		);
	}

	public function test_somebody_with_two_roles_gets_the_older_of_their_dashboards(): void {
		$customers = new Dashboard( 10, 'Customers', Subject::role( 'customer' ) );
		$agents    = new Dashboard( 20, 'Agents', Subject::role( 'agent' ) );

		$this->assertSame(
			$customers,
			$this->resolver->resolve( $this->both(), array( $agents, $customers ) )
		);
	}

	public function test_rubbish_in_the_list_is_stepped_over(): void {
		$customers = new Dashboard( 10, 'Customers', Subject::role( 'customer' ) );

		$this->assertSame(
			$customers,
			$this->resolver->resolve( $this->customer(), array( 'not a dashboard', null, $customers ) )
		);
	}

	public function test_the_candidates_come_back_best_first(): void {
		$default   = new Dashboard( 5, 'Everybody' );
		$customers = new Dashboard( 10, 'Customers', Subject::role( 'customer' ) );

		$titles = array_map(
			static fn ( Dashboard $dashboard ): string => $dashboard->title(),
			$this->resolver->candidates( $this->customer(), array( $default, $customers ) )
		);

		$this->assertSame( array( 'Customers', 'Everybody' ), $titles );
	}

	public function test_a_dashboard_knows_whether_it_is_the_default(): void {
		$this->assertTrue( ( new Dashboard( 5, 'Everybody' ) )->is_default() );
		$this->assertSame( 'default', ( new Dashboard( 5, 'Everybody' ) )->subject_key() );

		$customers = new Dashboard( 10, 'Customers', Subject::role( 'customer' ) );

		$this->assertFalse( $customers->is_default() );
		$this->assertSame( 'role:customer', $customers->subject_key() );
	}

	public function test_specificity_matches_the_redirect_engines_ladder(): void {
		// The two screens have to agree, or a site owner learns one rule and then
		// finds it does not hold in the other place.
		$this->assertSame( 0, ( new Dashboard( 1, 'x' ) )->specificity() );
		$this->assertSame( 20, ( new Dashboard( 1, 'x', Subject::authenticated() ) )->specificity() );
		$this->assertSame( 30, ( new Dashboard( 1, 'x', Subject::role( 'customer' ) ) )->specificity() );
		$this->assertSame( 40, ( new Dashboard( 1, 'x', new Subject( Subject::GROUP, '1' ) ) )->specificity() );
		$this->assertSame( 50, ( new Dashboard( 1, 'x', new Subject( Subject::USER, '1' ) ) )->specificity() );
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
