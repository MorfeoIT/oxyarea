<?php
/**
 * The thing access is being asked about.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Access;

/**
 * Anything OxyArea can protect.
 *
 * A post, a dashboard, a private notice, and in PRO a file. The resolver does
 * not care which: it is handed a type and an identifier and looks for
 * assignments against them. That is what allows PRO to introduce resource types
 * the free plugin has never heard of without the resolver changing.
 */
interface ResourceInterface {

	/**
	 * What kind of thing this is.
	 *
	 * @return string
	 */
	public function get_type(): string;

	/**
	 * Which one, within that kind.
	 *
	 * @return int
	 */
	public function get_id(): int;
}
