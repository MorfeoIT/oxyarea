<?php
/**
 * What is true of this request.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Conditions;

/**
 * The facts a condition is judged against.
 *
 * ## Why this is not a subject
 *
 * A subject says *who somebody is*: a role, a named person, a company. A
 * condition says *what this request is like*: their first time signing in, the
 * page they were trying to reach, a value on their account. The two are
 * different in a way that matters, and blurring them was the tempting mistake.
 *
 * A subject decides how *specific* a rule is — a rule about Mario beats a rule
 * about Customers, and the whole ordering rests on that. A condition has no
 * specificity at all: "on their first login" is not more or less specific than
 * "coming from the checkout", it is simply another hurdle. Putting conditions in
 * the subject codec would have meant inventing a rank for them, and every
 * ranking would have been arbitrary.
 *
 * So a rule matches when its subject matches **and** every one of its conditions
 * holds, and the ordering afterwards is the one it always was.
 *
 * ## Why it lives in Conditions and not in Redirect
 *
 * Redirect rules are the first thing to use this, not the only one. Whether a
 * dashboard widget should be drawn is the same question asked of the same facts,
 * and a `Redirect\Condition` would have had to be moved or copied the day that
 * arrived.
 */
final class Context {

	/**
	 * Whose request this is, or 0 for a signed-out visitor.
	 *
	 * @var int
	 */
	private int $user_id;

	/**
	 * What is happening, when there is a name for it.
	 *
	 * A redirect event for redirects; the empty string when a condition is being
	 * asked about something with no moment attached, such as whether to draw a
	 * widget.
	 *
	 * @var string
	 */
	private string $event;

	/**
	 * Where the browser was trying to go, when that is known.
	 *
	 * @var string
	 */
	private string $requested;

	/**
	 * Anything an add-on wants its own conditions to be able to read.
	 *
	 * A filter fills this, so a condition type shipped by one plugin can be
	 * judged against a fact contributed by another without either knowing about
	 * the other.
	 *
	 * @var array<string, mixed>
	 */
	private array $extra;

	/**
	 * Build a context.
	 *
	 * @param int                  $user_id   Whose request, or 0.
	 * @param string               $event     What is happening, or ''.
	 * @param string               $requested Where they were going, or ''.
	 * @param array<string, mixed> $extra     Anything else.
	 */
	public function __construct( int $user_id, string $event = '', string $requested = '', array $extra = array() ) {
		$this->user_id   = max( 0, $user_id );
		$this->event     = $event;
		$this->requested = $requested;
		$this->extra     = $extra;
	}

	/**
	 * Whose request this is.
	 *
	 * @return int
	 */
	public function user_id(): int {
		return $this->user_id;
	}

	/**
	 * What is happening.
	 *
	 * @return string
	 */
	public function event(): string {
		return $this->event;
	}

	/**
	 * Where the browser was trying to go.
	 *
	 * @return string
	 */
	public function requested(): string {
		return $this->requested;
	}

	/**
	 * One of the extra facts, or a default.
	 *
	 * @param string $key  Which fact.
	 * @param mixed  $fallback What to answer when it is not there.
	 * @return mixed
	 */
	public function get( string $key, $fallback = null ) {
		return $this->extra[ $key ] ?? $fallback;
	}

	/**
	 * The same context with more facts in it.
	 *
	 * Immutable, so that a condition cannot change what the next one is judged
	 * against. A condition that could edit the context would make the order they
	 * are evaluated in part of the answer, and nothing declares that order.
	 *
	 * @param array<string, mixed> $extra What to add.
	 * @return self
	 */
	public function with( array $extra ): self {
		return new self( $this->user_id, $this->event, $this->requested, array_merge( $this->extra, $extra ) );
	}
}
