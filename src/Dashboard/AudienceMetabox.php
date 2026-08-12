<?php
/**
 * Choosing who a dashboard is for.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Dashboard;

use OxyArea\Access\SubjectCodec;
use OxyArea\Infrastructure\Registrable;
use OxyArea\Persistence\DashboardRepository;
use OxyArea\Roles\Capabilities;

/**
 * The one field a dashboard has beyond its title and its content.
 *
 * A select, not free text. The whole audience model is a closed set in the free
 * plugin — the site default, everybody signed in, or one role — and offering a
 * text box would mean accepting values nobody can act on and then deciding what
 * to do with them.
 */
final class AudienceMetabox implements Registrable {

	/**
	 * The nonce action.
	 */
	private const NONCE = 'oxyarea_dashboard_audience';

	/**
	 * The dashboards, so the cache can be dropped when one changes.
	 *
	 * @var DashboardRepository
	 */
	private DashboardRepository $dashboards;

	/**
	 * Subjects, to and from form values.
	 *
	 * @var SubjectCodec
	 */
	private SubjectCodec $codec;

	/**
	 * Build the metabox.
	 *
	 * @param DashboardRepository $dashboards The dashboards.
	 * @param SubjectCodec        $codec      Subjects, to and from form values.
	 */
	public function __construct( DashboardRepository $dashboards, SubjectCodec $codec ) {
		$this->dashboards = $dashboards;
		$this->codec      = $codec;
	}

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post_' . DashboardPostType::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'deleted_post', array( $this, 'flush' ) );
		add_action( 'trashed_post', array( $this, 'flush' ) );
		add_action( 'untrashed_post', array( $this, 'flush' ) );
	}

	/**
	 * Register the box.
	 *
	 * @return void
	 */
	public function add(): void {
		add_meta_box(
			'oxyarea-dashboard-audience',
			__( 'Who this dashboard is for', 'oxyarea' ),
			array( $this, 'render' ),
			DashboardPostType::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Draw the box.
	 *
	 * @param \WP_Post $post The dashboard being edited.
	 * @return void
	 */
	public function render( $post ): void {
		$current = (string) get_post_meta( (int) $post->ID, DashboardPostType::AUDIENCE_META, true );

		wp_nonce_field( self::NONCE, '_oxyarea_audience_nonce' );

		echo '<p><label class="screen-reader-text" for="oxyarea-audience">'
			. esc_html__( 'Who this dashboard is for', 'oxyarea' )
			. '</label>';

		echo '<select name="oxyarea_audience" id="oxyarea-audience" style="width:100%">';

		echo '<option value=""' . selected( $current, '', false ) . '>'
			. esc_html__( 'The site default', 'oxyarea' ) . '</option>';

		echo '<option value="authenticated"' . selected( $current, 'authenticated', false ) . '>'
			. esc_html__( 'Anybody signed in', 'oxyarea' ) . '</option>';

		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$value = 'role:' . $slug;

			echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>'
				. esc_html( sprintf( /* translators: %s: role name. */ __( 'Role: %s', 'oxyarea' ), translate_user_role( (string) $name ) ) )
				. '</option>';
		}

		echo '</select></p>';

		// An add-on may name audiences this list cannot hold. A site with five
		// thousand customers cannot have all of them in an option list, so what
		// is offered here is the choice this plugin can draw, and anything else
		// draws its own control.
		$this->codec->render_extra_controls( 'dashboard', '' === $current ? array() : array( $current ) );

		echo '<p class="description">'
			. esc_html__( 'One template serves everybody who holds the role. The site default is what somebody sees when their role has nothing of its own.', 'oxyarea' )
			. '</p>';

		echo '<p class="description">'
			. esc_html__( 'Placeholders you can use in the content:', 'oxyarea' )
			. ' <code>{{' . esc_html( implode( '}}</code> <code>{{', Tokens::known() ) ) . '}}</code></p>';
	}

	/**
	 * Save the choice.
	 *
	 * @param int      $post_id The dashboard.
	 * @param \WP_Post $post    The dashboard.
	 * @return void
	 */
	public function save( $post_id, $post ): void {
		unset( $post );

		$post_id = (int) $post_id;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$nonce = isset( $_POST['_oxyarea_audience_nonce'] )
			? sanitize_key( wp_unslash( $_POST['_oxyarea_audience_nonce'] ) )
			: '';

		// A save that did not come from our box leaves the value alone. Quick
		// edit, the REST API and wp-cli all reach this hook without it.
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( Capabilities::MANAGE_DASHBOARDS ) ) {
			return;
		}

		$chosen = isset( $_POST['oxyarea_audience'] )
			? sanitize_text_field( wp_unslash( $_POST['oxyarea_audience'] ) )
			: '';

		$gathered = $this->codec->gather( '' === $chosen ? array() : array( $chosen ), 'dashboard' );

		// A dashboard has one audience, so the last word wins. An add-on's own
		// control is appended after this plugin's, which is what makes "choose a
		// person" override "choose a role" rather than the other way round —
		// the more specific choice is the one somebody went out of their way to
		// make.
		$chosen = $this->clean(
			array() === $gathered ? '' : (string) $gathered[ array_key_last( $gathered ) ]
		);

		if ( '' === $chosen ) {
			delete_post_meta( $post_id, DashboardPostType::AUDIENCE_META );
		} else {
			update_post_meta( $post_id, DashboardPostType::AUDIENCE_META, $chosen );
		}

		$this->dashboards->flush();
	}

	/**
	 * Drop the cached list when a dashboard comes or goes.
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->dashboards->flush();
	}

	/**
	 * Narrow a posted audience to one of the values that mean something.
	 *
	 * @param string $chosen What was posted.
	 * @return string
	 */
	private function clean( string $chosen ): string {
		$subject = $this->codec->decode( $chosen );

		return null === $subject ? '' : $this->codec->encode( $subject );
	}
}
