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

		/**
		 * What add-ons want kept in the export.
		 *
		 * Keyed by whoever is contributing — `oxyarea-pro` and so on — and
		 * carried through this plugin untouched. A blueprint is a site's whole
		 * configuration or it is a curiosity, and asking somebody to export
		 * twice and remember which file was which is how a migration goes wrong.
		 *
		 * Files are deliberately not in it, here or anywhere: an export is a
		 * configuration document, and the day it starts carrying a client's
		 * contracts is the day emailing one becomes a disclosure.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, array<string, mixed>> $extras The sections gathered so far.
		 */
		$extras = apply_filters( 'oxyarea_blueprint_extras', array() );

		$blueprint = new Blueprint(
			$this->settings->all(),
			$rules,
			$dashboards,
			is_array( $extras ) ? $extras : array()
		);

		return $blueprint->to_json( VERSION, gmdate( 'c' ) );
	}

	/**
	 * Apply a document to this site.
	 *
	 * Adds; it does not replace. An import that wiped what was already there
	 * would make "try this template" a one-way door.
	 *
	 * @param string $json The document.
	 * @return array{settings: int, redirects: int, dashboards: int, skipped: list<string>, notes: list<string>}
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
			'notes'      => array(),
		);

		$applied['settings'] = $this->import_settings( $blueprint->settings() );

		/**
		 * Let add-ons apply their own sections.
		 *
		 * Fired before this plugin's own rules and dashboards, so that anything
		 * they refer to — a company a rule names — exists by the time the rule
		 * is read.
		 *
		 * An add-on that is not installed simply does not listen, and its
		 * section is left alone rather than dropped: the file still carries it,
		 * and installing the add-on later applies it.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, array<string, mixed>> $extras What the document carried.
		 */
		do_action( 'oxyarea_blueprint_import_extras', $blueprint->extras() );

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

		/**
		 * The report an import is about to show.
		 *
		 * An add-on that applied a section of its own says here what it did and
		 * what it had to leave out, so that the site owner reads one account of
		 * the import rather than two. An add-on that reports nothing is an
		 * add-on whose half of the file silently did not arrive.
		 *
		 * The shape is put back together afterwards: a filter that returns
		 * something else, or a count that came back as a sentence, must not
		 * become a fatal on the screen that was about to say the import worked.
		 *
		 * @since 0.1.0
		 *
		 * @param array{settings: int, redirects: int, dashboards: int, skipped: list<string>, notes: list<string>} $applied What this plugin did.
		 */
		return self::clean_report( apply_filters( 'oxyarea_blueprint_import_report', $applied ), $applied );
	}

	/**
	 * Put a report back into the shape the screen expects.
	 *
	 * `skipped` and `notes` are two lists on purpose. What did not arrive turns
	 * the notice red; what an add-on did arrive with does not. Sharing one list
	 * would make a successful import look like a failed one, or a failed one
	 * look successful, depending on which way the mistake went.
	 *
	 * @param mixed                                                                                             $filtered What came back from the filter.
	 * @param array{settings: int, redirects: int, dashboards: int, skipped: list<string>, notes: list<string>} $applied What this plugin did.
	 * @return array{settings: int, redirects: int, dashboards: int, skipped: list<string>, notes: list<string>}
	 */
	private static function clean_report( $filtered, array $applied ): array {
		if ( ! is_array( $filtered ) ) {
			return $applied;
		}

		return array(
			'settings'   => isset( $filtered['settings'] ) && is_numeric( $filtered['settings'] ) ? (int) $filtered['settings'] : $applied['settings'],
			'redirects'  => isset( $filtered['redirects'] ) && is_numeric( $filtered['redirects'] ) ? (int) $filtered['redirects'] : $applied['redirects'],
			'dashboards' => isset( $filtered['dashboards'] ) && is_numeric( $filtered['dashboards'] ) ? (int) $filtered['dashboards'] : $applied['dashboards'],
			'skipped'    => self::clean_lines( $filtered['skipped'] ?? array() ),
			'notes'      => self::clean_lines( $filtered['notes'] ?? array() ),
		);
	}

	/**
	 * Keep the sentences, drop everything else.
	 *
	 * @param mixed $raw What the filter put there.
	 * @return list<string>
	 */
	private static function clean_lines( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$lines = array();

		foreach ( $raw as $line ) {
			if ( is_string( $line ) && '' !== trim( $line ) ) {
				$lines[] = $line;
			}
		}

		return $lines;
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
