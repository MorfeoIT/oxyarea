<?php
/**
 * The resolver, with the extension hook around it.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Access;

/**
 * Wraps the resolver so add-ons can see and adjust a decision.
 *
 * The hook lives here rather than inside AccessResolver for one reason: the
 * resolver must stay free of WordPress so that every branch of it can be tested,
 * and apply_filters() is WordPress.
 *
 * A filter that returns anything other than a Decision is ignored, not trusted
 * and not fatal. Returning true from a badly written add-on must not become
 * "access granted".
 */
final class HookedAccessResolver implements AccessResolverInterface {

	/**
	 * The resolver doing the actual work.
	 *
	 * @var AccessResolverInterface
	 */
	private AccessResolverInterface $inner;

	/**
	 * Wrap a resolver.
	 *
	 * @param AccessResolverInterface $inner The resolver doing the actual work.
	 */
	public function __construct( AccessResolverInterface $inner ) {
		$this->inner = $inner;
	}

	/**
	 * Whether a user may view a resource.
	 *
	 * @param int               $user_id User ID, or 0 for a signed-out visitor.
	 * @param ResourceInterface $target  The resource in question.
	 * @return bool
	 */
	public function can_view( int $user_id, ResourceInterface $target ): bool {
		return $this->explain( $user_id, $target )->is_allowed();
	}

	/**
	 * The same question, answered with its reasoning.
	 *
	 * @param int               $user_id User ID, or 0 for a signed-out visitor.
	 * @param ResourceInterface $target  The resource in question.
	 * @return Decision
	 */
	public function explain( int $user_id, ResourceInterface $target ): Decision {
		$decision = $this->inner->explain( $user_id, $target );

		/**
		 * Filters an access decision before it is acted on.
		 *
		 * This is the seam add-ons use to add reasoning of their own. Whatever is
		 * returned is used for the answer *and* for what the permission inspector
		 * shows an administrator, so a filter that grants access silently is a
		 * filter that lies to the site owner: build the reasoning into the
		 * Decision you return.
		 *
		 * @since 0.1.0
		 *
		 * @param Decision          $decision The decision reached so far.
		 * @param int               $user_id  User ID, or 0 for a signed-out visitor.
		 * @param ResourceInterface $target   The resource in question.
		 */
		$filtered = apply_filters( 'oxyarea_access_decision', $decision, $user_id, $target );

		return $filtered instanceof Decision ? $filtered : $decision;
	}
}
