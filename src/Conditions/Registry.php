<?php
/**
 * Which conditions this site knows about.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Conditions;

/**
 * The condition types available, and the verdict on a set of them.
 *
 * ## What happens to a condition nobody can judge
 *
 * It refuses. A rule carrying `oxyarea_pro/first_login` on a site where PRO has
 * been deactivated is a rule whose author meant "only on their first sign-in",
 * and the site can no longer tell whether that is true. Treating an unanswerable
 * condition as satisfied would quietly turn a narrow rule into a broad one —
 * everybody would start being sent where only first-timers were meant to go, and
 * nothing on any screen would say why.
 *
 * The same reasoning as the access resolver's deny-by-default, applied to a
 * different question: when the site cannot answer, it does not guess in the
 * direction that does more.
 *
 * That does mean deactivating an add-on stops some redirects working rather than
 * making them fire for everybody. It is the safer of the two failures and the
 * screen says which rules are affected.
 */
final class Registry {

	/**
	 * The conditions, by type.
	 *
	 * @var array<string, ConditionInterface>|null
	 */
	private ?array $conditions = null;

	/**
	 * Every condition type this site can judge.
	 *
	 * @return array<string, ConditionInterface>
	 */
	public function all(): array {
		if ( null !== $this->conditions ) {
			return $this->conditions;
		}

		/**
		 * The conditions a rule may carry.
		 *
		 * The free plugin contributes none: every condition anybody has asked
		 * for belongs to an add-on. Return objects implementing
		 * ConditionInterface; anything else is dropped rather than trusted,
		 * because this list decides who gets sent where.
		 *
		 * @since 0.1.0
		 *
		 * @param list<ConditionInterface> $conditions The conditions gathered so far.
		 */
		$offered = apply_filters( 'oxyarea_conditions', array() );

		$this->conditions = array();

		foreach ( is_array( $offered ) ? $offered : array() as $condition ) {
			if ( ! $condition instanceof ConditionInterface ) {
				continue;
			}

			$type = trim( $condition->type() );

			if ( '' === $type ) {
				continue;
			}

			// First one wins. An add-on registering a type another already
			// claimed is a collision its author has to see and fix, and the
			// alternative — last one wins — would make the answer depend on
			// plugin load order.
			if ( ! isset( $this->conditions[ $type ] ) ) {
				$this->conditions[ $type ] = $condition;
			}
		}

		return $this->conditions;
	}

	/**
	 * Whether every one of these conditions holds.
	 *
	 * An empty list holds: a rule with no conditions is a rule about everybody
	 * the subject already narrowed it to.
	 *
	 * They are joined with **and**, not with or. A rule saying "on their first
	 * sign-in, coming from the checkout" means both, which is what somebody
	 * writing two lines in a form means, and an `or` that has to be spelled out
	 * as two rules is easier to read than an `and` that has to be spelled out as
	 * a nested expression.
	 *
	 * @param list<array{type: string, value: string}> $specifications What the rule carries.
	 * @param Context                                  $context        The facts.
	 * @return bool
	 */
	public function satisfied( array $specifications, Context $context ): bool {
		if ( array() === $specifications ) {
			return true;
		}

		$known = $this->all();

		foreach ( $specifications as $specification ) {
			$type = (string) ( $specification['type'] ?? '' );

			if ( ! isset( $known[ $type ] ) ) {
				return false;
			}

			if ( ! $known[ $type ]->matches( (string) ( $specification['value'] ?? '' ), $context ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether anything on this site can judge a given type.
	 *
	 * The screens use it to mark a rule whose add-on is not installed, rather
	 * than showing a rule that silently never fires.
	 *
	 * @param string $type The stored type.
	 * @return bool
	 */
	public function knows( string $type ): bool {
		return isset( $this->all()[ $type ] );
	}

	/**
	 * Forget what was gathered.
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->conditions = null;
	}
}
