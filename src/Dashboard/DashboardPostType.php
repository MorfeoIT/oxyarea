<?php
/**
 * Dashboards, as a post type.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Dashboard;

use OxyArea\Infrastructure\Brand;
use OxyArea\Infrastructure\Registrable;
use OxyArea\Roles\Capabilities;

/**
 * Registers the post type dashboards are stored in.
 *
 * A post type and not a table, for one reason that outweighs the rest: the block
 * editor. "Gutenberg-based layout" is a requirement, and every hour spent
 * building a layout editor would be an hour spent building a worse one than the
 * editor already sitting in the same admin.
 *
 * Not public, and not publicly queryable. A dashboard has a URL of its own only
 * in the sense that everything in wp_posts does, and the whole point of the
 * feature is that people see the dashboard resolved for *them*, never one they
 * picked by identifier. The shortcode and the block never take an id from the
 * request.
 */
final class DashboardPostType implements Registrable {

	/**
	 * The post type.
	 */
	public const POST_TYPE = 'oxyarea_dashboard';

	/**
	 * The meta key holding who a dashboard is for.
	 */
	public const AUDIENCE_META = '_oxyarea_audience';

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$capability = Capabilities::MANAGE_DASHBOARDS;

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Dashboards', 'oxyarea' ),
					'singular_name' => __( 'Dashboard', 'oxyarea' ),
					'add_new_item'  => __( 'Add a dashboard', 'oxyarea' ),
					'edit_item'     => __( 'Edit dashboard', 'oxyarea' ),
					'search_items'  => __( 'Search dashboards', 'oxyarea' ),
					'not_found'     => __( 'No dashboards yet.', 'oxyarea' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => Brand::MENU_SLUG,
				'show_in_rest'        => true,
				'supports'            => array( 'title', 'editor', 'revisions' ),
				'menu_icon'           => 'dashicons-layout',
				'map_meta_cap'        => true,
				'capability_type'     => array( 'oxyarea_dashboard', 'oxyarea_dashboards' ),
				'capabilities'        => array(
					'edit_post'              => $capability,
					'read_post'              => $capability,
					'delete_post'            => $capability,
					'edit_posts'             => $capability,
					'edit_others_posts'      => $capability,
					'delete_posts'           => $capability,
					'delete_others_posts'    => $capability,
					'publish_posts'          => $capability,
					'read_private_posts'     => $capability,
					'create_posts'           => $capability,
					'edit_published_posts'   => $capability,
					'delete_published_posts' => $capability,
				),
			)
		);
	}
}
