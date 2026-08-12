<?php
/**
 * One rule about where somebody goes.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Redirect;

use InvalidArgumentException;
use OxyArea\Access\Subject;

/*
 * The Redirect layer contains no WordPress call by design, for the same reason
 * the Access layer does not: the ordering rules are the whole feature, and they
 * have to be testable without an installation. esc_html() does not exist here,
 * and the value below is an event name from this plugin's own closed list.
 */
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * An event, who it is about, and where they should land.
 *
 * A rule with no subject is the fallback for its event: it matches everybody who
 * reaches that moment, and it is the least specific thing in the box.
 */
final class RedirectRule {

	/**
	 * The identifier, or 0 for a rule that has not been stored yet.
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * Which moment this rule is about.
	 *
	 * @var string
	 */
	private string $event;

	/**
	 * Who it is about, or null for the fallback.
	 *
	 * @var Subject|null
	 */
	private ?Subject $subject;

	/**
	 * Where they should land.
	 *
	 * @var string
	 */
	private string $destination;

	/**
	 * The tie-breaker, lower first, in the manner of a WordPress hook.
	 *
	 * @var int
	 */
	private int $priority;

	/**
	 * Whether the rule counts at all.
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Build a rule.
	 *
	 * @param string       $event       Which moment this rule is about.
	 * @param Subject|null $subject     Who it is about, or null for the event's fallback.
	 * @param string       $destination Where they should land.
	 * @param int          $priority    Tie-breaker, lower first.
	 * @param bool         $enabled     Whether it counts.
	 * @param int          $id          Identifier, or 0 when not yet stored.
	 *
	 * @throws InvalidArgumentException If the event is not one OxyArea knows, or the destination is empty.
	 */
	public function __construct(
		string $event,
		?Subject $subject,
		string $destination,
		int $priority = 10,
		bool $enabled = true,
		int $id = 0
	) {
		if ( ! RedirectEvent::exists( $event ) ) {
			throw new InvalidArgumentException(
				sprintf( 'There is no OxyArea redirect event called "%s".', $event )
			);
		}

		$destination = trim( $destination );

		if ( '' === $destination ) {
			throw new InvalidArgumentException( 'An OxyArea redirect rule must have a destination.' );
		}

		$this->event       = $event;
		$this->subject     = $subject;
		$this->destination = $destination;
		$this->priority    = $priority;
		$this->enabled     = $enabled;
		$this->id          = $id;
	}

	/**
	 * The identifier.
	 *
	 * @return int
	 */
	public function id(): int {
		return $this->id;
	}

	/**
	 * Which moment this rule is about.
	 *
	 * @return string
	 */
	public function event(): string {
		return $this->event;
	}

	/**
	 * Who it is about, or null for the event's fallback.
	 *
	 * @return Subject|null
	 */
	public function subject(): ?Subject {
		return $this->subject;
	}

	/**
	 * Where they should land.
	 *
	 * @return string
	 */
	public function destination(): string {
		return $this->destination;
	}

	/**
	 * The tie-breaker.
	 *
	 * @return int
	 */
	public function priority(): int {
		return $this->priority;
	}

	/**
	 * Whether the rule counts.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Whether this is the fallback for its event.
	 *
	 * @return bool
	 */
	public function is_fallback(): bool {
		return null === $this->subject;
	}

	/**
	 * How specific this rule is.
	 *
	 * The number decides who wins before priority is even looked at, and the
	 * ordering is the one a site owner would guess: a rule about a named person
	 * beats a rule about their company, which beats a rule about their role,
	 * which beats "everybody who is signed in", which beats the fallback.
	 *
	 * Guessing right matters more than being configurable. Somebody who writes
	 * "agents go to the agent dashboard" and also has "everybody goes to the
	 * shop" should not have to discover a priority field to get what they plainly
	 * meant.
	 *
	 * The gaps are deliberate: PRO adds subject types between these without
	 * renumbering anything the free plugin stored.
	 *
	 * @return int
	 */
	public function specificity(): int {
		if ( null === $this->subject ) {
			return 0;
		}

		switch ( $this->subject->type() ) {
			case Subject::USER:
				return 50;
			case Subject::GROUP:
				return 40;
			case Subject::ROLE:
				return 30;
			case Subject::CAPABILITY:
				return 25;
			case Subject::AUTHENTICATED:
			case Subject::ANONYMOUS:
				return 20;
			default:
				// A subject type nobody here has heard of comes from an add-on. It
				// is more specific than the fallback and less specific than
				// anything this plugin can reason about, which is the modest
				// assumption.
				return 10;
		}
	}

	/**
	 * A description of who this rule is about, for a screen or a log line.
	 *
	 * @return string
	 */
	public function subject_key(): string {
		return null === $this->subject ? 'everybody' : $this->subject->key();
	}

	/**
	 * The same rule with an identifier, as returned by the store.
	 *
	 * @param int $id The identifier.
	 * @return self
	 */
	public function with_id( int $id ): self {
		return new self( $this->event, $this->subject, $this->destination, $this->priority, $this->enabled, $id );
	}
}
