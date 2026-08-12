<?php
/**
 * The answer, and why.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Access;

/**
 * An access decision together with the reasoning that produced it.
 *
 * The reasoning is not decoration. PRO sells a screen that tells an
 * administrator why a particular user cannot see a particular document, and the
 * only way that screen can be trusted is if it shows the actual steps of the
 * actual decision rather than a second implementation that agrees with the first
 * most of the time. So the resolver produces this, the boolean is derived from
 * it, and the inspector renders it.
 *
 * Immutable: every method that appears to change a decision returns a new one.
 * A decision that could be edited after the fact by a filter would be worth
 * nothing as an audit record.
 */
final class Decision {

	/**
	 * Whether access is granted.
	 *
	 * @var bool
	 */
	private bool $allowed;

	/**
	 * The steps that led here, in order.
	 *
	 * @var list<array{passed: bool, reason: string}>
	 */
	private array $steps;

	/**
	 * @param bool                                     $allowed Whether access is granted.
	 * @param list<array{passed: bool, reason: string}> $steps   The reasoning.
	 */
	private function __construct( bool $allowed, array $steps ) {
		$this->allowed = $allowed;
		$this->steps   = $steps;
	}

	/**
	 * Access granted.
	 *
	 * @param string $reason Why.
	 * @return self
	 */
	public static function allow( string $reason ): self {
		return new self(
			true,
			array(
				array(
					'passed' => true,
					'reason' => $reason,
				),
			)
		);
	}

	/**
	 * Access refused.
	 *
	 * This is what an empty resolver returns, and what every unexpected state
	 * falls back to. A private area that fails open is not a private area.
	 *
	 * @param string $reason Why.
	 * @return self
	 */
	public static function deny( string $reason ): self {
		return new self(
			false,
			array(
				array(
					'passed' => false,
					'reason' => $reason,
				),
			)
		);
	}

	/**
	 * The same decision, with one more step recorded before it.
	 *
	 * Used to carry the checks that passed on the way to the one that decided.
	 *
	 * @param bool   $passed Whether this step was satisfied.
	 * @param string $reason What was checked.
	 * @return self
	 */
	public function with_step( bool $passed, string $reason ): self {
		return new self(
			$this->allowed,
			array_merge(
				array(
					array(
						'passed' => $passed,
						'reason' => $reason,
					),
				),
				$this->steps
			)
		);
	}

	/**
	 * Whether access is granted.
	 *
	 * @return bool
	 */
	public function is_allowed(): bool {
		return $this->allowed;
	}

	/**
	 * The reasoning, in the order it was applied.
	 *
	 * @return list<array{passed: bool, reason: string}>
	 */
	public function steps(): array {
		return $this->steps;
	}

	/**
	 * The reason that settled it.
	 *
	 * @return string
	 */
	public function summary(): string {
		$count = count( $this->steps );

		return $count > 0 ? $this->steps[ $count - 1 ]['reason'] : '';
	}
}
