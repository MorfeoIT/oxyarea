<?php
/**
 * Who administers the private area.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Access;

/**
 * Answers whether a user administers OxyArea itself.
 *
 * Someone who can edit the rules can already read anything the rules protect, by
 * the simple method of granting themselves access and reading it. Pretending
 * otherwise on the front end would buy no security and would make the dashboard
 * preview and the permission inspector impossible to build.
 *
 * It is an interface so that the resolver never has to call user_can(), which is
 * what keeps the resolver testable without WordPress.
 */
interface ManagerCheckInterface {

	/**
	 * Whether this user administers OxyArea.
	 *
	 * @param int $user_id User ID, or 0 for a signed-out visitor.
	 * @return bool
	 */
	public function is_manager( int $user_id ): bool;
}
