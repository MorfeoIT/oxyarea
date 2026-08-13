<?php
/**
 * One rule about one resource.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Access;

use DateTimeImmutable;
use InvalidArgumentException;

/*
 * The Access layer contains no WordPress call by design: that is what makes the
 * authorisation rules testable without an installation, and esc_html() does not
 * exist here. The message below carries a stored effect value, which comes from
 * this plugin's own column and cannot contain markup.
 */
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * A subject, an effect, and optionally a period during which it counts.
 *
 * An assignment is a statement, not a decision: "customers may see this" is one
 * assignment, "Bob may not" is another, and what happens when both apply is the
 * resolver's business, not this object's.
 */
final class Assignment {

	/**
	 * The subject may see the resource.
	 */
	public const ALLOW = 'allow';

	/**
	 * The subject may not, whatever else says otherwise.
	 */
	public const DENY = 'deny';

	/**
	 * Who this is about.
	 *
	 * @var Subject
	 */
	private Subject $subject;

	/**
	 * Allow or deny.
	 *
	 * @var string
	 */
	private string $effect;

	/**
	 * Ordering weight, reserved for the compound rules PRO adds.
	 *
	 * @var int
	 */
	private int $priority;

	/**
	 * When the rule starts counting, or null for "always has".
	 *
	 * @var DateTimeImmutable|null
	 */
	private ?DateTimeImmutable $starts_at;

	/**
	 * When it stops, or null for "never does".
	 *
	 * @var DateTimeImmutable|null
	 */
	private ?DateTimeImmutable $ends_at;

	/**
	 * Build an assignment.
	 *
	 * @param Subject                $subject   Who the rule is about.
	 * @param string                 $effect    Assignment::ALLOW or Assignment::DENY.
	 * @param int                    $priority  Ordering weight.
	 * @param DateTimeImmutable|null $starts_at When the rule begins to count.
	 * @param DateTimeImmutable|null $ends_at   When it stops.
	 *
	 * @throws InvalidArgumentException If the effect is neither allow nor deny.
	 */
	public function __construct(
		Subject $subject,
		string $effect = self::ALLOW,
		int $priority = 10,
		?DateTimeImmutable $starts_at = null,
		?DateTimeImmutable $ends_at = null
	) {
		if ( self::ALLOW !== $effect && self::DENY !== $effect ) {
			throw new InvalidArgumentException(
				sprintf( 'An OxyArea assignment effect must be "allow" or "deny", "%s" given.', $effect )
			);
		}

		$this->subject   = $subject;
		$this->effect    = $effect;
		$this->priority  = $priority;
		$this->starts_at = $starts_at;
		$this->ends_at   = $ends_at;
	}

	/**
	 * Who the rule is about.
	 *
	 * @return Subject
	 */
	public function subject(): Subject {
		return $this->subject;
	}

	/**
	 * Whether this rule takes access away.
	 *
	 * @return bool
	 */
	public function is_deny(): bool {
		return self::DENY === $this->effect;
	}

	/**
	 * The effect, as stored.
	 *
	 * @return string
	 */
	public function effect(): string {
		return $this->effect;
	}

	/**
	 * The ordering weight.
	 *
	 * @return int
	 */
	public function priority(): int {
		return $this->priority;
	}

	/**
	 * When the rule starts counting, or null for "always has".
	 *
	 * Exposed so that the rule can be *stored*, which sounds obvious and was
	 * missing for six sprints. The window was validated here, filtered by the
	 * resolver and read back by the repository — and the repository's write path
	 * had no way to ask for it, so it wrote null and every expiry a site set was
	 * silently discarded. Nothing noticed because nothing in the free plugin sets
	 * a window; the first thing that did was PRO's file vault.
	 *
	 * @return DateTimeImmutable|null
	 */
	public function starts_at(): ?DateTimeImmutable {
		return $this->starts_at;
	}

	/**
	 * When the rule stops counting, or null for "never does".
	 *
	 * @return DateTimeImmutable|null
	 */
	public function ends_at(): ?DateTimeImmutable {
		return $this->ends_at;
	}

	/**
	 * Whether the rule counts at a given moment.
	 *
	 * Both ends are inclusive. A window whose end precedes its start never
	 * applies: that is corrupt data, and the safe reading of corrupt data in an
	 * access rule is that it grants nothing.
	 *
	 * @param DateTimeImmutable $now The moment to judge.
	 * @return bool
	 */
	public function applies_at( DateTimeImmutable $now ): bool {
		if ( null !== $this->starts_at && null !== $this->ends_at && $this->ends_at < $this->starts_at ) {
			return false;
		}

		if ( null !== $this->starts_at && $now < $this->starts_at ) {
			return false;
		}

		if ( null !== $this->ends_at && $now > $this->ends_at ) {
			return false;
		}

		return true;
	}
}
