<?php
/**
 * Exercises the parts of OxyArea that only exist inside WordPress: the role
 * manager, the assignment repository and the resolver wired to real roles.
 *
 * Run with: wp eval-file smoke.php
 *
 * Cleans up after itself. Anything it leaves behind is a bug in this file.
 */

use OxyArea\Access\AccessResolver;
use OxyArea\Access\Assignment;
use OxyArea\Access\AudienceResolver;
use OxyArea\Access\ProtectedResource;
use OxyArea\Access\Subject;
use OxyArea\Infrastructure\SystemClock;
use OxyArea\Persistence\AssignmentRepository;
use OxyArea\Roles\CapabilityManagerCheck;
use OxyArea\Roles\ManagedRoles;
use OxyArea\Roles\RoleException;
use OxyArea\Roles\RoleManager;

// wp eval-file includes this inside a function, so top-level variables here are
// not globals. The counters are addressed explicitly so that check() reaches the
// same two whatever scope it is called from.
$GLOBALS['oxa_passed'] = 0;
$GLOBALS['oxa_failed'] = 0;

/**
 * Assert.
 *
 * @param string $what      What is being checked.
 * @param bool   $condition Whether it holds.
 */
function check( string $what, bool $condition ): void {
	if ( $condition ) {
		++$GLOBALS['oxa_passed'];
		echo "  ok    {$what}\n";

		return;
	}

	++$GLOBALS['oxa_failed'];
	echo "  FAIL  {$what}\n";
}

/**
 * Assert that something is refused.
 *
 * @param string   $what   What is being checked.
 * @param callable $action The thing that should be refused.
 */
function refuses( string $what, callable $action ): void {
	try {
		$action();
		check( $what, false );
	} catch ( RoleException $e ) {
		check( $what . ' — ' . $e->getMessage(), true );
	}
}

$admin = get_user_by( 'login', 'oxysoft' );
$alice = get_user_by( 'login', 'alice' );
$bob   = get_user_by( 'login', 'bob' );
$carol = get_user_by( 'login', 'carol' );

$managed = new ManagedRoles();
$manager = new RoleManager( $managed );
$repo    = new AssignmentRepository();

/**
 * A resolver with a fresh audience cache, since roles change during this run.
 *
 * @return AccessResolver
 */
function resolver(): AccessResolver {
	return new AccessResolver(
		new AssignmentRepository(),
		new AudienceResolver( array( new \OxyArea\Roles\RoleAudienceProvider() ) ),
		new CapabilityManagerCheck(),
		new SystemClock()
	);
}

echo "\n== creating roles ==\n";

$customer = $manager->create( 'Test Customer', 'oxytest_customer', array( 'read' ), $admin->ID );
$agent    = $manager->create( 'Test Agent', 'oxytest_agent', array( 'read', 'upload_files' ), $admin->ID );

check( 'the customer role exists', null !== get_role( $customer ) );
check( 'OxyArea remembers it created it', $managed->contains( $customer ) );
check( 'every new role can read', get_role( $customer )->has_cap( 'read' ) );
check( 'the agent role got upload_files', get_role( $agent )->has_cap( 'upload_files' ) );

echo "\n== the refusals ==\n";

refuses(
	'will not edit the administrator role',
	static fn () => $manager->update_capabilities( 'administrator', array( 'read' ), $admin->ID )
);

refuses(
	'will not delete a role it did not create',
	static fn () => $manager->delete( 'editor', 'subscriber', $admin->ID )
);

refuses(
	'will not create a role whose name leaves no identifier',
	static fn () => $manager->create( '???', '', array(), $admin->ID )
);

refuses(
	'will not create a role that already exists',
	static fn () => $manager->create( 'Test Customer', 'oxytest_customer', array(), $admin->ID )
);

refuses(
	'will not move a role\'s people to a role that does not exist',
	static fn () => $manager->delete( 'oxytest_agent', 'no_such_role', $admin->ID )
);

echo "\n== the escalation guard ==\n";

// Alice is a subscriber. She holds none of these, so none of them may be given
// away by her, whatever the form posts.
$escalated = $manager->create( 'Escalation Attempt', 'oxytest_escalation', array( 'install_plugins', 'edit_users', 'manage_options', 'upload_files' ), $alice->ID );
$role      = get_role( $escalated );

check( 'a subscriber cannot grant install_plugins', ! $role->has_cap( 'install_plugins' ) );
check( 'a subscriber cannot grant edit_users', ! $role->has_cap( 'edit_users' ) );
check( 'a subscriber cannot grant manage_options', ! $role->has_cap( 'manage_options' ) );
check( 'a subscriber cannot grant upload_files either', ! $role->has_cap( 'upload_files' ) );
check( 'but the role still exists and can read', $role->has_cap( 'read' ) );

echo "\n== capabilities from other plugins are left alone ==\n";

$role = get_role( $agent );
$role->add_cap( 'woocommerce_view_order' );

$manager->update_capabilities( $agent, array( 'read' ), $admin->ID );

check(
	'a capability outside the catalogue survives a save',
	get_role( $agent )->has_cap( 'woocommerce_view_order' )
);
check(
	'a capability inside the catalogue is removed as asked',
	! get_role( $agent )->has_cap( 'upload_files' )
);

echo "\n== Alice and Bob ==\n";

$manager->assign_user( $alice->ID, $customer, $admin->ID );
$manager->assign_user( $bob->ID, 'subscriber', $admin->ID );
$manager->assign_user( $carol->ID, $agent, $admin->ID );

$post_id  = wp_insert_post(
	array(
		'post_title'   => 'Customer contract 2026',
		'post_content' => 'Private.',
		'post_status'  => 'publish',
		'post_author'  => $admin->ID,
	)
);
$document = ProtectedResource::post( (int) $post_id );

$repo->replace_for_resource( $document, array( new Assignment( Subject::role( $customer ) ) ) );

check( 'Alice, a customer, may see the contract', resolver()->can_view( $alice->ID, $document ) );
check( 'Bob, who is not, may not', ! resolver()->can_view( $bob->ID, $document ) );
check( 'Carol, an agent, may not', ! resolver()->can_view( $carol->ID, $document ) );
check( 'a signed-out visitor may not', ! resolver()->can_view( 0, $document ) );
check( 'the administrator may', resolver()->can_view( $admin->ID, $document ) );

echo "\n== changing a role changes what Alice sees ==\n";

$manager->assign_user( $alice->ID, 'subscriber', $admin->ID );
check( 'taking the role away takes the contract away', ! resolver()->can_view( $alice->ID, $document ) );

$manager->assign_user( $alice->ID, $customer, $admin->ID );
check( 'giving it back gives the contract back', resolver()->can_view( $alice->ID, $document ) );

echo "\n== an explicit deny ==\n";

// Carol gets both roles, which is the only way to test deny-beats-allow with
// what the free plugin can express. add_role rather than the manager, because
// assign_user deliberately replaces roles rather than adding to them.
get_user_by( 'id', $carol->ID )->add_role( $customer );

$repo->replace_for_resource(
	$document,
	array(
		new Assignment( Subject::role( $customer ) ),
		new Assignment( Subject::role( $agent ), Assignment::DENY ),
	)
);

check( 'Alice, a customer only, still sees it', resolver()->can_view( $alice->ID, $document ) );
check( 'Carol, who is also an agent, does not: the deny wins', ! resolver()->can_view( $carol->ID, $document ) );

echo "\n== the free/PRO boundary ==\n";

// A rule naming one individual is stored and read back perfectly well, but
// nothing in the free plugin ever presents a "user" subject, so it matches
// nobody. Per-user targeting is what PRO's audience providers add. This is the
// specification working, not a gap.
$repo->replace_for_resource(
	$document,
	array(
		new Assignment( Subject::role( $customer ) ),
		new Assignment( new Subject( Subject::USER, (string) $alice->ID ), Assignment::DENY ),
	)
);

check(
	'a rule naming an individual does nothing without PRO',
	resolver()->can_view( $alice->ID, $document )
);

get_user_by( 'id', $carol->ID )->remove_role( $customer );

echo "\n== the stored rules survive a round trip ==\n";

$stored = $repo->for_resource( $document );
check( 'both rules came back', 2 === count( $stored ) );
check( 'the deny is still a deny', $stored[0]->is_deny() || $stored[1]->is_deny() );

$repo->replace_for_resource( $document, array() );
check( 'clearing the rules leaves nothing granting access', ! resolver()->can_view( $alice->ID, $document ) );

echo "\n== deleting a role moves its people ==\n";

$moved = $manager->delete( $customer, 'subscriber', $admin->ID );

check( 'the role is gone', null === get_role( $customer ) );
check( 'OxyArea has forgotten it', ! $managed->contains( $customer ) );
check( 'one person was moved', 1 === $moved );

$alice = get_user_by( 'id', $alice->ID );
check( 'Alice is a subscriber again, not roleless', in_array( 'subscriber', (array) $alice->roles, true ) );

echo "\n== cleaning up ==\n";

wp_delete_post( (int) $post_id, true );
$manager->delete( $agent, 'subscriber', $admin->ID );
$manager->delete( $escalated, 'subscriber', $admin->ID );

foreach ( array( 'alice', 'bob', 'carol' ) as $login ) {
	$user = get_user_by( 'login', $login );
	$user->set_role( 'subscriber' );
}

check( 'no OxyArea test roles are left behind', array() === $managed->all() );

echo "\n== result ==\n";
echo "  passed: {$GLOBALS['oxa_passed']}\n";
echo "  failed: {$GLOBALS['oxa_failed']}\n";

if ( $GLOBALS['oxa_failed'] > 0 ) {
	exit( 1 );
}
