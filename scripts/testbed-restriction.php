<?php
/**
 * Sets up or tears down the restriction flow test.
 *
 * A file rather than `wp eval '...'`: namespaced class names do not survive
 * bash, ssh, sudo and WP-CLI's own eval intact. No declare(strict_types=1)
 * either — wp eval-file evaluates the file with a leading `?>`, so nothing here
 * is ever the first statement of a script.
 *
 * OXYAREA_SETUP=1  makes the role, the posts and the restriction, and prints
 *                  "public=<id> private=<id> role=<slug>".
 * With no OXYAREA_SETUP, everything it made is removed.
 *
 * @package OxyArea
 */

use OxyArea\Access\Assignment;
use OxyArea\Access\ProtectedResource;
use OxyArea\Access\Subject;
use OxyArea\Persistence\AssignmentRepository;
use OxyArea\Roles\ManagedRoles;
use OxyArea\Roles\RoleManager;

$oxyarea_role_slug = 'oxytest_restricted';
$oxyarea_managed   = new ManagedRoles();
$oxyarea_manager   = new RoleManager( $oxyarea_managed );
$oxyarea_rules     = new AssignmentRepository();
$oxyarea_admin     = get_user_by( 'login', 'oxysoft' );
$oxyarea_alice     = get_user_by( 'login', 'alice' );
$oxyarea_bob       = get_user_by( 'login', 'bob' );

if ( '1' !== (string) getenv( 'OXYAREA_SETUP' ) ) {
	foreach ( array( 'oxyarea-public-post', 'oxyarea-private-post' ) as $oxyarea_slug ) {
		$oxyarea_found = get_page_by_path( $oxyarea_slug, OBJECT, 'post' );

		if ( $oxyarea_found ) {
			$oxyarea_rules->replace_for_resource( ProtectedResource::post( (int) $oxyarea_found->ID ), array() );
			wp_delete_post( (int) $oxyarea_found->ID, true );
		}
	}

	$oxyarea_alice->set_role( 'subscriber' );
	$oxyarea_bob->set_role( 'subscriber' );

	if ( $oxyarea_managed->contains( $oxyarea_role_slug ) ) {
		$oxyarea_manager->delete( $oxyarea_role_slug, 'subscriber', (int) $oxyarea_admin->ID );
	}

	echo "cleared\n";

	return;
}

if ( null === get_role( $oxyarea_role_slug ) ) {
	$oxyarea_manager->create( 'Restricted tester', $oxyarea_role_slug, array( 'read' ), (int) $oxyarea_admin->ID );
}

$oxyarea_manager->assign_user( (int) $oxyarea_alice->ID, $oxyarea_role_slug, (int) $oxyarea_admin->ID );
$oxyarea_manager->assign_user( (int) $oxyarea_bob->ID, 'subscriber', (int) $oxyarea_admin->ID );

$oxyarea_public = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'A public announcement',
		'post_name'    => 'oxyarea-public-post',
		'post_content' => 'PUBLICMARKER everybody may read this.',
	)
);

$oxyarea_private = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'The quarterly contract',
		'post_name'    => 'oxyarea-private-post',
		'post_content' => 'SECRETMARKER the terms of the contract.',
	)
);

$oxyarea_rules->replace_for_resource(
	ProtectedResource::post( (int) $oxyarea_private ),
	array( new Assignment( Subject::role( $oxyarea_role_slug ) ) )
);

echo 'public=' . (int) $oxyarea_public . ' private=' . (int) $oxyarea_private . ' role=' . $oxyarea_role_slug . "\n";
