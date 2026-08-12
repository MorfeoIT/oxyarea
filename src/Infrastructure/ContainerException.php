<?php
/**
 * Container failures.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Infrastructure;

use RuntimeException;

/**
 * Thrown when a service cannot be resolved.
 *
 * Every case this covers is a programming mistake made by OxyArea or by an
 * add-on, never something a site administrator can cause or fix, so the messages
 * are developer-facing and untranslated.
 */
final class ContainerException extends RuntimeException {
}
