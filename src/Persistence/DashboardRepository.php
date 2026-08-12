<?php
/**
 * The dashboards, from the post type.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Persistence;

use Exception;
use OxyArea\Access\Subject;
use OxyArea\Dashboard\Dashboard;
use OxyArea\Dashboard\DashboardPostType;
use OxyArea\Dashboard\DashboardRepositoryInterface;
use WP_Post;

/**
 * Reads the published dashboards and their audiences.
 *
 * One query per request, cached. A site has a handful of dashboards — one per
 * role and a default — so this is a small list read on every private page view,
 * which is exactly the shape that deserves a cache and does not deserve
 * pagination.
 */
final class DashboardRepository implements DashboardRepositoryInterface {

	/**
	 * The object cache group.
	 */
	private const CACHE_GROUP = 'oxyarea';

	/**
	 * The cache key.
	 */
	private const CACHE_KEY = 'dashboards';

	/**
	 * What audience() returns for a value it cannot make sense of.
	 *
	 * Distinct from null, which means "no audience recorded", which means the
	 * site default. Collapsing the two would take a dashboard whose audience is
	 * unreadable and serve it to everybody signed in, which is the widest
	 * possible reading of a value nobody can read.
	 */
	private const UNREADABLE = false;

	/**
	 * Every published dashboard, oldest first.
	 *
	 * @return list<Dashboard>
	 */
	public function all(): array {
		$cached = wp_cache_get( self::CACHE_KEY, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return array_values(
				array_filter( $cached, static fn ( $entry ): bool => $entry instanceof Dashboard )
			);
		}

		$posts = get_posts(
			array(
				'post_type'           => DashboardPostType::POST_TYPE,
				'post_status'         => 'publish',
				'numberposts'         => 100,
				'orderby'             => 'ID',
				'order'               => 'ASC',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		);

		$dashboards = array();

		foreach ( (array) $posts as $post ) {
			$dashboard = $this->hydrate( $post );

			if ( null !== $dashboard ) {
				$dashboards[] = $dashboard;
			}
		}

		wp_cache_set( self::CACHE_KEY, $dashboards, self::CACHE_GROUP );

		return $dashboards;
	}

	/**
	 * Forget what was cached.
	 *
	 * @return void
	 */
	public function flush(): void {
		wp_cache_delete( self::CACHE_KEY, self::CACHE_GROUP );
	}

	/**
	 * Turn a post into a dashboard, or nothing if it cannot be read.
	 *
	 * @param mixed $post The post.
	 * @return Dashboard|null
	 */
	private function hydrate( $post ): ?Dashboard {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$audience = $this->audience( (int) $post->ID );

		// A dashboard whose audience cannot be read is dropped, not widened. It
		// disappears from the site until somebody fixes it, which is a dull
		// outcome; the alternative is showing one role's page to everybody.
		if ( self::UNREADABLE === $audience ) {
			return null;
		}

		try {
			return new Dashboard( (int) $post->ID, (string) $post->post_title, $audience );
		} catch ( Exception $e ) {
			unset( $e );

			return null;
		}
	}

	/**
	 * Who a dashboard is for.
	 *
	 * @param int $post_id The dashboard.
	 * @return Subject|null|false Null for the site default, false when unreadable.
	 */
	private function audience( int $post_id ) {
		$stored = get_post_meta( $post_id, DashboardPostType::AUDIENCE_META, true );

		// Nothing recorded: this is the site's default dashboard.
		if ( ! is_string( $stored ) || '' === $stored ) {
			return null;
		}

		if ( Subject::AUTHENTICATED === $stored ) {
			return Subject::authenticated();
		}

		if ( 0 === strpos( $stored, 'role:' ) ) {
			$role = substr( $stored, 5 );

			return '' === $role ? self::UNREADABLE : Subject::role( $role );
		}

		return self::UNREADABLE;
	}
}
