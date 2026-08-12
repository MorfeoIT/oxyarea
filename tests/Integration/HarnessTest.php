<?php
/**
 * That the harness itself is real.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Integration;

use OxyArea\Infrastructure\Migrator;
use OxyArea\Roles\Capabilities;
use WP_UnitTestCase;

/**
 * The first tests to write and the least interesting to read.
 *
 * Everything after this assumes WordPress is loaded, the plugin is active, the
 * schema is there and the capabilities are granted. If that assumption is
 * wrong, a hundred later tests fail in a hundred confusing ways; here they fail
 * in one obvious one.
 */
final class HarnessTest extends WP_UnitTestCase {

	public function test_wordpress_is_loaded(): void {
		$this->assertTrue( defined( 'ABSPATH' ) );
		$this->assertTrue( function_exists( 'wp_insert_post' ) );
	}

	public function test_the_plugin_is_loaded(): void {
		$this->assertTrue( class_exists( \OxyArea\Plugin::class ) );
		$this->assertTrue( defined( 'OxyArea\VERSION' ) );
	}

	public function test_it_started_rather_than_merely_being_included(): void {
		// oxyarea_init fires at the end of boot. If the file were included but the
		// plugin never started, every class would exist and nothing would work.
		$this->assertGreaterThan( 0, did_action( 'oxyarea_init' ) );
	}

	public function test_the_schema_is_there(): void {
		global $wpdb;

		foreach ( array( Migrator::TABLE_ASSIGNMENTS, Migrator::TABLE_REDIRECT_RULES ) as $table ) {
			$name = Migrator::table( $table );

			$this->assertSame(
				$name,
				$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ),
				sprintf( 'The %s table should exist.', $name )
			);
		}
	}

	public function test_the_administrator_can_administer_the_plugin(): void {
		$administrator = get_role( 'administrator' );

		$this->assertNotNull( $administrator );

		foreach ( Capabilities::all() as $capability ) {
			$this->assertTrue(
				$administrator->has_cap( $capability ),
				sprintf( 'An administrator should hold %s.', $capability )
			);
		}
	}

	public function test_the_dashboard_post_type_is_registered(): void {
		$this->assertTrue( post_type_exists( 'oxyarea_dashboard' ) );
	}

	public function test_each_test_starts_from_a_clean_database(): void {
		// The suite wraps every test in a transaction and rolls it back. Proving it
		// here means later tests can create roles and posts without tidying up,
		// which is most of what makes them readable.
		$this->assertSame( 0, (int) wp_count_posts( 'oxyarea_dashboard' )->publish );

		self::factory()->post->create( array( 'post_type' => 'oxyarea_dashboard' ) );

		$this->assertSame( 1, (int) wp_count_posts( 'oxyarea_dashboard' )->publish );
	}

	public function test_and_the_next_one_does_too(): void {
		$this->assertSame( 0, (int) wp_count_posts( 'oxyarea_dashboard' )->publish );
	}
}
