<?php
/**
 * An audience provider that says what it was told to say.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Support;

use OxyArea\Access\AudienceProviderInterface;
use OxyArea\Access\Subject;

/**
 * Reports fixed subjects per user, standing in for WordPress roles.
 */
final class StubAudienceProvider implements AudienceProviderInterface {

	/**
	 * Subjects, by user ID.
	 *
	 * @var array<int, list<Subject>>
	 */
	private array $subjects;

	/**
	 * How many times it has been asked.
	 *
	 * @var int
	 */
	private int $calls = 0;

	/**
	 * Build the provider.
	 *
	 * @param array<int, list<Subject>> $subjects Subjects, by user ID.
	 */
	public function __construct( array $subjects = array() ) {
		$this->subjects = $subjects;
	}

	/**
	 * The subjects this user presents.
	 *
	 * @param int $user_id User ID, or 0 for a signed-out visitor.
	 * @return list<Subject>
	 */
	public function get_subjects( int $user_id ): array {
		++$this->calls;

		return $this->subjects[ $user_id ] ?? array();
	}

	/**
	 * How many times it has been asked.
	 *
	 * @return int
	 */
	public function calls(): int {
		return $this->calls;
	}
}
