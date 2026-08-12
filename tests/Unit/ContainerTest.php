<?php
/**
 * The container.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Unit;

use OxyArea\Infrastructure\Container;
use OxyArea\Infrastructure\ContainerException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @covers \OxyArea\Infrastructure\Container
 */
final class ContainerTest extends TestCase {

	public function test_it_builds_a_service_on_demand(): void {
		$container = new Container();
		$built     = 0;

		$container->set(
			'thing',
			static function () use ( &$built ): stdClass {
				++$built;

				return new stdClass();
			}
		);

		$this->assertSame( 0, $built, 'Declaring a service must not build it.' );

		$container->get( 'thing' );
		$container->get( 'thing' );

		$this->assertSame( 1, $built, 'A service is built once and shared.' );
	}

	public function test_the_same_instance_comes_back_every_time(): void {
		$container = new Container();
		$container->set( 'thing', static fn (): stdClass => new stdClass() );

		$this->assertSame( $container->get( 'thing' ), $container->get( 'thing' ) );
	}

	public function test_a_factory_can_resolve_another_service(): void {
		$container = new Container();

		$container->set( 'inner', static fn (): stdClass => new stdClass() );
		$container->set(
			'outer',
			static function ( Container $c ): stdClass {
				$outer        = new stdClass();
				$outer->inner = $c->get( 'inner' );

				return $outer;
			}
		);

		$this->assertSame( $container->get( 'inner' ), $container->get( 'outer' )->inner );
	}

	public function test_an_add_on_can_replace_a_service_before_it_is_built(): void {
		$container = new Container();

		$container->set( 'thing', static fn (): stdClass => new stdClass() );

		$replacement = new stdClass();
		$container->set( 'thing', static fn (): stdClass => $replacement );

		$this->assertSame( $replacement, $container->get( 'thing' ) );
	}

	public function test_it_refuses_to_replace_a_service_already_in_use(): void {
		$container = new Container();
		$container->set( 'thing', static fn (): stdClass => new stdClass() );
		$container->get( 'thing' );

		$this->expectException( ContainerException::class );

		$container->set( 'thing', static fn (): stdClass => new stdClass() );
	}

	public function test_an_unknown_service_is_an_error_not_a_null(): void {
		$this->expectException( ContainerException::class );

		( new Container() )->get( 'nothing-declared-this' );
	}

	public function test_it_detects_a_service_that_depends_on_itself(): void {
		$container = new Container();
		$container->set( 'loop', static fn ( Container $c ): object => $c->get( 'loop' ) );

		$this->expectException( ContainerException::class );

		$container->get( 'loop' );
	}

	public function test_a_failed_resolution_does_not_poison_the_next_one(): void {
		$container = new Container();
		$container->set( 'loop', static fn ( Container $c ): object => $c->get( 'loop' ) );

		try {
			$container->get( 'loop' );
		} catch ( ContainerException $e ) {
			unset( $e );
		}

		// The resolving stack must have been unwound, so the same identifier
		// reports the cycle again rather than some stale state.
		$this->expectException( ContainerException::class );

		$container->get( 'loop' );
	}

	public function test_ids_are_reported_in_declaration_order(): void {
		$container = new Container();
		$container->set( 'first', static fn (): stdClass => new stdClass() );
		$container->set( 'second', static fn (): stdClass => new stdClass() );

		$this->assertSame( array( 'first', 'second' ), $container->ids() );
	}
}
