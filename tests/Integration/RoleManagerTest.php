<?php
/**
 * The role editor's refusals, against real WordPress roles.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Integration;

use OxyArea\Roles\ManagedRoles;
use OxyArea\Roles\RoleException;
use OxyArea\Tests\Support\CastTestCase;

/**
 * What the role manager will not do.
 *
 * Every one of these is a way a role editor can ruin a site, and none of them
 * can be checked without WordPress: they are all about what `get_role`,
 * `user_can` and the roles option actually contain.
 */
final class RoleManagerTest extends CastTestCase {

	public function test_it_creates_a_role_and_remembers_that_it_did(): void {
		$slug = $this->roles->create( 'Test Customer', 'oxytest_customer', array( 'read' ), $this->admin );

		$this->assertNotNull( get_role( $slug ) );
		$this->assertTrue( ( new ManagedRoles() )->contains( $slug ) );
	}

	public function test_every_new_role_can_read(): void {
		// Without `read` its holders cannot reach the admin bar, let alone a
		// private area.
		$slug = $this->roles->create( 'Bare', 'oxytest_bare', array(), $this->admin );

		$this->assertTrue( get_role( $slug )->has_cap( 'read' ) );
	}

	public function test_it_will_not_touch_the_administrator_role(): void {
		$this->expectException( RoleException::class );

		$this->roles->update_capabilities( 'administrator', array( 'read' ), $this->admin );
	}

	public function test_it_will_not_delete_a_role_it_did_not_create(): void {
		$this->expectException( RoleException::class );

		$this->roles->delete( 'editor', 'subscriber', $this->admin );
	}

	public function test_it_will_not_create_a_role_that_already_exists(): void {
		$this->roles->create( 'Twice', 'oxytest_twice', array(), $this->admin );

		$this->expectException( RoleException::class );

		$this->roles->create( 'Twice', 'oxytest_twice', array(), $this->admin );
	}

	public function test_it_will_not_move_people_to_a_role_that_does_not_exist(): void {
		$slug = $this->roles->create( 'Doomed', 'oxytest_doomed', array(), $this->admin );

		$this->expectException( RoleException::class );

		$this->roles->delete( $slug, 'no_such_role', $this->admin );
	}

	public function test_a_name_with_nothing_usable_in_it_is_refused(): void {
		$this->expectException( RoleException::class );

		$this->roles->create( '???', '', array(), $this->admin );
	}

	/**
	 * @dataProvider site_ending_capabilities
	 *
	 * @param string $capability Something that hands over the site.
	 */
	public function test_somebody_cannot_grant_what_they_do_not_hold( string $capability ): void {
		// The escalation guard. Without it, an editor who has been given the role
		// screen mints a role with install_plugins, assigns it to themselves, and
		// owns the site by lunchtime.
		$slug = $this->roles->create( 'Escalation', 'oxytest_esc_' . substr( md5( $capability ), 0, 6 ), array( $capability ), $this->alice );

		$this->assertFalse(
			get_role( $slug )->has_cap( $capability ),
			sprintf( 'A subscriber should not be able to grant %s.', $capability )
		);
	}

	/**
	 * Capabilities that amount to owning the site.
	 *
	 * @return array<string, array{string}>
	 */
	public static function site_ending_capabilities(): array {
		return array(
			'install a plugin' => array( 'install_plugins' ),
			'edit users'       => array( 'edit_users' ),
			'manage options'   => array( 'manage_options' ),
			'edit files'       => array( 'edit_files' ),
		);
	}

	public function test_cloning_is_not_a_way_around_the_guard(): void {
		$slug = $this->roles->clone_role( 'administrator', 'Copy of admin', 'oxytest_copy', $this->alice );

		$this->assertFalse( get_role( $slug )->has_cap( 'install_plugins' ) );
		$this->assertFalse( get_role( $slug )->has_cap( 'manage_options' ) );
	}

	public function test_capabilities_from_other_plugins_survive_a_save(): void {
		$slug = $this->roles->create( 'Shop', 'oxytest_shop', array( 'read', 'upload_files' ), $this->admin );

		get_role( $slug )->add_cap( 'woocommerce_view_order' );

		$this->roles->update_capabilities( $slug, array( 'read' ), $this->admin );

		$this->assertTrue(
			get_role( $slug )->has_cap( 'woocommerce_view_order' ),
			'A capability outside the catalogue must not be removed by saving.'
		);
		$this->assertFalse(
			get_role( $slug )->has_cap( 'upload_files' ),
			'A capability inside the catalogue must be removed when it is unticked.'
		);
	}

	public function test_deleting_a_role_moves_its_people_rather_than_stranding_them(): void {
		$slug = $this->roles->create( 'Temporary', 'oxytest_temp', array(), $this->admin );

		$this->roles->assign_user( $this->bob, $slug, $this->admin );

		$moved = $this->roles->delete( $slug, 'subscriber', $this->admin );

		$this->assertSame( 1, $moved );
		$this->assertNull( get_role( $slug ) );

		$bob = get_userdata( $this->bob );

		$this->assertContains( 'subscriber', (array) $bob->roles );
		$this->assertNotSame( array(), (array) $bob->roles, 'Nobody should be left with no role at all.' );
	}

	public function test_the_last_administrator_cannot_be_demoted(): void {
		// The condition has to be built rather than assumed: the WordPress test
		// installer creates an administrator of its own, so a fresh install starts
		// with two and the guard would have nothing to refuse.
		$this->leave_one_administrator();

		$this->assertCount( 1, get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) ) );

		$this->expectException( RoleException::class );

		$this->roles->assign_user( $this->admin, 'subscriber', $this->admin );
	}

	public function test_the_last_administrator_keeps_the_role_after_the_refusal(): void {
		$this->leave_one_administrator();

		try {
			$this->roles->assign_user( $this->admin, 'subscriber', $this->admin );
		} catch ( RoleException $e ) {
			unset( $e );
		}

		$this->assertContains( 'administrator', (array) get_userdata( $this->admin )->roles );
	}

	/**
	 * Demote every administrator but ours.
	 *
	 * @return void
	 */
	private function leave_one_administrator(): void {
		foreach ( get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) ) as $id ) {
			if ( (int) $id !== $this->admin ) {
				get_userdata( (int) $id )->set_role( 'subscriber' );
			}
		}
	}

	public function test_an_administrator_can_be_demoted_when_there_is_another(): void {
		$this->leave_one_administrator();

		$second = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->roles->assign_user( $this->admin, 'subscriber', $second );

		$this->assertContains( 'subscriber', (array) get_userdata( $this->admin )->roles );
	}
}
