<?php
/**
 * Which posts are restricted at all.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Content;

use OxyArea\Access\AssignmentRepositoryInterface;
use OxyArea\Access\ProtectedResource;

/**
 * Answers "is this restricted", which comes before "may this person see it".
 *
 * The distinction is the whole reason this class exists. The access resolver
 * refuses anything nobody has granted, which is right for a resource that is
 * private — and catastrophic if asked about every post on a blog, because it
 * would refuse all of them. So the resolver is only ever consulted about posts
 * that somebody has actually said something about, and this is what knows which
 * those are.
 */
final class Restrictions {

	/**
	 * Where the rules live.
	 *
	 * @var AssignmentRepositoryInterface
	 */
	private AssignmentRepositoryInterface $assignments;

	/**
	 * The restricted post identifiers, once worked out.
	 *
	 * @var array<int, true>|null
	 */
	private ?array $restricted = null;

	/**
	 * Build the service.
	 *
	 * @param AssignmentRepositoryInterface $assignments Where the rules live.
	 */
	public function __construct( AssignmentRepositoryInterface $assignments ) {
		$this->assignments = $assignments;
	}

	/**
	 * Whether anybody has said anything about this post.
	 *
	 * @param int $post_id The post.
	 * @return bool
	 */
	public function is_restricted( int $post_id ): bool {
		return isset( $this->all()[ $post_id ] );
	}

	/**
	 * Every restricted post.
	 *
	 * @return list<int>
	 */
	public function ids(): array {
		return array_keys( $this->all() );
	}

	/**
	 * Whether the site restricts anything at all.
	 *
	 * Worth asking first: on a site that restricts nothing, every filter in this
	 * sprint can return immediately, and most sites running this plugin for the
	 * first time are that site.
	 *
	 * @return bool
	 */
	public function any(): bool {
		return array() !== $this->all();
	}

	/**
	 * Load the rules for a page full of posts in one query.
	 *
	 * @param list<int> $ids The posts about to be asked about.
	 * @return void
	 */
	public function warm( array $ids ): void {
		$restricted = array_values( array_filter( $ids, fn ( $id ): bool => $this->is_restricted( (int) $id ) ) );

		if ( array() !== $restricted ) {
			$this->assignments->warm( ProtectedResource::POST, $restricted );
		}
	}

	/**
	 * Forget what was worked out, for a request that changes the rules.
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->restricted = null;
	}

	/**
	 * The restricted posts, as a lookup.
	 *
	 * @return array<int, true>
	 */
	private function all(): array {
		if ( null === $this->restricted ) {
			$this->restricted = array_fill_keys(
				$this->assignments->restricted_ids( ProtectedResource::POST ),
				true
			);
		}

		return $this->restricted;
	}
}
