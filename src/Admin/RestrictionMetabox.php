<?php
/**
 * Saying who may read a page.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Admin;

use OxyArea\Access\Assignment;
use OxyArea\Access\AssignmentRepositoryInterface;
use OxyArea\Access\ProtectedResource;
use OxyArea\Access\Subject;
use OxyArea\Access\SubjectCodec;
use OxyArea\Content\Restrictions;
use OxyArea\Infrastructure\Registrable;
use WP_Post;

/**
 * A box on the editor: everybody, or these roles.
 *
 * Restriction is stored as assignments, the same rows the access resolver reads
 * for everything else. There is no second table and no "is private" flag to fall
 * out of step with the rules: a page with no rows is public, a page with rows is
 * private to whoever the rows name.
 *
 * Only post types that are public get the box. Restricting a post type nobody
 * can reach on the front of the site would be an option that does nothing, and
 * OxyArea's own dashboards are already unreachable by design.
 */
final class RestrictionMetabox implements Registrable {

	/**
	 * The nonce action.
	 */
	private const NONCE = 'oxyarea_restriction';

	/**
	 * Where the rules live.
	 *
	 * @var AssignmentRepositoryInterface
	 */
	private AssignmentRepositoryInterface $assignments;

	/**
	 * What is restricted at all.
	 *
	 * @var Restrictions
	 */
	private Restrictions $restrictions;

	/**
	 * Subjects, to and from form values.
	 *
	 * @var SubjectCodec
	 */
	private SubjectCodec $codec;

	/**
	 * Build the box.
	 *
	 * @param AssignmentRepositoryInterface $assignments  Where the rules live.
	 * @param Restrictions                  $restrictions What is restricted at all.
	 * @param SubjectCodec                  $codec        Subjects, to and from form values.
	 */
	public function __construct( AssignmentRepositoryInterface $assignments, Restrictions $restrictions, SubjectCodec $codec ) {
		$this->assignments  = $assignments;
		$this->restrictions = $restrictions;
		$this->codec        = $codec;
	}

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Register the box on every public post type.
	 *
	 * @return void
	 */
	public function add(): void {
		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
			add_meta_box(
				'oxyarea-restriction',
				__( 'Who can see this', 'oxyarea' ),
				array( $this, 'render' ),
				(string) $post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Draw the box.
	 *
	 * @param WP_Post $post The post being edited.
	 * @return void
	 */
	public function render( $post ): void {
		$chosen = $this->chosen_subjects( (int) $post->ID );

		wp_nonce_field( self::NONCE, '_oxyarea_restriction_nonce' );

		echo '<p><label><input type="radio" name="oxyarea_restrict" value="0"' . checked( array(), $chosen, false ) . ' /> '
			. esc_html__( 'Everybody — this page is public', 'oxyarea' ) . '</label></p>';

		echo '<p><label><input type="radio" name="oxyarea_restrict" value="1"' . checked( array() !== $chosen, true, false ) . ' /> '
			. esc_html__( 'Only these people:', 'oxyarea' ) . '</label></p>';

		echo '<div style="margin-inline-start:1.5rem">';

		echo '<p><label><input type="checkbox" name="oxyarea_subjects[]" value="authenticated"'
			. checked( in_array( 'authenticated', $chosen, true ), true, false ) . ' /> '
			. esc_html__( 'Anybody signed in', 'oxyarea' ) . '</label></p>';

		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$value = 'role:' . $slug;

			echo '<p><label><input type="checkbox" name="oxyarea_subjects[]" value="' . esc_attr( $value ) . '"'
				. checked( in_array( $value, $chosen, true ), true, false ) . ' /> '
				. esc_html( translate_user_role( (string) $name ) ) . '</label></p>';
		}

		$this->codec->render_extra_controls( 'restriction', $chosen );

		echo '</div>';

		echo '<p class="description">'
			. esc_html__( 'A restricted page is kept out of search, feeds, the REST API and the sitemap as well as being refused when it is opened directly.', 'oxyarea' )
			. '</p>';
	}

	/**
	 * Save the choice.
	 *
	 * @param int     $post_id The post.
	 * @param WP_Post $post    The post.
	 * @return void
	 */
	public function save( $post_id, $post = null ): void {
		$post_id = (int) $post_id;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST['_oxyarea_restriction_nonce'] )
			? sanitize_key( wp_unslash( $_POST['_oxyarea_restriction_nonce'] ) )
			: '';

		// A save that did not come from our box leaves the rules alone. Quick
		// edit, the REST API and WP-CLI all reach this hook without it, and none
		// of them means "make this page public".
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		unset( $post );

		$restrict = isset( $_POST['oxyarea_restrict'] ) && '1' === sanitize_key( wp_unslash( $_POST['oxyarea_restrict'] ) );

		$posted = isset( $_POST['oxyarea_subjects'] ) && is_array( $_POST['oxyarea_subjects'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['oxyarea_subjects'] ) )
			: array();

		$assignments = array();

		if ( $restrict ) {
			foreach ( $this->codec->gather( array_values( $posted ), 'restriction' ) as $value ) {
				$subject = $this->codec->decode( (string) $value );

				if ( null !== $subject ) {
					$assignments[] = new Assignment( $subject );
				}
			}
		}

		// "Restricted, but nobody named" is stored as exactly that: rows exist, so
		// the page is private, and none of them matches anybody, so nobody gets
		// in. Quietly turning it back into a public page would be the opposite of
		// what the person clicking it asked for.
		if ( $restrict && array() === $assignments ) {
			$assignments[] = new Assignment( new Subject( Subject::ROLE, '__nobody__' ) );
		}

		$this->assignments->replace_for_resource( ProtectedResource::post( $post_id ), $assignments );
		$this->restrictions->flush();
	}

	/**
	 * Who is currently named on a post.
	 *
	 * @param int $post_id The post.
	 * @return list<string>
	 */
	private function chosen_subjects( int $post_id ): array {
		$chosen = array();

		foreach ( $this->assignments->for_resource( ProtectedResource::post( $post_id ) ) as $assignment ) {
			$value = $this->codec->encode( $assignment->subject() );

			if ( '' !== $value ) {
				$chosen[] = $value;
			}
		}

		return $chosen;
	}
}
