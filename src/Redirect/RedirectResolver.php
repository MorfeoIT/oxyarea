<?php
/**
 * Which rule wins.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Redirect;

use OxyArea\Access\Subject;

/*
 * Pure, like the access resolver and the redirect guard. The ordering rules
 * below are the whole feature, and an ordering that cannot be tested
 * exhaustively is an ordering nobody can rely on.
 */

/**
 * Picks the destination for a moment, from the rules that apply to a person.
 *
 * The order is fixed and total, in this sequence:
 *
 * 1. **Specificity.** A rule about a role beats a rule about everybody signed
 *    in, which beats the event's fallback. This is first because it is what a
 *    site owner means: writing "agents go to the agent dashboard" alongside
 *    "everybody goes to the shop" should not require discovering a priority
 *    field.
 * 2. **Priority**, lower first, in the manner of a WordPress hook. This is what
 *    settles two rules of equal specificity — the ordinary case of somebody who
 *    holds two roles, and the reason the field exists at all.
 * 3. **Age**, oldest first. Not because the oldest rule deserves to win, but
 *    because *something* has to decide, and a tie broken by whatever order the
 *    database felt like returning is a bug that appears once a fortnight and
 *    cannot be reproduced.
 *
 * Nothing here validates a destination. Deciding which URL is safe to send
 * somebody to is SafeRedirect's job, and it happens after this, on the way out.
 */
final class RedirectResolver {

	/**
	 * Decide where somebody goes.
	 *
	 * @param string             $event    The moment.
	 * @param list<Subject>      $subjects What the person counts as.
	 * @param list<RedirectRule> $rules The rules to consider.
	 * @param string             $fallback Where to go when no rule applies.
	 * @return Resolution
	 */
	public function resolve( string $event, array $subjects, array $rules, string $fallback ): Resolution {
		$candidates = $this->candidates( $event, $subjects, $rules );

		if ( array() === $candidates ) {
			return new Resolution( $fallback );
		}

		$winner = $candidates[0];

		return new Resolution( $winner->destination(), $winner, $candidates );
	}

	/**
	 * The rules that apply, best first.
	 *
	 * @param string             $event    The moment.
	 * @param list<Subject>      $subjects What the person counts as.
	 * @param list<RedirectRule> $rules    The rules to consider.
	 * @return list<RedirectRule>
	 */
	public function candidates( string $event, array $subjects, array $rules ): array {
		$matching = array();

		foreach ( $rules as $rule ) {
			if ( ! $rule instanceof RedirectRule ) {
				continue;
			}

			if ( $rule->event() !== $event || ! $rule->is_enabled() ) {
				continue;
			}

			if ( $this->applies_to( $rule, $subjects ) ) {
				$matching[] = $rule;
			}
		}

		usort( $matching, array( $this, 'compare' ) );

		return $matching;
	}

	/**
	 * Whether a rule is about somebody who presents these subjects.
	 *
	 * @param RedirectRule  $rule     The rule.
	 * @param list<Subject> $subjects What the person counts as.
	 * @return bool
	 */
	private function applies_to( RedirectRule $rule, array $subjects ): bool {
		$subject = $rule->subject();

		if ( null === $subject ) {
			return true;
		}

		foreach ( $subjects as $presented ) {
			if ( $presented instanceof Subject && $presented->equals( $subject ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The ordering. Negative means the first rule wins.
	 *
	 * @param RedirectRule $a One rule.
	 * @param RedirectRule $b The other.
	 * @return int
	 */
	private function compare( RedirectRule $a, RedirectRule $b ): int {
		// More specific first, so the comparison is the other way round.
		$specificity = $b->specificity() <=> $a->specificity();

		if ( 0 !== $specificity ) {
			return $specificity;
		}

		$priority = $a->priority() <=> $b->priority();

		if ( 0 !== $priority ) {
			return $priority;
		}

		return $a->id() <=> $b->id();
	}
}
