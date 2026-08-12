<?php
/**
 * Putting the engine where WordPress decides.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Redirect;

use OxyArea\Access\AudienceResolver;
use OxyArea\Access\Subject;
use OxyArea\Auth\Destination;
use OxyArea\Infrastructure\Registrable;
use OxyArea\Infrastructure\Settings;
use OxyArea\Roles\Capabilities;
use WP_User;

/**
 * Answers WordPress's "where now?" questions with the site's own rules.
 *
 * Two sets of hooks, deliberately. WordPress's own — `login_redirect`,
 * `logout_redirect`, `registration_redirect` — so that the rules apply even to
 * somebody who reached wp-login.php directly, or to another plugin's form. And
 * OxyArea's own filters, so the frontend forms from the last sprint go through
 * the same engine rather than a second copy of the same idea.
 *
 * Every destination leaves through Destination::make_safe, however it was
 * decided. A rule typed into the admin by somebody who has been careless, or a
 * destination injected by a filter, gets the identical treatment as one that
 * arrived in a query string.
 */
final class RedirectService implements Registrable {

	/**
	 * The engine.
	 *
	 * @var RedirectResolver
	 */
	private RedirectResolver $resolver;

	/**
	 * Where the rules live.
	 *
	 * @var RuleRepositoryInterface
	 */
	private RuleRepositoryInterface $rules;

	/**
	 * What people count as.
	 *
	 * @var AudienceResolver
	 */
	private AudienceResolver $audience;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Build the service.
	 *
	 * @param RedirectResolver        $resolver The engine.
	 * @param RuleRepositoryInterface $rules    Where the rules live.
	 * @param AudienceResolver        $audience What people count as.
	 * @param Settings                $settings The settings.
	 */
	public function __construct(
		RedirectResolver $resolver,
		RuleRepositoryInterface $rules,
		AudienceResolver $audience,
		Settings $settings
	) {
		$this->resolver = $resolver;
		$this->rules    = $rules;
		$this->audience = $audience;
		$this->settings = $settings;
	}

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'login_redirect', array( $this, 'after_login' ), 20, 3 );
		add_filter( 'logout_redirect', array( $this, 'after_logout' ), 20, 3 );
		add_filter( 'registration_redirect', array( $this, 'after_registration' ), 20 );

		add_filter( 'oxyarea_login_destination', array( $this, 'after_login_form' ), 10, 3 );
		add_filter( 'oxyarea_logout_destination', array( $this, 'after_logout_form' ), 10, 2 );
		add_filter( 'oxyarea_password_reset_destination', array( $this, 'after_password_reset' ), 10, 2 );

		add_action( 'admin_init', array( $this, 'keep_out_of_the_admin' ) );
	}

	/**
	 * Where somebody goes after signing in.
	 *
	 * @param string        $destination Where WordPress was going to send them.
	 * @param string        $requested   What the request asked for.
	 * @param WP_User|mixed $user        Who signed in, or an error.
	 * @return string
	 */
	public function after_login( string $destination, string $requested, $user ): string {
		if ( ! $user instanceof WP_User ) {
			return $destination;
		}

		// Somebody who asked for a particular page, and may have it, keeps it. The
		// rules decide where people go by default, not where they are allowed to
		// go, and overriding an explicit request would break every "you must sign
		// in to see this, we will bring you back" link on the site.
		if ( '' !== trim( $requested ) ) {
			return $destination;
		}

		return $this->decide( RedirectEvent::LOGIN, (int) $user->ID, $destination );
	}

	/**
	 * Where somebody goes after signing out.
	 *
	 * @param string        $destination Where WordPress was going to send them.
	 * @param string        $requested   What the request asked for.
	 * @param WP_User|mixed $user        Who signed out.
	 * @return string
	 */
	public function after_logout( string $destination, string $requested, $user ): string {
		if ( '' !== trim( $requested ) ) {
			return $destination;
		}

		$user_id = $user instanceof WP_User ? (int) $user->ID : 0;

		return $this->decide( RedirectEvent::LOGOUT, $user_id, $destination );
	}

	/**
	 * Where somebody goes after registering.
	 *
	 * They are not signed in yet, so there is no user to ask about. The rules are
	 * matched against the role the site gives new accounts, which is the only
	 * thing that is actually known at this moment, plus "not signed in".
	 *
	 * @param string $destination Where WordPress was going to send them.
	 * @return string
	 */
	public function after_registration( string $destination ): string {
		$subjects = array( Subject::anonymous() );
		$default  = (string) get_option( 'default_role', '' );

		if ( '' !== $default ) {
			$subjects[] = Subject::role( $default );
		}

		return $this->decide_for_subjects( RedirectEvent::REGISTRATION, $subjects, $destination );
	}

	/**
	 * Where somebody goes after signing in through OxyArea's own form.
	 *
	 * Somebody who asked for a particular page keeps it, exactly as on
	 * WordPress's own login_redirect. The two paths into this plugin have to
	 * agree about that, or where a person lands depends on which form they
	 * happened to use.
	 *
	 * @param string        $destination Where the form was going to send them.
	 * @param WP_User|mixed $user        Who signed in.
	 * @param string        $requested   What the request asked for, empty if it asked for nothing.
	 * @return string
	 */
	public function after_login_form( string $destination, $user, string $requested = '' ): string {
		if ( ! $user instanceof WP_User || '' !== trim( $requested ) ) {
			return $destination;
		}

		return $this->decide( RedirectEvent::LOGIN, (int) $user->ID, $destination );
	}

	/**
	 * Where somebody goes after signing out through OxyArea's own form.
	 *
	 * Decided *before* the session ends, while there is still somebody to ask
	 * about. Asking afterwards would match every rule against "not signed in" and
	 * make a per-role sign-out rule impossible.
	 *
	 * @param string $destination Where the form was going to send them.
	 * @param int    $user_id     Who is signing out.
	 * @return string
	 */
	public function after_logout_form( string $destination, int $user_id ): string {
		return $this->decide( RedirectEvent::LOGOUT, $user_id, $destination );
	}

	/**
	 * Where somebody goes after setting a new password.
	 *
	 * @param string $destination Where the form was going to send them.
	 * @param int    $user_id     Whose password it was.
	 * @return string
	 */
	public function after_password_reset( string $destination, int $user_id ): string {
		return $this->decide( RedirectEvent::PASSWORD_RESET, $user_id, $destination );
	}

	/**
	 * Send people who have no business in wp-admin back to the front of the site.
	 *
	 * Off unless the setting is on. Anybody who can write a post, or who
	 * administers OxyArea, is left alone: this exists to stop a customer landing
	 * on the dashboard, not to lock staff out of their own site.
	 *
	 * @return void
	 */
	public function keep_out_of_the_admin(): void {
		if ( ! $this->settings->get( 'block_admin_access', false ) ) {
			return;
		}

		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}

		if ( current_user_can( 'edit_posts' ) || current_user_can( Capabilities::MANAGE ) ) {
			return;
		}

		$destination = $this->decide( RedirectEvent::LOGIN, get_current_user_id(), home_url( '/' ) );

		wp_safe_redirect( Destination::make_safe( $destination, home_url( '/' ) ) );

		exit;
	}

	/**
	 * Decide, for a user.
	 *
	 * @param string $event    The moment.
	 * @param int    $user_id  Who it is about, or 0.
	 * @param string $fallback Where to go when no rule applies.
	 * @return string
	 */
	public function decide( string $event, int $user_id, string $fallback ): string {
		return $this->decide_for_subjects( $event, $this->audience->subjects_for( $user_id ), $fallback );
	}

	/**
	 * Decide, for a set of subjects.
	 *
	 * @param string        $event    The moment.
	 * @param list<Subject> $subjects What the person counts as.
	 * @param string        $fallback Where to go when no rule applies.
	 * @return string
	 */
	public function decide_for_subjects( string $event, array $subjects, string $fallback ): string {
		$resolution = $this->resolve( $event, $subjects, $fallback );

		return Destination::make_safe( $resolution->destination(), $fallback );
	}

	/**
	 * The full answer, with the rules that were considered.
	 *
	 * Public because the admin screen shows it. Nothing is redirected here.
	 *
	 * @param string        $event    The moment.
	 * @param list<Subject> $subjects What the person counts as.
	 * @param string        $fallback Where to go when no rule applies.
	 * @return Resolution
	 */
	public function resolve( string $event, array $subjects, string $fallback ): Resolution {
		return $this->resolver->resolve( $event, $subjects, $this->rules->for_event( $event ), $fallback );
	}
}
