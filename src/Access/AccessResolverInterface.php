<?php
/**
 * The one place access is decided.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Access;

/**
 * Answers whether a user may see a resource.
 *
 * There is exactly one implementation of this running on a site, and every
 * question about access goes through it: templates, blocks, REST routes, AJAX
 * handlers, the query filter that keeps private posts out of search, and the
 * download controller PRO adds. Nothing re-implements the rules locally.
 *
 * That is not tidiness. Authorisation logic duplicated in five places is
 * authorisation logic that is wrong in at least one of them, and the one that is
 * wrong is the one nobody tested.
 */
interface AccessResolverInterface {

	/**
	 * Whether a user may view a resource.
	 *
	 * A user ID of 0 means a visitor who is not signed in. The answer for an
	 * unknown resource, an unknown user, or any state the resolver cannot make
	 * sense of, is false.
	 *
	 * @param int               $user_id User ID, or 0 for a signed-out visitor.
	 * @param ResourceInterface $target  The resource in question.
	 * @return bool
	 */
	public function can_view( int $user_id, ResourceInterface $target ): bool;

	/**
	 * The same question, answered with its reasoning.
	 *
	 * Reducing this to its boolean is what can_view() does. Keeping the two on
	 * one interface is what guarantees that the explanation an administrator
	 * reads is the decision the site actually made.
	 *
	 * @param int               $user_id User ID, or 0 for a signed-out visitor.
	 * @param ResourceInterface $target  The resource in question.
	 * @return Decision
	 */
	public function explain( int $user_id, ResourceInterface $target ): Decision;
}
