<?php
/**
 * Makes or removes a dashboard, for the flow test.
 *
 * A file rather than `wp eval '...'`: a namespaced class name does not survive
 * bash, ssh, sudo and WP-CLI's own eval intact.
 *
 * Note there is no declare(strict_types=1): WP-CLI evaluates the file with a
 * leading `?>`, so nothing here is ever the first statement of a script.
 *
 * Run with: OXYAREA_ROLE=subscriber wp eval-file testbed-dashboard.php
 * With no OXYAREA_ROLE, every dashboard is removed instead.
 *
 * @package OxyArea
 */

use OxyArea\Dashboard\DashboardPostType;
use OxyArea\Persistence\DashboardRepository;

$oxyarea_role  = (string) getenv( 'OXYAREA_ROLE' );
$oxyarea_store = new DashboardRepository();

if ( '' === $oxyarea_role ) {
	$oxyarea_existing = get_posts(
		array(
			'post_type'   => DashboardPostType::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => 100,
			'fields'      => 'ids',
		)
	);

	foreach ( (array) $oxyarea_existing as $oxyarea_id ) {
		wp_delete_post( (int) $oxyarea_id, true );
	}

	$oxyarea_store->flush();

	echo "cleared\n";

	return;
}

$oxyarea_id = wp_insert_post(
	array(
		'post_type'    => DashboardPostType::POST_TYPE,
		'post_status'  => 'publish',
		'post_title'   => 'Customer area',
		'post_content' => "<!-- wp:paragraph -->\n<p>Hello {{display_name}}, this is your private area.</p>\n<!-- /wp:paragraph -->\n<!-- wp:oxyarea/profile-summary /-->",
	)
);

update_post_meta( (int) $oxyarea_id, DashboardPostType::AUDIENCE_META, 'role:' . $oxyarea_role );

$oxyarea_store->flush();

echo (int) $oxyarea_id . "\n";
