<?php
/**
 * One dashboard, and who it is for.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Dashboard;

use InvalidArgumentException;
use OxyArea\Access\Subject;

/*
 * Pure, like the Access and Redirect layers. Which dashboard somebody gets is a
 * decision, and a decision that cannot be tested exhaustively is one nobody can
 * rely on.
 */
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * A template, and the audience it serves.
 *
 * The point of the product in one object: **one template serves every user who
 * holds a role**. A site with four hundred customers has one customer
 * dashboard, not four hundred pages, and adding the four hundred and first
 * customer is not a content task.
 *
 * A dashboard with no subject is the site's default: what somebody sees when
 * their role has nothing of its own.
 */
final class Dashboard {

	/**
	 * The post this dashboard is stored as.
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * Its title, for admin screens.
	 *
	 * @var string
	 */
	private string $title;

	/**
	 * Who it is for, or null for the default.
	 *
	 * @var Subject|null
	 */
	private ?Subject $subject;

	/**
	 * Build a dashboard.
	 *
	 * @param int          $id      The post it is stored as.
	 * @param string       $title   Its title.
	 * @param Subject|null $subject Who it is for, or null for the default.
	 *
	 * @throws InvalidArgumentException If the identifier is not a real one.
	 */
	public function __construct( int $id, string $title, ?Subject $subject = null ) {
		if ( $id <= 0 ) {
			throw new InvalidArgumentException( 'An OxyArea dashboard must have a post identifier.' );
		}

		$this->id      = $id;
		$this->title   = $title;
		$this->subject = $subject;
	}

	/**
	 * The post it is stored as.
	 *
	 * @return int
	 */
	public function id(): int {
		return $this->id;
	}

	/**
	 * Its title.
	 *
	 * @return string
	 */
	public function title(): string {
		return $this->title;
	}

	/**
	 * Who it is for, or null for the default.
	 *
	 * @return Subject|null
	 */
	public function subject(): ?Subject {
		return $this->subject;
	}

	/**
	 * Whether this is the site's default dashboard.
	 *
	 * @return bool
	 */
	public function is_default(): bool {
		return null === $this->subject;
	}

	/**
	 * How specific this dashboard is.
	 *
	 * The same ladder the redirect rules use, and deliberately so: a site owner
	 * who has learned that a role beats "everybody" in one screen should not have
	 * to learn a different answer in the other.
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
				return 20;
			default:
				return 10;
		}
	}

	/**
	 * Who it is for, as a string for a screen or a log line.
	 *
	 * @return string
	 */
	public function subject_key(): string {
		return null === $this->subject ? 'default' : $this->subject->key();
	}
}
