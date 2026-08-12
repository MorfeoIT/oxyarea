<?php
/**
 * The cast the specification names.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Support;

use OxyArea\Access\AccessResolver;
use OxyArea\Access\AudienceResolver;
use OxyArea\Infrastructure\SystemClock;
use OxyArea\Persistence\AssignmentRepository;
use OxyArea\Roles\CapabilityManagerCheck;
use OxyArea\Roles\ManagedRoles;
use OxyArea\Roles\RoleAudienceProvider;
use OxyArea\Roles\RoleManager;
use WP_UnitTestCase;

/**
 * Admin, Alice, Bob and Carol, built the same way every time.
 *
 * The release gate is written in terms of these four people — "nothing ships if
 * Alice can reach Bob's data" — so every test that means anything about it uses
 * the same four. Building them here rather than in each test means a test reads
 * as the thing it is checking rather than as ten lines of setup.
 *
 * Each test runs in a transaction that is rolled back, so nothing here has to be
 * undone.
 */
abstract class CastTestCase extends WP_UnitTestCase {

	/**
	 * The administrator.
	 *
	 * @var int
	 */
	protected int $admin;

	/**
	 * A customer.
	 *
	 * @var int
	 */
	protected int $alice;

	/**
	 * Another customer, who must never see Alice's things.
	 *
	 * @var int
	 */
	protected int $bob;

	/**
	 * An agent.
	 *
	 * @var int
	 */
	protected int $carol;

	/**
	 * The role manager.
	 *
	 * @var RoleManager
	 */
	protected RoleManager $roles;

	/**
	 * Where the access rules live.
	 *
	 * @var AssignmentRepository
	 */
	protected AssignmentRepository $assignments;

	/**
	 * Build the cast.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->roles       = new RoleManager( new ManagedRoles() );
		$this->assignments = new AssignmentRepository();

		add_role( 'customer', 'Customer', array( 'read' => true ) );
		add_role( 'agent', 'Agent', array( 'read' => true ) );

		$this->admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->alice = self::factory()->user->create(
			array(
				'role'         => 'customer',
				'user_login'   => 'alice',
				'user_email'   => 'alice@example.test',
				'display_name' => 'Alice',
			)
		);
		$this->bob   = self::factory()->user->create(
			array(
				'role'       => 'customer',
				'user_login' => 'bob',
				'user_email' => 'bob@example.test',
			)
		);
		$this->carol = self::factory()->user->create(
			array(
				'role'       => 'agent',
				'user_login' => 'carol',
				'user_email' => 'carol@example.test',
			)
		);
	}

	/**
	 * Take the roles back out.
	 *
	 * Roles live in an option that the transaction rollback does restore, but
	 * WordPress caches wp_roles in memory for the process, so a role added in one
	 * test is still in the cache in the next one.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_role( 'customer' );
		remove_role( 'agent' );

		parent::tear_down();
	}

	/**
	 * An access resolver wired the way the plugin wires it.
	 *
	 * Built fresh on each call because the audience resolver caches per user, and
	 * a test that changes somebody's roles halfway needs the second answer, not
	 * the first.
	 *
	 * @return AccessResolver
	 */
	protected function resolver(): AccessResolver {
		return new AccessResolver(
			new AssignmentRepository(),
			new AudienceResolver( array( new RoleAudienceProvider() ) ),
			new CapabilityManagerCheck(),
			new SystemClock()
		);
	}
}
