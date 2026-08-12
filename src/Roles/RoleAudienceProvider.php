<?php
/**
 * What WordPress says a user is.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Roles;

use OxyArea\Access\AudienceProviderInterface;
use OxyArea\Access\Subject;

/**
 * Reports whether a visitor is signed in, and which roles they hold.
 *
 * This is the whole audience model of the free plugin. PRO registers further
 * providers alongside it; it is never replaced, because "which roles does this
 * person hold" does not become a different question when PRO is installed.
 *
 * Note what a signed-out visitor presents: "anonymous", and nothing else. It is
 * a distinct audience, not a synonym for "everybody". A rule that should reach
 * both a signed-out visitor and a signed-in one says so twice, because the
 * alternative — anonymous quietly matching everyone — is the kind of shorthand
 * that ends with a private page on the open internet.
 */
final class RoleAudienceProvider implements AudienceProviderInterface {

	/**
	 * The subjects this user presents.
	 *
	 * @param int $user_id User ID, or 0 for a signed-out visitor.
	 * @return list<Subject>
	 */
	public function get_subjects( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array( Subject::anonymous() );
		}

		$user = get_userdata( $user_id );

		// A user ID that names nobody is not treated as a signed-in visitor. It
		// happens with a deleted account whose session is still around, and the
		// safe reading is that the account is gone.
		if ( false === $user ) {
			return array( Subject::anonymous() );
		}

		$subjects = array( Subject::authenticated() );

		foreach ( (array) $user->roles as $role ) {
			if ( is_string( $role ) && '' !== $role ) {
				$subjects[] = Subject::role( $role );
			}
		}

		return $subjects;
	}
}
