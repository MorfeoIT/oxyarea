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

// Marked with a meta key rather than found by slug. WordPress appends a suffix
// to a duplicate slug, so a run that died before its teardown leaves posts whose
// slugs no longer match what the next teardown looks for — and the next run then
// cleans up the previous run's posts while orphaning its own.
$oxyarea_marker = '_oxyarea_flow_test';

if ( '1' !== (string) getenv( 'OXYAREA_SETUP' ) ) {
	$oxyarea_ours = get_posts(
		array(
			'post_type'   => 'any',
			'post_status' => 'any',
			'numberposts' => 100,
			'fields'      => 'ids',
			'meta_key'    => $oxyarea_marker,
			'meta_value'  => '1',
		)
	);

	foreach ( (array) $oxyarea_ours as $oxyarea_id ) {
		$oxyarea_rules->replace_for_resource( ProtectedResource::post( (int) $oxyarea_id ), array() );
		wp_delete_post( (int) $oxyarea_id, true );
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

update_post_meta( (int) $oxyarea_public, $oxyarea_marker, '1' );
update_post_meta( (int) $oxyarea_private, $oxyarea_marker, '1' );

$oxyarea_rules->replace_for_resource(
	ProtectedResource::post( (int) $oxyarea_private ),
	array( new Assignment( Subject::role( $oxyarea_role_slug ) ) )
);

echo 'public=' . (int) $oxyarea_public . ' private=' . (int) $oxyarea_private . ' role=' . $oxyarea_role_slug . "\n";
