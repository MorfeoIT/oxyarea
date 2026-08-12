<?php
/**
 * The release blockers.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Security;

use OxyArea\Access\Assignment;
use OxyArea\Access\ProtectedResource;
use OxyArea\Access\Subject;
use OxyArea\Content\QueryGuard;
use OxyArea\Content\Restrictions;
use OxyArea\Persistence\AssignmentRepository;
use OxyArea\Tests\Support\CastTestCase;
use WP_Error;
use WP_Query;
use WP_REST_Request;

/**
 * Alice may read the contract. Bob may not, and cannot find out that it exists.
 *
 * The specification refuses a release if private content leaks through search,
 * feeds, sitemaps or REST, or if one customer can reach another's page. Those
 * are five different ways of asking WordPress a question and this asks all five,
 * as each of three people.
 *
 * These run against a real database and a real WP_Query, which is the point:
 * the unit suite proves the resolver decides correctly, and this proves the
 * decision is actually applied by the things that list content.
 */
final class ContentRestrictionTest extends CastTestCase {

	/**
	 * A post everybody may read.
	 *
	 * @var int
	 */
	private int $public_post;

	/**
	 * The word both posts share, so that a search matches both and the filtering
	 * is what decides which come back. Searching for a word only one of them
	 * contains would pass for the wrong reason.
	 */
	private const SHARED_WORD = 'document';

	/**
	 * A post only customers may read.
	 *
	 * @var int
	 */
	private int $private_post;

	public function set_up(): void {
		parent::set_up();

		$this->public_post = self::factory()->post->create(
			array(
				'post_title'   => 'A public announcement',
				'post_content' => 'A document everybody may read.',
			)
		);

		$this->private_post = self::factory()->post->create(
			array(
				'post_title'   => 'The quarterly contract',
				'post_content' => 'A document only customers may read.',
			)
		);

		$this->assignments->replace_for_resource(
			ProtectedResource::post( $this->private_post ),
			array( new Assignment( Subject::role( 'customer' ) ) )
		);
	}

	public function test_only_the_restricted_post_is_restricted(): void {
		$restrictions = new Restrictions( new AssignmentRepository() );

		$this->assertTrue( $restrictions->is_restricted( $this->private_post ) );
		$this->assertFalse( $restrictions->is_restricted( $this->public_post ) );
	}

	public function test_alice_may_read_it_and_bob_may_not(): void {
		$this->assertTrue( $this->resolver()->can_view( $this->alice, ProtectedResource::post( $this->private_post ) ) );
		$this->assertFalse( $this->resolver()->can_view( $this->carol, ProtectedResource::post( $this->private_post ) ) );
	}

	public function test_bob_holds_the_same_role_and_so_may_read_it(): void {
		// Bob is also a customer, so he may. The interesting isolation in the free
		// plugin is between roles; between individuals is what PRO adds, and the
		// test for it belongs there rather than being faked here.
		$this->assertTrue( $this->resolver()->can_view( $this->bob, ProtectedResource::post( $this->private_post ) ) );
	}

	public function test_a_stranger_may_not(): void {
		$this->assertFalse( $this->resolver()->can_view( 0, ProtectedResource::post( $this->private_post ) ) );
	}

	public function test_search_does_not_mention_it_to_somebody_who_may_not_read_it(): void {
		$found = $this->titles_found_by( $this->carol, array( 's' => self::SHARED_WORD ) );

		$this->assertContains( 'A public announcement', $found );
		$this->assertNotContains( 'The quarterly contract', $found );
	}

	public function test_search_does_mention_it_to_somebody_who_may(): void {
		$found = $this->titles_found_by( $this->alice, array( 's' => self::SHARED_WORD ) );

		$this->assertContains( 'The quarterly contract', $found );
	}

	public function test_a_stranger_searching_finds_only_the_public_one(): void {
		$found = $this->titles_found_by( 0, array( 's' => self::SHARED_WORD ) );

		$this->assertSame( array( 'A public announcement' ), $found );
	}

	public function test_the_feed_leaves_it_out(): void {
		$found = $this->titles_found_by( 0, array( 'feed' => 'rss2' ) );

		$this->assertNotContains( 'The quarterly contract', $found );
	}

	public function test_an_archive_leaves_it_out(): void {
		$found = $this->titles_found_by( 0, array( 'post_type' => 'post' ) );

		$this->assertNotContains( 'The quarterly contract', $found );
	}

	public function test_the_sitemap_leaves_it_out_whoever_is_asking(): void {
		// A sitemap is read by machines that are never signed in, so everything
		// restricted comes out of it — the leak with the longest tail, since a
		// search engine would keep the URL.
		$guard = $this->guard();
		$args  = $guard->filter_sitemap( array() );

		$this->assertContains( $this->private_post, array_map( 'intval', $args['post__not_in'] ) );
		$this->assertNotContains( $this->public_post, array_map( 'intval', $args['post__not_in'] ) );
	}

	public function test_rest_refuses_the_single_item_to_a_stranger(): void {
		wp_set_current_user( 0 );

		$answer = $this->guard()->guard_rest(
			null,
			null,
			new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->private_post )
		);

		$this->assertInstanceOf( WP_Error::class, $answer );
	}

	public function test_and_answers_404_rather_than_403(): void {
		// 403 would confirm the post is there, which on a site whose private area
		// is the product is itself information.
		wp_set_current_user( 0 );

		$answer = $this->guard()->guard_rest(
			null,
			null,
			new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->private_post )
		);

		$this->assertInstanceOf( WP_Error::class, $answer );
		$this->assertSame( 404, $answer->get_error_data()['status'] );
	}

	public function test_rest_refuses_it_to_carol_too(): void {
		wp_set_current_user( $this->carol );

		$this->assertInstanceOf(
			WP_Error::class,
			$this->guard()->guard_rest( null, null, new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->private_post ) )
		);
	}

	public function test_rest_lets_alice_through(): void {
		wp_set_current_user( $this->alice );

		$this->assertNull(
			$this->guard()->guard_rest( null, null, new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->private_post ) )
		);
	}

	public function test_rest_leaves_an_unrestricted_post_alone(): void {
		wp_set_current_user( 0 );

		$this->assertNull(
			$this->guard()->guard_rest( null, null, new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->public_post ) )
		);
	}

	public function test_rest_ignores_routes_that_are_not_a_single_post(): void {
		wp_set_current_user( 0 );

		foreach ( array( '/wp/v2/posts', '/wp/v2/users/1', '/oxyarea/v1/anything' ) as $route ) {
			$this->assertNull(
				$this->guard()->guard_rest( null, null, new WP_REST_Request( 'GET', $route ) ),
				sprintf( 'The guard should not touch %s.', $route )
			);
		}
	}

	public function test_changing_alices_role_changes_what_she_may_read(): void {
		$alice = get_userdata( $this->alice );
		$alice->set_role( 'agent' );

		$this->assertFalse(
			$this->resolver()->can_view( $this->alice, ProtectedResource::post( $this->private_post ) ),
			'Taking the role away should take the contract away.'
		);
	}

	public function test_removing_the_rule_makes_the_post_public_again(): void {
		$this->assignments->replace_for_resource( ProtectedResource::post( $this->private_post ), array() );

		$restrictions = new Restrictions( new AssignmentRepository() );

		$this->assertFalse( $restrictions->is_restricted( $this->private_post ) );

		$found = $this->titles_found_by( 0, array( 's' => self::SHARED_WORD ) );

		$this->assertContains( 'The quarterly contract', $found );
	}

	/**
	 * A guard wired the way the plugin wires it.
	 *
	 * @return QueryGuard
	 */
	private function guard(): QueryGuard {
		return new QueryGuard( $this->resolver(), new Restrictions( new AssignmentRepository() ) );
	}

	/**
	 * The titles a query returns for a particular person, after the guard.
	 *
	 * @param int                  $user_id Who is asking, or 0.
	 * @param array<string, mixed> $args    The query.
	 * @return list<string>
	 */
	private function titles_found_by( int $user_id, array $args ): array {
		wp_set_current_user( $user_id );

		$guard = $this->guard();

		add_filter( 'the_posts', array( $guard, 'filter_posts' ), 10, 2 );

		$query = new WP_Query( array_merge( array( 'posts_per_page' => 50 ), $args ) );

		remove_filter( 'the_posts', array( $guard, 'filter_posts' ), 10 );

		return array_values( wp_list_pluck( $query->posts, 'post_title' ) );
	}
}
