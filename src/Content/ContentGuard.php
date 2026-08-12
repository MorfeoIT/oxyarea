<?php
/**
 * The gate on a single page.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Content;

use OxyArea\Access\AccessResolverInterface;
use OxyArea\Access\ProtectedResource;
use OxyArea\Auth\Destination;
use OxyArea\Infrastructure\Registrable;
use OxyArea\Infrastructure\Settings;
use OxyArea\Infrastructure\Templates;
use WP_Post;

/**
 * Refuses a restricted page to somebody who may not have it.
 *
 * Runs at `template_redirect`, before a theme has printed anything, because two
 * of the four answers are an HTTP status and one is a redirect, and none of
 * those can be sent after output has begun.
 *
 * This is the gate that matters. The filters on listings, feeds and the REST API
 * stop a private page being *mentioned*; this stops it being *read*, and a site
 * that got only one of the two right would be a site where the private page is
 * one guessed URL away.
 */
final class ContentGuard implements Registrable {

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
	 * The settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Rendering.
	 *
	 * @var Templates
	 */
	private Templates $templates;

	/**
	 * Whether the current page is being shown as a refusal.
	 *
	 * @var bool
	 */
	private bool $refused = false;

	/**
	 * Build the guard.
	 *
	 * @param AccessResolverInterface $access       Who may see what.
	 * @param Restrictions            $restrictions What is restricted at all.
	 * @param Settings                $settings     The settings.
	 * @param Templates               $templates    Rendering.
	 */
	public function __construct(
		AccessResolverInterface $access,
		Restrictions $restrictions,
		Settings $settings,
		Templates $templates
	) {
		$this->access       = $access;
		$this->restrictions = $restrictions;
		$this->settings     = $settings;
		$this->templates    = $templates;
	}

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'guard' ), 1 );
	}

	/**
	 * Decide whether this page may be shown, and act if it may not.
	 *
	 * @return void
	 */
	public function guard(): void {
		if ( is_admin() || ! is_singular() || ! $this->restrictions->any() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof WP_Post || ! $this->restrictions->is_restricted( (int) $post->ID ) ) {
			return;
		}

		if ( $this->access->can_view( get_current_user_id(), ProtectedResource::post( (int) $post->ID ) ) ) {
			return;
		}

		/**
		 * Fires when somebody is refused a restricted page.
		 *
		 * @since 0.1.0
		 *
		 * @param int $post_id The page.
		 * @param int $user_id Who was refused, or 0.
		 */
		do_action( 'oxyarea_content_refused', (int) $post->ID, get_current_user_id() );

		$this->act( (int) $post->ID );
	}

	/**
	 * Do whatever the site asked for.
	 *
	 * @param int $post_id The page being refused.
	 * @return void
	 */
	private function act( int $post_id ): void {
		$login_url = $this->login_url();

		$behaviour = Unauthorised::decide(
			(string) $this->settings->get( 'restricted_behaviour', Unauthorised::LOGIN ),
			is_user_logged_in(),
			$login_url
		);

		/**
		 * Filters what a refusal looks like for a particular page.
		 *
		 * @since 0.1.0
		 *
		 * @param string $behaviour One of login, message, 403 or 404.
		 * @param int    $post_id   The page being refused.
		 */
		$behaviour = (string) apply_filters( 'oxyarea_unauthorised_behaviour', $behaviour, $post_id );

		// Written as a chain rather than a switch: wp_die() ends the request, and
		// a switch case that ends the request is either an unreachable statement to
		// one tool or a missing fall-through comment to the other.
		if ( Unauthorised::LOGIN === $behaviour ) {
			$here = home_url( add_query_arg( array() ) );

			wp_safe_redirect(
				add_query_arg( Destination::FIELD, rawurlencode( $here ), $login_url )
			);

			exit;
		}

		if ( Unauthorised::FORBIDDEN === $behaviour ) {
			wp_die(
				esc_html__( 'You are not allowed to see this page.', 'oxyarea' ),
				esc_html__( 'Not allowed', 'oxyarea' ),
				array( 'response' => 403 )
			);
		}

		if ( Unauthorised::NOT_FOUND === $behaviour ) {
			$this->pretend_it_is_not_there();

			return;
		}

		// Anything else, including a filter that returned something unexpected,
		// leaves them on the page with an explanation.
		$this->refused = true;

		add_filter( 'the_content', array( $this, 'replace_content' ), 999 );
		add_filter( 'the_excerpt', array( $this, 'replace_content' ), 999 );
		add_filter( 'comments_open', '__return_false', 999 );
		add_filter( 'get_comments_number', '__return_zero', 999 );
	}

	/**
	 * Show the refusal in place of the page's content.
	 *
	 * @param string $content What the page would have said.
	 * @return string
	 */
	public function replace_content( $content ): string {
		if ( ! $this->refused || ! is_singular() || ! in_the_loop() ) {
			return (string) $content;
		}

		return $this->templates->render(
			'content/restricted',
			array(
				'signed_in' => is_user_logged_in(),
				'login_url' => $this->login_url(),
			)
		);
	}

	/**
	 * Answer as though the page does not exist.
	 *
	 * The only one of the four behaviours that does not confirm the page is
	 * there. On a site whose private area is the product, "there is a page here
	 * you cannot have" is itself information.
	 *
	 * @return void
	 */
	private function pretend_it_is_not_there(): void {
		global $wp_query;

		if ( isset( $wp_query ) ) {
			$wp_query->set_404();
		}

		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Where the sign-in form is, or an empty string if the site has not said.
	 *
	 * @return string
	 */
	private function login_url(): string {
		$page_id = (int) $this->settings->get( 'login_page', 0 );

		if ( $page_id > 0 ) {
			$permalink = get_permalink( $page_id );

			if ( is_string( $permalink ) && '' !== $permalink ) {
				return $permalink;
			}
		}

		return wp_login_url();
	}
}
