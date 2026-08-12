<?php
/**
 * Where the redirect rules are kept.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Redirect;

/**
 * Reads and writes the redirect rules.
 *
 * An interface so the engine can be exercised against a list in memory, which is
 * what makes the ordering rules testable at all.
 */
interface RuleRepositoryInterface {

	/**
	 * Every rule for a moment, enabled or not.
	 *
	 * @param string $event The moment.
	 * @return list<RedirectRule>
	 */
	public function for_event( string $event ): array;

	/**
	 * Every rule there is, grouped by nothing, oldest first.
	 *
	 * @return list<RedirectRule>
	 */
	public function all(): array;

	/**
	 * Store a rule and return it with its identifier.
	 *
	 * @param RedirectRule $rule The rule.
	 * @return RedirectRule
	 */
	public function save( RedirectRule $rule ): RedirectRule;

	/**
	 * Remove a rule.
	 *
	 * @param int $id The identifier.
	 * @return void
	 */
	public function delete( int $id ): void;

	/**
	 * Turn a rule on or off without losing it.
	 *
	 * @param int  $id      The identifier.
	 * @param bool $enabled Whether it should count.
	 * @return void
	 */
	public function set_enabled( int $id, bool $enabled ): void;
}
