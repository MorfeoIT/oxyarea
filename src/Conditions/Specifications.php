<?php
/**
 * The conditions a rule carries, as they are stored.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Conditions;

/**
 * Reading and writing a rule's conditions, safely.
 *
 * Stored as JSON in one column rather than as rows in a table of their own, and
 * the reason is what the data is: a handful of pairs that are only ever read
 * together with the rule they belong to, never queried across rules, and never
 * joined. A second table would have bought an index nothing would use and a
 * migration to keep in step.
 *
 * The column is read on the hottest path there is — every sign-in — so what
 * comes out of it is treated as hostile: a value edited by hand, truncated by a
 * migration, or written by a version that has since changed its mind must
 * produce an empty list rather than a warning on somebody's login page.
 */
final class Specifications {

	/**
	 * Turn a stored column into a list of conditions.
	 *
	 * @param mixed $stored What was in the column.
	 * @return list<array{type: string, value: string}>
	 */
	public static function decode( $stored ): array {
		if ( ! is_string( $stored ) || '' === trim( $stored ) ) {
			return array();
		}

		$decoded = json_decode( $stored, true );

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$specifications = array();

		foreach ( $decoded as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$type = isset( $entry['type'] ) && is_string( $entry['type'] ) ? trim( $entry['type'] ) : '';

			if ( '' === $type ) {
				continue;
			}

			$value = isset( $entry['value'] ) && is_scalar( $entry['value'] ) ? (string) $entry['value'] : '';

			$specifications[] = array(
				'type'  => $type,
				'value' => $value,
			);
		}

		return $specifications;
	}

	/**
	 * Turn a list of conditions into something to store.
	 *
	 * An empty list stores as the empty string rather than as `[]`, so that a
	 * rule with no conditions looks in the database exactly like every rule
	 * written before this column existed. That is what makes the migration a
	 * column addition and nothing else.
	 *
	 * @param list<array{type: string, value: string}> $specifications The conditions.
	 * @return string
	 */
	public static function encode( array $specifications ): string {
		if ( array() === $specifications ) {
			return '';
		}

		$clean = array();

		foreach ( $specifications as $specification ) {
			$type = trim( (string) ( $specification['type'] ?? '' ) );

			if ( '' === $type ) {
				continue;
			}

			$clean[] = array(
				'type'  => $type,
				'value' => (string) ( $specification['value'] ?? '' ),
			);
		}

		if ( array() === $clean ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- This class is deliberately free of WordPress so the encoding can be tested without it.
		$encoded = json_encode( $clean );

		return false === $encoded ? '' : $encoded;
	}
}
