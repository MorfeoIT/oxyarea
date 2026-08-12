<?php
/**
 * The capability catalogue.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Unit;

use OxyArea\Roles\Capabilities;
use OxyArea\Roles\CapabilityCatalogue;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OxyArea\Roles\CapabilityCatalogue
 */
final class CapabilityCatalogueTest extends TestCase {

	public function test_the_everyday_capabilities_are_offered(): void {
		$offered = CapabilityCatalogue::offered();

		foreach ( array( 'read', 'edit_posts', 'upload_files', 'moderate_comments' ) as $capability ) {
			$this->assertContains( $capability, $offered );
		}
	}

	public function test_oxyareas_own_capabilities_are_offered_and_are_not_dangerous(): void {
		foreach ( Capabilities::all() as $capability ) {
			$this->assertContains( $capability, CapabilityCatalogue::offered() );
			$this->assertFalse( CapabilityCatalogue::is_dangerous( $capability ) );
		}
	}

	/**
	 * @dataProvider site_ending_capabilities
	 *
	 * @param string $capability The capability.
	 */
	public function test_the_ones_that_hand_over_the_site_are_marked( string $capability ): void {
		$this->assertTrue(
			CapabilityCatalogue::is_dangerous( $capability ),
			sprintf( '%s must be treated as dangerous.', $capability )
		);
	}

	/**
	 * Capabilities that amount to owning the site.
	 *
	 * @return array<string, array{string}>
	 */
	public static function site_ending_capabilities(): array {
		return array(
			'install a plugin'   => array( 'install_plugins' ),
			'edit plugin files'  => array( 'edit_plugins' ),
			'edit theme files'   => array( 'edit_themes' ),
			'promote a user'     => array( 'promote_users' ),
			'edit users'         => array( 'edit_users' ),
			'manage options'     => array( 'manage_options' ),
			'unfiltered html'    => array( 'unfiltered_html' ),
			'unfiltered upload'  => array( 'unfiltered_upload' ),
			'update core'        => array( 'update_core' ),
		);
	}

	public function test_a_capability_nobody_has_assessed_counts_as_dangerous(): void {
		// A capability the catalogue has never heard of has never been looked at,
		// and the cautious reading of an unassessed grant is the safe one.
		$this->assertTrue( CapabilityCatalogue::is_dangerous( 'some_other_plugin_do_anything' ) );
	}

	public function test_reading_is_not_dangerous(): void {
		$this->assertFalse( CapabilityCatalogue::is_dangerous( 'read' ) );
	}

	public function test_nothing_appears_twice(): void {
		$offered = CapabilityCatalogue::offered();

		$this->assertSame( array_values( array_unique( $offered ) ), $offered );
	}

	public function test_every_group_has_something_in_it(): void {
		foreach ( CapabilityCatalogue::groups() as $group => $capabilities ) {
			$this->assertNotEmpty( $capabilities, sprintf( 'The "%s" group is empty.', $group ) );
		}
	}

	public function test_the_dangerous_group_is_exactly_the_dangerous_list(): void {
		$this->assertSame(
			CapabilityCatalogue::dangerous(),
			CapabilityCatalogue::groups()[ CapabilityCatalogue::GROUP_DANGEROUS ]
		);
	}
}
