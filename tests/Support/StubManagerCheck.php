<?php
/**
 * A manager check with a fixed answer.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Support;

use OxyArea\Access\ManagerCheckInterface;

/**
 * Says a named set of users administer OxyArea, and nobody else does.
 */
final class StubManagerCheck implements ManagerCheckInterface {

	/**
	 * The user IDs that administer OxyArea.
	 *
	 * @var list<int>
	 */
	private array $managers;

	/**
	 * Build the check.
	 *
	 * @param list<int> $managers The user IDs that administer OxyArea.
	 */
	public function __construct( array $managers = array() ) {
		$this->managers = $managers;
	}

	/**
	 * Whether this user administers OxyArea.
	 *
	 * @param int $user_id User ID, or 0 for a signed-out visitor.
	 * @return bool
	 */
	public function is_manager( int $user_id ): bool {
		return in_array( $user_id, $this->managers, true );
	}
}
