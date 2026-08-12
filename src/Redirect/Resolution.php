<?php
/**
 * Where somebody is going, and which rule said so.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Redirect;

/**
 * The engine's answer, with the working shown.
 *
 * "Why did my customer end up on the shop page?" is the support question this
 * feature generates, and it is unanswerable unless the engine can say which of
 * the rules it looked at won and which it passed over. So it says.
 */
final class Resolution {

	/**
	 * Where they are going.
	 *
	 * @var string
	 */
	private string $destination;

	/**
	 * The rule that decided, or null when nothing matched.
	 *
	 * @var RedirectRule|null
	 */
	private ?RedirectRule $rule;

	/**
	 * The rules that matched, best first.
	 *
	 * @var list<RedirectRule>
	 */
	private array $candidates;

	/**
	 * Build a resolution.
	 *
	 * @param string             $destination Where they are going.
	 * @param RedirectRule|null  $rule        The rule that decided.
	 * @param list<RedirectRule> $candidates  Every rule that matched, best first.
	 */
	public function __construct( string $destination, ?RedirectRule $rule = null, array $candidates = array() ) {
		$this->destination = $destination;
		$this->rule        = $rule;
		$this->candidates  = $candidates;
	}

	/**
	 * Where they are going.
	 *
	 * @return string
	 */
	public function destination(): string {
		return $this->destination;
	}

	/**
	 * The rule that decided, or null when nothing matched.
	 *
	 * @return RedirectRule|null
	 */
	public function rule(): ?RedirectRule {
		return $this->rule;
	}

	/**
	 * Every rule that matched, in the order they were considered.
	 *
	 * @return list<RedirectRule>
	 */
	public function candidates(): array {
		return $this->candidates;
	}

	/**
	 * Whether a rule decided this, as opposed to the fallback.
	 *
	 * @return bool
	 */
	public function was_decided_by_a_rule(): bool {
		return null !== $this->rule;
	}

	/**
	 * Whether more than one rule wanted this moment.
	 *
	 * Not an error — it is the ordinary case for somebody holding two roles — but
	 * it is the thing worth showing an administrator who is surprised by where
	 * their users land.
	 *
	 * @return bool
	 */
	public function was_contested(): bool {
		return count( $this->candidates ) > 1;
	}
}
