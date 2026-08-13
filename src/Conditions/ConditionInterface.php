<?php
/**
 * One thing that has to be true.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Conditions;

/**
 * A test a rule can carry, judged against the facts of a request.
 *
 * The free plugin ships **no implementations of this**, and that is deliberate
 * rather than unfinished. Every condition anybody has actually asked for — their
 * first sign-in, the page they were trying to reach, a value on their account,
 * an order in a shop — belongs to the paid edition or to somebody else's plugin.
 * What the free plugin owes them is the seam: a way to be stored, a way to be
 * offered on a screen, and a place in the decision.
 *
 * A condition must be **cheap and side-effect free**. It is asked on every
 * sign-in, sometimes several times, and it must be able to answer for a person
 * who is not the one making the request: an administrator previewing what a
 * customer would get is asking about somebody else entirely.
 */
interface ConditionInterface {

	/**
	 * The stored type, which is how a rule names this condition.
	 *
	 * Short, lowercase, and prefixed by whoever ships it — `oxyarea_pro/first_login`
	 * rather than `first_login`. Two plugins choosing the same word would each
	 * answer the other's rules, and the failure would be a redirect that works
	 * on one site and not another.
	 *
	 * @return string
	 */
	public function type(): string;

	/**
	 * What to call it on a screen.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Whether it holds.
	 *
	 * @param string  $value   The value stored with the rule, or '' when the
	 *                         condition takes none.
	 * @param Context $context The facts of this request.
	 * @return bool
	 */
	public function matches( string $value, Context $context ): bool;
}
