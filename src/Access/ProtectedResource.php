<?php
/**
 * The ordinary resource.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Access;

use InvalidArgumentException;

/**
 * A type and an identifier, and nothing else.
 *
 * Most callers need no more than this. Code that wants to carry the underlying
 * object along with it implements ResourceInterface on its own class instead.
 *
 * Named ProtectedResource rather than Resource because "resource" is a
 * soft-reserved word in PHP and a class taking that name is one language release
 * away from being a problem.
 */
final class ProtectedResource implements ResourceInterface {

	/**
	 * A post, page or custom post type entry.
	 */
	public const POST = 'post';

	/**
	 * An OxyArea dashboard.
	 */
	public const DASHBOARD = 'dashboard';

	/**
	 * A private item: a notice, a note, a link.
	 */
	public const ITEM = 'item';

	/**
	 * A file in the vault. Provided by OxyArea PRO.
	 */
	public const FILE = 'file';

	/**
	 * The resource type.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * The resource identifier.
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * Build a resource reference.
	 *
	 * @param string $type Resource type.
	 * @param int    $id   Resource identifier.
	 *
	 * @throws InvalidArgumentException If the type is empty or too long for the column.
	 */
	public function __construct( string $type, int $id ) {
		$type = trim( $type );

		if ( '' === $type ) {
			throw new InvalidArgumentException( 'An OxyArea resource must have a type.' );
		}

		if ( strlen( $type ) > 32 ) {
			throw new InvalidArgumentException( 'An OxyArea resource type may not exceed 32 characters.' );
		}

		$this->type = $type;
		$this->id   = $id;
	}

	/**
	 * A post, page or CPT entry.
	 *
	 * @param int $post_id Post ID.
	 * @return self
	 */
	public static function post( int $post_id ): self {
		return new self( self::POST, $post_id );
	}

	/**
	 * A dashboard.
	 *
	 * @param int $dashboard_id Dashboard ID.
	 * @return self
	 */
	public static function dashboard( int $dashboard_id ): self {
		return new self( self::DASHBOARD, $dashboard_id );
	}

	/**
	 * What kind of thing this is.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Which one, within that kind.
	 *
	 * @return int
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * A stable string form, for cache keys and log lines.
	 *
	 * @return string
	 */
	public function key(): string {
		return $this->type . ':' . $this->id;
	}
}
