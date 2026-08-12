<?php
/**
 * Settings sanitisation.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Unit;

use OxyArea\Infrastructure\Settings;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OxyArea\Infrastructure\Settings::sanitize
 * @covers \OxyArea\Infrastructure\Settings::defaults
 */
final class SettingsSanitizeTest extends TestCase {

	public function test_an_empty_input_produces_the_defaults(): void {
		$this->assertSame( Settings::defaults(), Settings::sanitize( array() ) );
	}

	public function test_deleting_data_on_uninstall_is_off_unless_asked_for(): void {
		$this->assertFalse( Settings::defaults()['delete_data_on_uninstall'] );
	}

	public function test_unknown_keys_are_discarded(): void {
		$clean = Settings::sanitize(
			array(
				'delete_data_on_uninstall' => true,
				'something_invented'       => 'payload',
			)
		);

		$this->assertArrayNotHasKey( 'something_invented', $clean );
		$this->assertTrue( $clean['delete_data_on_uninstall'] );
	}

	public function test_an_unrecognised_behaviour_falls_back_to_the_login_screen(): void {
		$clean = Settings::sanitize( array( 'restricted_behaviour' => 'let-them-in' ) );

		$this->assertSame( 'login', $clean['restricted_behaviour'] );
	}

	public function test_every_recognised_behaviour_survives(): void {
		foreach ( array( 'login', 'message', '403', '404' ) as $behaviour ) {
			$this->assertSame(
				$behaviour,
				Settings::sanitize( array( 'restricted_behaviour' => $behaviour ) )['restricted_behaviour']
			);
		}
	}

	public function test_a_boolean_setting_stays_a_boolean(): void {
		$clean = Settings::sanitize( array( 'delete_data_on_uninstall' => '1' ) );

		$this->assertIsBool( $clean['delete_data_on_uninstall'] );
		$this->assertTrue( $clean['delete_data_on_uninstall'] );
	}

	public function test_an_array_where_a_string_belongs_becomes_the_default(): void {
		$clean = Settings::sanitize( array( 'default_login_redirect' => array( 'https://evil.example' ) ) );

		$this->assertSame( '', $clean['default_login_redirect'] );
	}
}
