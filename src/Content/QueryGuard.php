<?php
/**
 * Keeping restricted pages out of the places that list them.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Content;

use OxyArea\Access\AccessResolverInterface;
use OxyArea\Access\ProtectedResource;
use OxyArea\Infrastructure\Registrable;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;

/**
 * Removes restricted pages from search, feeds, archives, the REST API and the
 * sitemap.
 *
 * The specification lists "private content leaks through search, feeds, sitemaps
 * or REST" as a release blocker, and it is right to treat the four together:
 * they are four different ways of asking WordPress for a list, and a plugin that
 * remembers three of them has a hole in the fourth.
 *
 * Filtering happens after the query rather than inside it. Rewriting the SQL to
 * express "posts this particular person may see" means reimplementing the
 * resolver in a WHERE clause, in a second language, where it cannot be tested
 * and where every future subject type PRO adds would have to be expressed again.
 * The cost is that a page of ten may show eight; the benefit is that what it
 * shows is decided by the same code that decides everything else.
 */
final class QueryGuard implements Registrable {

	/**
	 * Who may see what.
	 *
	 * @var AccessResolverInterface
	 */
	private AccessResolverInterface $access;

	/**
	 * What is restricted at all.
	 *
	 * @var Restrictions
	 */
	private Restrictions $restrictions;

	/**
	 * Build the guard.
	 *
	 * @param AccessResolverInterface $access       Who may see what.
	 * @param Restrictions            $restrictions What is restricted at all.
	 */
	public function __construct( AccessResolverInterface $access, Restrictions $restrictions ) {
		$this->access       = $access;
		$this->restrictions = $restrictions;
	}

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'the_posts', array( $this, 'filter_posts' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'filter_sitemap' ) );
		add_filter( 'rest_request_before_callbacks', array( $this, 'guard_rest' ), 10, 3 );
		add_filter( 'get_previous_post_where', array( $this, 'filter_adjacent' ) );
		add_filter( 'get_next_post_where', array( $this, 'filter_adjacent' ) );
	}

	/**
	 * Drop the posts this visitor may not see.
	 *
	 * @param mixed $posts The posts the query found.
	 * @param mixed $query The query.
	 * @return array<int, mixed>
	 */
	public function filter_posts( $posts, $query = null ): array {
		$posts = is_array( $posts ) ? $posts : array();

		if ( array() === $posts || ! $this->restrictions->any() ) {
			return $posts;
		}

		// The admin lists posts so they can be managed, and an editor who cannot
		// see a restricted page in the list cannot edit it either. The gate for
		// wp-admin is the capability system, not this.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $posts;
		}

		// A single page is the other guard's business: dropping the post here
		// would produce a 404 whatever the site asked for.
		if ( $query instanceof WP_Query && $query->is_main_query() && $query->is_singular() ) {
			return $posts;
		}

		$ids = array();

		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$ids[] = (int) $post->ID;
			}
		}

		$this->restrictions->warm( $ids );

		$user_id = get_current_user_id();

		return array_values(
			array_filter(
				$posts,
				function ( $post ) use ( $user_id ): bool {
					if ( ! $post instanceof WP_Post ) {
						return true;
					}

					return $this->may_see( $user_id, (int) $post->ID );
				}
			)
		);
	}

	/**
	 * Keep restricted pages out of the sitemap entirely.
	 *
	 * A sitemap is read by machines that are never signed in, so there is nobody
	 * to resolve against and nothing to weigh up: everything restricted comes
	 * out. Leaving a URL in for a search engine to index would be the leak with
	 * the longest tail of them all.
	 *
	 * @param mixed $args The query arguments.
	 * @return array<string, mixed>
	 */
	public function filter_sitemap( $args ): array {
		$args = is_array( $args ) ? $args : array();

		if ( ! $this->restrictions->any() ) {
			return $args;
		}

		$excluded = isset( $args['post__not_in'] ) && is_array( $args['post__not_in'] )
			? $args['post__not_in']
			: array();

		$args['post__not_in'] = array_values(
			array_unique( array_merge( $excluded, $this->restrictions->ids() ) )
		);

		return $args;
	}

	/**
	 * Refuse a restricted post asked for through the REST API.
	 *
	 * Answers 404 rather than 403, for the same reason the 404 behaviour exists:
	 * "this exists and you may not have it" is itself information about a site
	 * whose private area is the product.
	 *
	 * @param mixed $response The response so far.
	 * @param mixed $handler  The route handler.
	 * @param mixed $request  The request.
	 * @return mixed
	 */
	public function guard_rest( $response, $handler = null, $request = null ) {
		unset( $handler );

		if ( $response instanceof WP_Error || ! $request instanceof WP_REST_Request ) {
			return $response;
		}

		if ( ! $this->restrictions->any() ) {
			return $response;
		}

		// Only the single-item routes. Collections go through WP_Query and are
		// already handled by the filter above.
		if ( 1 !== preg_match( '#^/wp/v2/[A-Za-z0-9_-]+/(\d+)$#', (string) $request->get_route(), $found ) ) {
			return $response;
		}

		$post_id = (int) $found[1];

		if ( ! $this->restrictions->is_restricted( $post_id ) ) {
			return $response;
		}

		if ( $this->may_see( get_current_user_id(), $post_id ) ) {
			return $response;
		}

		return new WP_Error(
			'rest_post_invalid_id',
			__( 'Invalid post ID.', 'oxyarea' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Keep restricted posts out of "previous" and "next" links.
	 *
	 * A neighbour link is a small leak with a title attached, which makes it a
	 * leak of exactly the sort the specification calls out.
	 *
	 * @param mixed $where The SQL fragment.
	 * @return string
	 */
	public function filter_adjacent( $where ): string {
		$where = (string) $where;

		if ( ! $this->restrictions->any() ) {
			return $where;
		}

		$excluded = $this->visible_exclusions( get_current_user_id() );

		if ( array() === $excluded ) {
			return $where;
		}

		// Every value is an integer from our own table, run through absint on the
		// way in, so there is nothing here for prepare() to protect. WordPress
		// builds the adjacent-post query with `p` as the alias.
		$list = implode( ',', array_map( 'absint', $excluded ) );

		return $where . " AND p.ID NOT IN ({$list})";
	}

	/**
	 * Whether somebody may see a post, restricted or not.
	 *
	 * @param int $user_id The visitor, or 0.
	 * @param int $post_id The post.
	 * @return bool
	 */
	private function may_see( int $user_id, int $post_id ): bool {
		if ( ! $this->restrictions->is_restricted( $post_id ) ) {
			return true;
		}

		return $this->access->can_view( $user_id, ProtectedResource::post( $post_id ) );
	}

	/**
	 * The restricted posts this visitor may not see.
	 *
	 * @param int $user_id The visitor, or 0.
	 * @return list<int>
	 */
	private function visible_exclusions( int $user_id ): array {
		$restricted = $this->restrictions->ids();

		$this->restrictions->warm( $restricted );

		$excluded = array();

		foreach ( $restricted as $post_id ) {
			if ( ! $this->may_see( $user_id, (int) $post_id ) ) {
				$excluded[] = (int) $post_id;
			}
		}

		return $excluded;
	}
}
