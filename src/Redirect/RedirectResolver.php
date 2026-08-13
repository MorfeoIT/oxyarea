<?php
/**
 * Which rule wins.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Redirect;

use OxyArea\Access\Subject;
use OxyArea\Conditions\Context;
use OxyArea\Conditions\Registry;

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
	 * Which conditions this site can judge, or null when nothing judges them.
	 *
	 * Optional so that everything written before conditions existed still
	 * constructs. A resolver without a registry treats every rule as having no
	 * conditions — which is exactly what a rule written before this column had
	 * anyway.
	 *
	 * @var Registry|null
	 */
	private ?Registry $conditions;

	/**
	 * Build the resolver.
	 *
	 * @param Registry|null $conditions Which conditions this site can judge.
	 */
	public function __construct( ?Registry $conditions = null ) {
		$this->conditions = $conditions;
	}

	/**
	 * Decide where somebody goes.
	 *
	 * @param string             $event    The moment.
	 * @param list<Subject>      $subjects What the person counts as.
	 * @param list<RedirectRule> $rules The rules to consider.
	 * @param string             $fallback Where to go when no rule applies.
	 * @param Context|null       $context  The facts of this request, for conditions.
	 * @return Resolution
	 */
	public function resolve( string $event, array $subjects, array $rules, string $fallback, ?Context $context = null ): Resolution {
		$candidates = $this->candidates( $event, $subjects, $rules, $context );

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
	 * @param Context|null       $context  The facts of this request, for conditions.
	 * @return list<RedirectRule>
	 */
	public function candidates( string $event, array $subjects, array $rules, ?Context $context = null ): array {
		$matching = array();

		foreach ( $rules as $rule ) {
			if ( ! $rule instanceof RedirectRule ) {
				continue;
			}

			if ( $rule->event() !== $event || ! $rule->is_enabled() ) {
				continue;
			}

			if ( ! $this->applies_to( $rule, $subjects ) ) {
				continue;
			}

			// Conditions are judged after the audience and before the ordering,
			// and that position is the whole design. They narrow *whether* a
			// rule applies; they contribute nothing to how specific it is. A
			// rule about Mario on his first sign-in is exactly as specific as a
			// rule about Mario — it simply applies less often.
			if ( ! $this->conditions_hold( $rule, $context ) ) {
				continue;
			}

			$matching[] = $rule;
		}

		usort( $matching, array( $this, 'compare' ) );

		return $matching;
	}

	/**
	 * Whether every condition a rule carries holds.
	 *
	 * A rule with conditions on a site that cannot judge them does not apply.
	 * The alternative — treating an unanswerable condition as satisfied — would
	 * quietly widen the rule: deactivate the add-on that provided "first sign-in
	 * only" and everybody starts being sent where only first-timers were meant
	 * to go, with nothing on any screen saying why.
	 *
	 * @param RedirectRule $rule    The rule.
	 * @param Context|null $context The facts, when there are any.
	 * @return bool
	 */
	private function conditions_hold( RedirectRule $rule, ?Context $context ): bool {
		$specifications = $rule->conditions();

		if ( array() === $specifications ) {
			return true;
		}

		if ( null === $this->conditions || null === $context ) {
			return false;
		}

		return $this->conditions->satisfied( $specifications, $context );
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
