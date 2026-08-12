<?php
/**
 * Which dashboard somebody gets.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Dashboard;

use OxyArea\Access\Subject;

/**
 * Picks one dashboard from the set, for the subjects a person presents.
 *
 * Same ordering as the redirect engine — specificity, then age — for the same
 * reason: two screens that answer "which one wins?" differently would teach a
 * site owner one rule and then break it.
 *
 * There is no priority field here, and that is a decision rather than an
 * omission. A redirect rule can plausibly need reordering against another of
 * equal specificity, because somebody may hold two roles and both rules are
 * about where to go. A dashboard is a page: two dashboards for the same role is
 * a mistake being made, not a preference being expressed, and inventing a
 * tie-break field would make the mistake configurable instead of visible.
 */
final class DashboardResolver {

	/**
	 * The dashboard for a person, or null if there is nothing for them.
	 *
	 * @param list<Subject>   $subjects   What the person counts as.
	 * @param list<Dashboard> $dashboards The dashboards to choose from.
	 * @return Dashboard|null
	 */
	public function resolve( array $subjects, array $dashboards ): ?Dashboard {
		$candidates = $this->candidates( $subjects, $dashboards );

		return $candidates[0] ?? null;
	}

	/**
	 * Every dashboard that applies, best first.
	 *
	 * @param list<Subject>   $subjects   What the person counts as.
	 * @param list<Dashboard> $dashboards The dashboards to choose from.
	 * @return list<Dashboard>
	 */
	public function candidates( array $subjects, array $dashboards ): array {
		$matching = array();

		foreach ( $dashboards as $dashboard ) {
			if ( ! $dashboard instanceof Dashboard ) {
				continue;
			}

			if ( $this->applies_to( $dashboard, $subjects ) ) {
				$matching[] = $dashboard;
			}
		}

		usort(
			$matching,
			static function ( Dashboard $a, Dashboard $b ): int {
				$specificity = $b->specificity() <=> $a->specificity();

				return 0 !== $specificity ? $specificity : $a->id() <=> $b->id();
			}
		);

		return $matching;
	}

	/**
	 * Whether a dashboard is for somebody who presents these subjects.
	 *
	 * @param Dashboard     $dashboard The dashboard.
	 * @param list<Subject> $subjects  What the person counts as.
	 * @return bool
	 */
	private function applies_to( Dashboard $dashboard, array $subjects ): bool {
		$subject = $dashboard->subject();

		if ( null === $subject ) {
			// The default dashboard is for signed-in people. Serving it to a
			// visitor who is not signed in would put whatever a site owner wrote
			// for their customers on the open internet.
			foreach ( $subjects as $presented ) {
				if ( $presented instanceof Subject && Subject::AUTHENTICATED === $presented->type() ) {
					return true;
				}
			}

			return false;
		}

		foreach ( $subjects as $presented ) {
			if ( $presented instanceof Subject && $presented->equals( $subject ) ) {
				return true;
			}
		}

		return false;
	}
}
