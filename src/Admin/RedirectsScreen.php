<?php
/**
 * The redirect rules screen.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Admin;

use InvalidArgumentException;
use OxyArea\Access\Subject;
use OxyArea\Auth\Destination;
use OxyArea\Infrastructure\Brand;
use OxyArea\Infrastructure\Registrable;
use OxyArea\Redirect\RedirectEvent;
use OxyArea\Redirect\RedirectRule;
use OxyArea\Redirect\RedirectService;
use OxyArea\Redirect\RuleRepositoryInterface;
use OxyArea\Roles\Capabilities;

/**
 * Lists the rules, adds them, removes them, and shows where each role lands.
 *
 * That last part is the one worth defending. The ordering is deterministic and
 * documented, and neither fact helps an administrator who has written four rules
 * and cannot work out why a customer keeps arriving at the shop. So the screen
 * answers the question directly, for every role on the site, using the same
 * engine that will decide it for real.
 *
 * PRO's tester goes further — arbitrary users, companies, compound conditions,
 * the full reasoning. This is the minimum needed to trust the feature at all.
 */
final class RedirectsScreen implements Registrable {

	/**
	 * The page slug.
	 */
	public const SLUG = 'oxyarea-redirects';

	/**
	 * Where the rules live.
	 *
	 * @var RuleRepositoryInterface
	 */
	private RuleRepositoryInterface $rules;

	/**
	 * The engine, for the preview.
	 *
	 * @var RedirectService
	 */
	private RedirectService $redirects;

	/**
	 * Build the screen.
	 *
	 * @param RuleRepositoryInterface $rules     Where the rules live.
	 * @param RedirectService         $redirects The engine.
	 */
	public function __construct( RuleRepositoryInterface $rules, RedirectService $redirects ) {
		$this->rules     = $rules;
		$this->redirects = $redirects;
	}

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_oxyarea_add_redirect', array( $this, 'handle_add' ) );
		add_action( 'admin_post_oxyarea_delete_redirect', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_oxyarea_toggle_redirect', array( $this, 'handle_toggle' ) );
	}

	/**
	 * Draw the screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_REDIRECTS ) ) {
			wp_die( esc_html__( 'You are not allowed to edit redirects.', 'oxyarea' ), '', array( 'response' => 403 ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Redirects', 'oxyarea' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Where people go after signing in, signing out, registering, or setting a new password. A rule about a role always beats a rule about everybody; where two rules are equally specific, the lower priority number wins.', 'oxyarea' ) . '</p>';

		Notices::show();

		$this->render_preview();

		foreach ( RedirectEvent::all() as $event ) {
			$this->render_event( $event );
		}

		$this->render_add_form();

		echo '</div>';
	}

	/**
	 * Add a rule.
	 *
	 * @return void
	 */
	public function handle_add(): void {
		check_admin_referer( 'oxyarea_add_redirect' );
		$this->require_capability();

		$event       = isset( $_POST['event'] ) ? sanitize_key( wp_unslash( $_POST['event'] ) ) : '';
		$subject_key = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$destination = isset( $_POST['destination'] ) ? sanitize_text_field( wp_unslash( $_POST['destination'] ) ) : '';
		$priority    = isset( $_POST['priority'] ) ? absint( wp_unslash( $_POST['priority'] ) ) : 10;

		$safe = Destination::make_safe( $destination, '' );

		// Refused at the point of writing, not silently corrected at the point of
		// use. Somebody who typed another site's address should be told, once,
		// rather than wonder for a fortnight why their rule does nothing.
		if ( '' === $safe ) {
			Notices::remember(
				'error',
				esc_html__( 'A destination has to be a page on this site: either a path beginning with a slash, or a full address on this domain.', 'oxyarea' )
			);

			$this->go_back();
		}

		try {
			$this->rules->save(
				new RedirectRule( $event, $this->subject_from( $subject_key ), $safe, $priority )
			);
		} catch ( InvalidArgumentException $e ) {
			Notices::remember( 'error', esc_html( $e->getMessage() ) );

			$this->go_back();
		}

		Notices::remember( 'success', esc_html__( 'Rule added.', 'oxyarea' ) );

		$this->go_back();
	}

	/**
	 * Remove a rule.
	 *
	 * @return void
	 */
	public function handle_delete(): void {
		check_admin_referer( 'oxyarea_delete_redirect' );
		$this->require_capability();

		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;

		if ( $id > 0 ) {
			$this->rules->delete( $id );
			Notices::remember( 'success', esc_html__( 'Rule removed.', 'oxyarea' ) );
		}

		$this->go_back();
	}

	/**
	 * Turn a rule on or off.
	 *
	 * @return void
	 */
	public function handle_toggle(): void {
		check_admin_referer( 'oxyarea_toggle_redirect' );
		$this->require_capability();

		$id      = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$enabled = isset( $_POST['enabled'] ) && '1' === sanitize_key( wp_unslash( $_POST['enabled'] ) );

		if ( $id > 0 ) {
			$this->rules->set_enabled( $id, $enabled );
			Notices::remember( 'success', esc_html__( 'Saved.', 'oxyarea' ) );
		}

		$this->go_back();
	}

	/**
	 * Where each role currently lands after signing in.
	 *
	 * @return void
	 */
	private function render_preview(): void {
		echo '<h2>' . esc_html__( 'Where people land after signing in', 'oxyarea' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Worked out with the same engine that will decide it for real. A role with two rules shows the one that wins.', 'oxyarea' ) . '</p>';

		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Role', 'oxyarea' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Lands on', 'oxyarea' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Decided by', 'oxyarea' ) . '</th>';
		echo '</tr></thead><tbody>';

		$home = home_url( '/' );

		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$resolution = $this->redirects->resolve(
				RedirectEvent::LOGIN,
				array( Subject::authenticated(), Subject::role( (string) $slug ) ),
				$home
			);

			$rule = $resolution->rule();

			echo '<tr>';
			echo '<td>' . esc_html( translate_user_role( (string) $name ) ) . '</td>';
			echo '<td><code>' . esc_html( $resolution->destination() ) . '</code></td>';
			echo '<td>';

			if ( null === $rule ) {
				echo '<em>' . esc_html__( 'no rule — the front page', 'oxyarea' ) . '</em>';
			} else {
				echo esc_html( $rule->subject_key() );

				if ( $resolution->was_contested() ) {
					echo ' <span class="description">';
					printf(
						/* translators: %d: how many other rules also matched. */
						esc_html( _n( '(%d other rule also matched)', '(%d other rules also matched)', count( $resolution->candidates() ) - 1, 'oxyarea' ) ),
						(int) ( count( $resolution->candidates() ) - 1 )
					);
					echo '</span>';
				}
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * The rules for one moment.
	 *
	 * @param string $event The moment.
	 * @return void
	 */
	private function render_event( string $event ): void {
		$rules = $this->rules->for_event( $event );

		echo '<h2>' . esc_html( $this->event_label( $event ) ) . '</h2>';

		if ( array() === $rules ) {
			echo '<p class="description">' . esc_html__( 'No rules yet. WordPress decides.', 'oxyarea' ) . '</p>';

			return;
		}

		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Who', 'oxyarea' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Goes to', 'oxyarea' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Priority', 'oxyarea' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'On', 'oxyarea' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Remove', 'oxyarea' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rules as $rule ) {
			echo '<tr>';
			echo '<td>' . esc_html( $this->subject_label( $rule ) ) . '</td>';
			echo '<td><code>' . esc_html( $rule->destination() ) . '</code></td>';
			echo '<td>' . esc_html( (string) $rule->priority() ) . '</td>';

			echo '<td>';
			$this->render_action_form(
				'oxyarea_toggle_redirect',
				$rule->id(),
				$rule->is_enabled() ? __( 'Turn off', 'oxyarea' ) : __( 'Turn on', 'oxyarea' ),
				'',
				array( 'enabled' => $rule->is_enabled() ? '0' : '1' )
			);
			echo '</td>';

			echo '<td>';
			$this->render_action_form( 'oxyarea_delete_redirect', $rule->id(), __( 'Remove', 'oxyarea' ), 'delete' );
			echo '</td>';

			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * A one-button form.
	 *
	 * @param string                $action Nonce and admin-post action.
	 * @param int                   $id     The rule.
	 * @param string                $label  The button.
	 * @param string                $button_class Extra button class.
	 * @param array<string, string> $extra  Further hidden fields.
	 * @return void
	 */
	private function render_action_form( string $action, int $id, string $label, string $button_class = '', array $extra = array() ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
		wp_nonce_field( $action );
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
		echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '" />';

		foreach ( $extra as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
		}

		echo '<button type="submit" class="button button-small ' . esc_attr( $button_class ) . '">' . esc_html( $label ) . '</button>';
		echo '</form>';
	}

	/**
	 * The form that adds a rule.
	 *
	 * @return void
	 */
	private function render_add_form(): void {
		echo '<h2>' . esc_html__( 'Add a rule', 'oxyarea' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'oxyarea_add_redirect' );
		echo '<input type="hidden" name="action" value="oxyarea_add_redirect" />';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="oxyarea-redirect-event">' . esc_html__( 'After', 'oxyarea' ) . '</label></th><td>';
		echo '<select name="event" id="oxyarea-redirect-event">';
		foreach ( RedirectEvent::all() as $event ) {
			echo '<option value="' . esc_attr( $event ) . '">' . esc_html( $this->event_label( $event ) ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="oxyarea-redirect-subject">' . esc_html__( 'Who', 'oxyarea' ) . '</label></th><td>';
		echo '<select name="subject" id="oxyarea-redirect-subject">';
		echo '<option value="">' . esc_html__( 'Everybody (the fallback)', 'oxyarea' ) . '</option>';
		echo '<option value="authenticated">' . esc_html__( 'Anybody signed in', 'oxyarea' ) . '</option>';
		echo '<option value="anonymous">' . esc_html__( 'Anybody not signed in', 'oxyarea' ) . '</option>';

		foreach ( wp_roles()->get_names() as $slug => $name ) {
			echo '<option value="' . esc_attr( 'role:' . $slug ) . '">'
				. esc_html( sprintf( /* translators: %s: role name. */ __( 'Role: %s', 'oxyarea' ), translate_user_role( (string) $name ) ) )
				. '</option>';
		}

		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="oxyarea-redirect-destination">' . esc_html__( 'Goes to', 'oxyarea' ) . '</label></th><td>';
		echo '<input name="destination" id="oxyarea-redirect-destination" type="text" class="regular-text" placeholder="/customers/" required />';
		echo '<p class="description">' . esc_html__( 'A path on this site, beginning with a slash. Addresses on other domains are refused.', 'oxyarea' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="oxyarea-redirect-priority">' . esc_html__( 'Priority', 'oxyarea' ) . '</label></th><td>';
		echo '<input name="priority" id="oxyarea-redirect-priority" type="number" value="10" min="0" max="9999" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'Only consulted between rules that are equally specific. Lower goes first, as with a WordPress hook.', 'oxyarea' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button( __( 'Add rule', 'oxyarea' ) );
		echo '</form>';
	}

	/**
	 * Turn a select value into a subject.
	 *
	 * @param string $key The posted value.
	 * @return Subject|null
	 */
	private function subject_from( string $key ): ?Subject {
		if ( '' === $key ) {
			return null;
		}

		if ( 'authenticated' === $key ) {
			return Subject::authenticated();
		}

		if ( 'anonymous' === $key ) {
			return Subject::anonymous();
		}

		if ( 0 === strpos( $key, 'role:' ) ) {
			return Subject::role( sanitize_key( substr( $key, 5 ) ) );
		}

		return null;
	}

	/**
	 * How a rule's audience reads.
	 *
	 * @param RedirectRule $rule The rule.
	 * @return string
	 */
	private function subject_label( RedirectRule $rule ): string {
		$subject = $rule->subject();

		if ( null === $subject ) {
			return __( 'Everybody', 'oxyarea' );
		}

		switch ( $subject->type() ) {
			case Subject::AUTHENTICATED:
				return __( 'Anybody signed in', 'oxyarea' );
			case Subject::ANONYMOUS:
				return __( 'Anybody not signed in', 'oxyarea' );
			case Subject::ROLE:
				$names = wp_roles()->get_names();
				$name  = $names[ $subject->id() ] ?? $subject->id();

				return sprintf( /* translators: %s: role name. */ __( 'Role: %s', 'oxyarea' ), translate_user_role( (string) $name ) );
			default:
				return $subject->key();
		}
	}

	/**
	 * How a moment reads.
	 *
	 * @param string $event The moment.
	 * @return string
	 */
	private function event_label( string $event ): string {
		switch ( $event ) {
			case RedirectEvent::LOGIN:
				return __( 'After signing in', 'oxyarea' );
			case RedirectEvent::LOGOUT:
				return __( 'After signing out', 'oxyarea' );
			case RedirectEvent::REGISTRATION:
				return __( 'After registering', 'oxyarea' );
			case RedirectEvent::PASSWORD_RESET:
				return __( 'After setting a new password', 'oxyarea' );
			default:
				return $event;
		}
	}

	/**
	 * Refuse anybody who may not edit redirects.
	 *
	 * @return void
	 */
	private function require_capability(): void {
		if ( ! current_user_can( Capabilities::MANAGE_REDIRECTS ) ) {
			wp_die( esc_html__( 'You are not allowed to edit redirects.', 'oxyarea' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Back to the screen.
	 *
	 * @return never
	 */
	private function go_back() {
		wp_safe_redirect( add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) ) );

		exit;
	}
}
