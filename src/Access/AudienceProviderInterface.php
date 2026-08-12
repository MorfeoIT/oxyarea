<?php
/**
 * Where subjects come from.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Access;

/**
 * Turns a user into the set of subjects they present.
 *
 * The free plugin ships one of these, which reports "authenticated" and the
 * user's roles. PRO registers further providers that add the user themselves,
 * their company, their groups and their capabilities. The resolver asks every
 * registered provider and works with the union.
 *
 * A provider is asked about one user at a time and must not assume that user is
 * the one making the request: the permission inspector asks about other people,
 * and an administrator previewing a dashboard asks about a role.
 */
interface AudienceProviderInterface {

	/**
	 * The subjects this user presents.
	 *
	 * Must return an empty array rather than throw for a user that does not
	 * exist. Must be cheap enough to call on every request, because it is.
	 *
	 * @param int $user_id User ID, or 0 for a signed-out visitor.
	 * @return list<Subject>
	 */
	public function get_subjects( int $user_id ): array;
}
