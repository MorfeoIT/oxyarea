<?php
/**
 * Who administers the private area, according to WordPress.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Roles;

use OxyArea\Access\ManagerCheckInterface;

/**
 * Reads the manage_oxyarea capability.
 *
 * Deliberately the narrow capability and not "is an administrator": a site that
 * has given manage_oxyarea to an office manager has said what it meant, and a
 * site that has taken it away from a particular administrator has also said what
 * it meant.
 */
final class CapabilityManagerCheck implements ManagerCheckInterface {

	/**
	 * Whether this user administers OxyArea.
	 *
	 * @param int $user_id User ID, or 0 for a signed-out visitor.
	 * @return bool
	 */
	public function is_manager( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		return user_can( $user_id, Capabilities::MANAGE );
	}
}
