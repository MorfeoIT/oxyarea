<?php
/**
 * A refusal from the role manager.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Roles;

use RuntimeException;

/**
 * Thrown when a role operation is refused.
 *
 * Unlike the container's exceptions, these are read by a human administrator, so
 * the messages are translated and already escaped at the point they are thrown.
 * Every one of them names something the person tried to do and why it was not
 * allowed, because "operation failed" teaches nobody anything.
 */
final class RoleException extends RuntimeException {
}
