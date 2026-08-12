<?php
/**
 * What a user counts as, when access is being decided.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Access;

use InvalidArgumentException;

/**
 * One answer to "who is this?", for the purpose of matching an assignment.
 *
 * A single person is several subjects at once: they are authenticated, they are
 * a Customer, and, once PRO is installed, they are Mario and they are ACME. An
 * assignment names one subject; a user matches it if that subject is among the
 * ones they present.
 *
 * The distinction the product turns on: a role says what *kind* of user someone
 * is, a group says which *organisation* they belong to. Sites that use a role
 * per customer end up with four hundred roles and no way to ask a useful
 * question, which is the failure this type exists to prevent.
 */
final class Subject {

	/**
	 * Everyone, signed in or not.
	 */
	public const ANONYMOUS = 'anonymous';

	/**
	 * Anyone signed in, whatever their role.
	 */
	public const AUTHENTICATED = 'authenticated';

	/**
	 * A WordPress role, identified by its slug.
	 */
	public const ROLE = 'role';

	/**
	 * One exact user, identified by ID. Provided by OxyArea PRO.
	 */
	public const USER = 'user';

	/**
	 * A company or group, identified by ID. Provided by OxyArea PRO.
	 */
	public const GROUP = 'group';

	/**
	 * A WordPress capability. Provided by OxyArea PRO.
	 */
	public const CAPABILITY = 'capability';

	/**
	 * The subject type.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * The identifier within that type, empty for types that need none.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * @param string     $type Subject type.
	 * @param string|int $id   Identifier within the type.
	 *
	 * @throws InvalidArgumentException If the type is empty or too long for the column.
	 */
	public function __construct( string $type, $id = '' ) {
		$type = trim( $type );

		if ( '' === $type ) {
			throw new InvalidArgumentException( 'An OxyArea subject must have a type.' );
		}

		if ( strlen( $type ) > 32 ) {
			throw new InvalidArgumentException( 'An OxyArea subject type may not exceed 32 characters.' );
		}

		$id = (string) $id;

		if ( strlen( $id ) > 191 ) {
			throw new InvalidArgumentException( 'An OxyArea subject identifier may not exceed 191 characters.' );
		}

		$this->type = $type;
		$this->id   = $id;
	}

	/**
	 * Everyone.
	 *
	 * @return self
	 */
	public static function anonymous(): self {
		return new self( self::ANONYMOUS );
	}

	/**
	 * Anyone signed in.
	 *
	 * @return self
	 */
	public static function authenticated(): self {
		return new self( self::AUTHENTICATED );
	}

	/**
	 * A role.
	 *
	 * @param string $role Role slug.
	 * @return self
	 */
	public static function role( string $role ): self {
		return new self( self::ROLE, $role );
	}

	/**
	 * The subject type.
	 *
	 * @return string
	 */
	public function type(): string {
		return $this->type;
	}

	/**
	 * The identifier within the type.
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Whether two subjects are the same one.
	 *
	 * @param self $other The subject to compare with.
	 * @return bool
	 */
	public function equals( self $other ): bool {
		return $this->type === $other->type && $this->id === $other->id;
	}

	/**
	 * A stable string form, for array keys and log lines.
	 *
	 * @return string
	 */
	public function key(): string {
		return '' === $this->id ? $this->type : $this->type . ':' . $this->id;
	}
}
