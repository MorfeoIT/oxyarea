<?php
/**
 * A rule store that needs no database.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Support;

use OxyArea\Access\Assignment;
use OxyArea\Access\AssignmentRepositoryInterface;
use OxyArea\Access\ResourceInterface;

/**
 * Holds assignments in an array, keyed the same way the table keys them.
 */
final class InMemoryAssignmentRepository implements AssignmentRepositoryInterface {

	/**
	 * Rules, by resource key.
	 *
	 * @var array<string, list<Assignment>>
	 */
	private array $rules = array();

	/**
	 * Every rule attached to a resource.
	 *
	 * @param ResourceInterface $target The resource.
	 * @return list<Assignment>
	 */
	public function for_resource( ResourceInterface $target ): array {
		return $this->rules[ $this->key( $target ) ] ?? array();
	}

	/**
	 * Replace every rule attached to a resource.
	 *
	 * @param ResourceInterface $target      The resource.
	 * @param list<Assignment>  $assignments The rules that should exist.
	 * @return void
	 */
	public function replace_for_resource( ResourceInterface $target, array $assignments ): void {
		$this->rules[ $this->key( $target ) ] = array_values( $assignments );
	}

	/**
	 * The key a resource is stored under.
	 *
	 * @param ResourceInterface $target The resource.
	 * @return string
	 */
	private function key( ResourceInterface $target ): string {
		return $target->get_type() . ':' . $target->get_id();
	}
}
