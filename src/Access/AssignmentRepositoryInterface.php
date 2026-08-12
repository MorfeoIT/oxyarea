<?php
/**
 * Where the rules are kept.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Access;

/**
 * Reads and writes the rules attached to a resource.
 *
 * An interface rather than a class reference so that the resolver can be built
 * and tested without a database, which is the only reason the authorisation
 * rules can be exercised exhaustively at all.
 */
interface AssignmentRepositoryInterface {

	/**
	 * Every rule attached to a resource, whether or not it currently applies.
	 *
	 * Returns an empty array for a resource nobody has said anything about.
	 * Malformed stored rows are skipped rather than raised: a corrupt row must
	 * not take a site down, and skipping it can only ever narrow access.
	 *
	 * @param ResourceInterface $target The resource.
	 * @return list<Assignment>
	 */
	public function for_resource( ResourceInterface $target ): array;

	/**
	 * Replace every rule attached to a resource with the given set.
	 *
	 * Passing an empty array removes the resource's rules, which leaves it with
	 * nothing granting access rather than open to everyone.
	 *
	 * @param ResourceInterface $target      The resource.
	 * @param list<Assignment>  $assignments The rules that should exist.
	 * @return void
	 */
	public function replace_for_resource( ResourceInterface $target, array $assignments ): void;

	/**
	 * Load the rules for many resources at once.
	 *
	 * A listing asks about every post on the page. Asking one at a time is one
	 * query per row, which is how a plugin turns a search results page into a
	 * slow search results page. Implementations warm whatever cache
	 * for_resource() reads from, so the calls that follow cost nothing.
	 *
	 * @param string    $type The resource type.
	 * @param list<int> $ids  The resource identifiers.
	 * @return void
	 */
	public function warm( string $type, array $ids ): void;

	/**
	 * Every resource of a type that has any rule attached to it at all.
	 *
	 * Answers "which of these is restricted", which is a different question from
	 * "may this person see it" and has to be asked first: a post nobody has said
	 * anything about is not private, and the resolver would refuse it.
	 *
	 * @param string $type The resource type.
	 * @return list<int>
	 */
	public function restricted_ids( string $type ): array;
}
