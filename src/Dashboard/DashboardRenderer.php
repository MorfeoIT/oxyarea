<?php
/**
 * Turning a dashboard into a page.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Dashboard;

use OxyArea\Access\AudienceResolver;
use OxyArea\Access\Subject;
use WP_User;

/**
 * Renders the dashboard that belongs to somebody.
 *
 * Never one they asked for. The block and the shortcode take no identifier, and
 * there is no method here that takes one from a request: what gets rendered is
 * what the resolver decided, and the only way to change that is to change the
 * person's roles.
 */
final class DashboardRenderer {

	/**
	 * Where the dashboards are.
	 *
	 * @var DashboardRepositoryInterface
	 */
	private DashboardRepositoryInterface $dashboards;

	/**
	 * Which one wins.
	 *
	 * @var DashboardResolver
	 */
	private DashboardResolver $resolver;

	/**
	 * What people count as.
	 *
	 * @var AudienceResolver
	 */
	private AudienceResolver $audience;

	/**
	 * Guards against a dashboard that contains itself.
	 *
	 * @var bool
	 */
	private bool $rendering = false;

	/**
	 * Build the renderer.
	 *
	 * @param DashboardRepositoryInterface $dashboards Where the dashboards are.
	 * @param DashboardResolver            $resolver   Which one wins.
	 * @param AudienceResolver             $audience   What people count as.
	 * @param Widgets|null                 $widgets    The widgets add-ons have offered.
	 */
	public function __construct(
		DashboardRepositoryInterface $dashboards,
		DashboardResolver $resolver,
		AudienceResolver $audience,
		?Widgets $widgets = null
	) {
		$this->dashboards = $dashboards;
		$this->resolver   = $resolver;
		$this->audience   = $audience;
		$this->widgets    = $widgets;
	}

	/**
	 * The widgets an add-on has offered, or null when nothing collects them.
	 *
	 * Optional so that everything written before widgets could be contributed
	 * still constructs.
	 *
	 * @var Widgets|null
	 */
	private ?Widgets $widgets;

	/**
	 * The dashboard a user gets, rendered.
	 *
	 * @param int $user_id The user, or 0 for a signed-out visitor.
	 * @return string
	 */
	public function render_for( int $user_id ): string {
		$dashboard = $this->resolver->resolve(
			$this->audience->subjects_for( $user_id ),
			$this->dashboards->all()
		);

		if ( null === $dashboard ) {
			return '';
		}

		return $this->render( $dashboard, $user_id );
	}

	/**
	 * Which dashboard a user would get, without rendering it.
	 *
	 * @param int $user_id The user.
	 * @return Dashboard|null
	 */
	public function resolve_for( int $user_id ): ?Dashboard {
		return $this->resolver->resolve(
			$this->audience->subjects_for( $user_id ),
			$this->dashboards->all()
		);
	}

	/**
	 * Which dashboard a role would get, for the admin preview.
	 *
	 * Not impersonation: nothing signs in as anybody, and no session is touched.
	 * It answers "which template would this role resolve to", which is the
	 * question an administrator is actually asking.
	 *
	 * @param string $role The role slug.
	 * @return Dashboard|null
	 */
	public function resolve_for_role( string $role ): ?Dashboard {
		return $this->resolver->resolve(
			array( Subject::authenticated(), Subject::role( $role ) ),
			$this->dashboards->all()
		);
	}

	/**
	 * Render a particular dashboard, with a particular person's details filled in.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 * @param int       $user_id   Whose details to fill in, or 0 for none.
	 * @return string
	 */
	public function render( Dashboard $dashboard, int $user_id ): string {
		// A dashboard containing the dashboard block would otherwise render
		// itself until the request runs out of memory.
		if ( $this->rendering ) {
			return '';
		}

		$post = get_post( $dashboard->id() );

		if ( null === $post || 'publish' !== $post->post_status ) {
			return '';
		}

		$this->rendering = true;

		// Widgets are told whose dashboard this is, for the length of this
		// render and no longer. The preview screen draws somebody else's, and a
		// widget left to ask `get_current_user_id()` there would show the
		// administrator their own documents in the place a customer's go.
		if ( null !== $this->widgets ) {
			$this->widgets->drawing_for( $user_id );
		}

		try {
			$content = (string) $post->post_content;

			$html = do_blocks( $content );
			$html = wptexturize( $html );
			$html = convert_smilies( $html );
			$html = wpautop( $html );
			$html = shortcode_unautop( $html );
			$html = do_shortcode( $html );
		} finally {
			$this->rendering = false;

			if ( null !== $this->widgets ) {
				$this->widgets->drawing_for( 0 );
			}
		}

		$html = Tokens::replace( $html, self::values_for( $user_id ) );

		/**
		 * Filters a rendered dashboard.
		 *
		 * @since 0.1.0
		 *
		 * @param string    $html      The rendered dashboard.
		 * @param Dashboard $dashboard Which dashboard it is.
		 * @param int       $user_id   Whose details were filled in.
		 */
		return (string) apply_filters( 'oxyarea_dashboard_rendered', $html, $dashboard, $user_id );
	}

	/**
	 * The placeholder values for a user, escaped.
	 *
	 * Escaped here because this is the layer that knows the values are going into
	 * HTML. A display name containing a quotation mark is ordinary; a display name
	 * containing a script tag is somebody trying it on, and both are handled by
	 * the same line.
	 *
	 * @param int $user_id The user, or 0.
	 * @return array<string, string>
	 */
	public static function values_for( int $user_id ): array {
		$user = $user_id > 0 ? get_userdata( $user_id ) : false;

		if ( ! $user instanceof WP_User ) {
			// Every known placeholder resolves to nothing rather than being left
			// on the page as "{{display_name}}".
			return array_fill_keys( Tokens::known(), '' );
		}

		return array(
			'display_name' => esc_html( (string) $user->display_name ),
			'first_name'   => esc_html( (string) $user->first_name ),
			'last_name'    => esc_html( (string) $user->last_name ),
			'username'     => esc_html( (string) $user->user_login ),
			'user_email'   => esc_html( (string) $user->user_email ),
			'user_id'      => esc_html( (string) $user->ID ),
		);
	}
}
