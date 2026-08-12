<?php
/**
 * Taking a configuration out of a site, and putting one in.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tools;

use InvalidArgumentException;
use OxyArea\Access\Subject;
use OxyArea\Auth\Destination;
use OxyArea\Dashboard\DashboardPostType;
use OxyArea\Infrastructure\Settings;
use OxyArea\Persistence\DashboardRepository;
use OxyArea\Redirect\RedirectEvent;
use OxyArea\Redirect\RedirectRule;
use OxyArea\Redirect\RuleRepositoryInterface;

use const OxyArea\VERSION;

/**
 * Moves settings, redirect rules and dashboards between sites.
 *
 * Export is the easy direction. Import is where the care goes, and the rule is
 * that **a file may describe a configuration but may not impose one**: every
 * value goes through the same checks it would have faced had somebody typed it
 * into the admin. A destination on another domain is refused here exactly as it
 * is refused on the redirects screen. A rule for a role this site does not have
 * is skipped, because a rule that can never match is not a rule.
 *
 * What is skipped is counted and reported. An import that silently drops half of
 * what was in the file is worse than one that fails, because the site owner goes
 * away believing something that is not true.
 */
final class Porter {

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * The redirect rules.
	 *
	 * @var RuleRepositoryInterface
	 */
	private RuleRepositoryInterface $redirects;

	/**
	 * The dashboards.
	 *
	 * @var DashboardRepository
	 */
	private DashboardRepository $dashboards;

	/**
	 * Build the porter.
	 *
	 * @param Settings                $settings   The settings.
	 * @param RuleRepositoryInterface $redirects  The redirect rules.
	 * @param DashboardRepository     $dashboards The dashboards.
	 */
	public function __construct(
		Settings $settings,
		RuleRepositoryInterface $redirects,
		DashboardRepository $dashboards
	) {
		$this->settings   = $settings;
		$this->redirects  = $redirects;
		$this->dashboards = $dashboards;
	}

	/**
	 * Everything this site has, as a document.
	 *
	 * @return string
	 */
	public function export(): string {
		$rules = array();

		foreach ( $this->redirects->all() as $rule ) {
			$subject = $rule->subject();

			$rules[] = array(
				'event'        => $rule->event(),
				'subject_type' => null === $subject ? '' : $subject->type(),
				'subject_id'   => null === $subject ? '' : $subject->id(),
				'destination'  => $rule->destination(),
				'priority'     => $rule->priority(),
				'enabled'      => $rule->is_enabled(),
			);
		}

		$dashboards = array();

		foreach ( $this->dashboards->all() as $dashboard ) {
			$post = get_post( $dashboard->id() );

			if ( null === $post ) {
				continue;
			}

			$subject = $dashboard->subject();

			$dashboards[] = array(
				'title'    => (string) $post->post_title,
				'audience' => null === $subject
					? ''
					: ( Subject::ROLE === $subject->type() ? 'role:' . $subject->id() : $subject->type() ),
				'content'  => (string) $post->post_content,
			);
		}

		$blueprint = new Blueprint( $this->settings->all(), $rules, $dashboards );

		return $blueprint->to_json( VERSION, gmdate( 'c' ) );
	}

	/**
	 * Apply a document to this site.
	 *
	 * Adds; it does not replace. An import that wiped what was already there
	 * would make "try this template" a one-way door.
	 *
	 * @param string $json The document.
	 * @return array{settings: int, redirects: int, dashboards: int, skipped: list<string>}
	 *
	 * @throws InvalidArgumentException If the document cannot be read at all.
	 */
	public function import( string $json ): array {
		$blueprint = Blueprint::from_json( $json );

		$applied = array(
			'settings'   => 0,
			'redirects'  => 0,
			'dashboards' => 0,
			'skipped'    => array(),
		);

		$applied['settings'] = $this->import_settings( $blueprint->settings() );

		foreach ( $blueprint->redirects() as $rule ) {
			$reason = $this->import_redirect( $rule );

			if ( '' === $reason ) {
				++$applied['redirects'];

				continue;
			}

			$applied['skipped'][] = $reason;
		}

		foreach ( $blueprint->dashboards() as $dashboard ) {
			$this->import_dashboard( $dashboard );

			++$applied['dashboards'];
		}

		$this->dashboards->flush();

		return $applied;
	}

	/**
	 * Apply the settings, through the same sanitiser the settings screen uses.
	 *
	 * @param array<string, scalar> $incoming The settings from the file.
	 * @return int How many were recognised.
	 */
	private function import_settings( array $incoming ): int {
		$known = array_intersect_key( $incoming, Settings::defaults() );

		if ( array() === $known ) {
			return 0;
		}

		// A destination in a file gets the same treatment as one typed into the
		// admin: on this site, or not at all.
		if ( isset( $known['default_login_redirect'] ) ) {
			$known['default_login_redirect'] = Destination::make_safe( (string) $known['default_login_redirect'], '' );
		}

		// A page identifier from another site names a page this one does not have.
		if ( isset( $known['login_page'] ) && null === get_post( (int) $known['login_page'] ) ) {
			unset( $known['login_page'] );
		}

		$this->settings->update( $known );

		return count( $known );
	}

	/**
	 * Apply one redirect rule.
	 *
	 * @param array<string, scalar> $rule The rule from the file.
	 * @return string An empty string when it was applied, or why it was not.
	 */
	private function import_redirect( array $rule ): string {
		$event = (string) $rule['event'];

		if ( ! RedirectEvent::exists( $event ) ) {
			return sprintf(
				/* translators: %s: the event named in the file. */
				__( 'A rule for an unknown moment, "%s".', 'oxyarea' ),
				$event
			);
		}

		$destination = Destination::make_safe( (string) $rule['destination'], '' );

		if ( '' === $destination ) {
			return sprintf(
				/* translators: %s: the destination named in the file. */
				__( 'A rule pointing somewhere off this site, "%s".', 'oxyarea' ),
				(string) $rule['destination']
			);
		}

		$type = (string) $rule['subject_type'];
		$id   = (string) $rule['subject_id'];

		$subject = null;

		if ( '' !== $type ) {
			if ( Subject::ROLE === $type && null === get_role( $id ) ) {
				return sprintf(
					/* translators: %s: the role named in the file. */
					__( 'A rule for a role this site does not have, "%s".', 'oxyarea' ),
					$id
				);
			}

			$subject = new Subject( $type, $id );
		}

		$this->redirects->save(
			new RedirectRule( $event, $subject, $destination, (int) $rule['priority'], (bool) $rule['enabled'] )
		);

		return '';
	}

	/**
	 * Apply one dashboard.
	 *
	 * @param array<string, string> $dashboard The dashboard from the file.
	 * @return void
	 */
	private function import_dashboard( array $dashboard ): void {
		$post_id = wp_insert_post(
			array(
				'post_type'    => DashboardPostType::POST_TYPE,
				'post_status'  => 'draft',
				'post_title'   => sanitize_text_field( $dashboard['title'] ),
				// Through the same filter an editor's content goes through. A
				// dashboard arriving from a file is not more trusted than one typed
				// by somebody with the editor open.
				'post_content' => wp_kses_post( $dashboard['content'] ),
			)
		);

		if ( is_wp_error( $post_id ) || 0 === (int) $post_id ) {
			return;
		}

		$audience = $dashboard['audience'];

		if ( 0 === strpos( $audience, 'role:' ) && null === get_role( substr( $audience, 5 ) ) ) {
			// The dashboard is kept, with no audience, rather than thrown away: the
			// layout is the work, and the role is one select box away.
			return;
		}

		if ( '' !== $audience ) {
			update_post_meta( (int) $post_id, DashboardPostType::AUDIENCE_META, $audience );
		}
	}
}
