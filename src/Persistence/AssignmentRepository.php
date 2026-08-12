<?php
/**
 * The rules, in the database.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use OxyArea\Access\Assignment;
use OxyArea\Access\AssignmentRepositoryInterface;
use OxyArea\Access\ResourceInterface;
use OxyArea\Access\Subject;
use OxyArea\Infrastructure\Migrator;

/**
 * Reads and writes wp_oxyarea_assignments.
 *
 * Every read is cached for the request. Access is asked about the same resource
 * repeatedly — once for the query filter, once when the template renders, once
 * more if a block asks — and three identical queries per page is how a plugin
 * earns a reputation.
 */
final class AssignmentRepository implements AssignmentRepositoryInterface {

	/**
	 * The object cache group.
	 */
	private const CACHE_GROUP = 'oxyarea';

	/**
	 * Effect column value meaning "may see it".
	 */
	private const EFFECT_ALLOW = 1;

	/**
	 * Effect column value meaning "may not".
	 */
	private const EFFECT_DENY = 0;

	/**
	 * Every rule attached to a resource, whether or not it currently applies.
	 *
	 * @param ResourceInterface $target The resource.
	 * @return list<Assignment>
	 */
	public function for_resource( ResourceInterface $target ): array {
		global $wpdb;

		$key    = $this->cache_key( $target );
		$cached = wp_cache_get( $key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			// A persistent object cache is shared with everything else on the
			// server and can hand back whatever was last written under this key.
			// Trusting its shape is how a cache becomes an attack surface.
			return array_values(
				array_filter(
					$cached,
					static fn ( $entry ): bool => $entry instanceof Assignment
				)
			);
		}

		$table = Migrator::table( Migrator::TABLE_ASSIGNMENTS );

		// A table name cannot be a prepared placeholder, and this one is built
		// from a constant of ours plus the site's own prefix: no part of it can
		// come from a request. The values are prepared, and the result is cached
		// on the line after the loop.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT subject_type, subject_id, effect, priority, starts_at, ends_at
				 FROM {$table}
				 WHERE resource_type = %s AND resource_id = %d
				 ORDER BY priority ASC, id ASC",
				$target->get_type(),
				$target->get_id()
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$assignments = array();

		foreach ( (array) $rows as $row ) {
			$assignment = $this->hydrate( (array) $row );

			// A row that cannot be read is skipped, not raised. Corrupt data must
			// not take a site down, and dropping a rule can only narrow access.
			if ( null !== $assignment ) {
				$assignments[] = $assignment;
			}
		}

		wp_cache_set( $key, $assignments, self::CACHE_GROUP );

		return $assignments;
	}

	/**
	 * Replace every rule attached to a resource with the given set.
	 *
	 * @param ResourceInterface $target      The resource.
	 * @param list<Assignment>  $assignments The rules that should exist.
	 * @return void
	 */
	public function replace_for_resource( ResourceInterface $target, array $assignments ): void {
		global $wpdb;

		$table = Migrator::table( Migrator::TABLE_ASSIGNMENTS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write path; the cache is invalidated at the end.
		$wpdb->delete(
			$table,
			array(
				'resource_type' => $target->get_type(),
				'resource_id'   => $target->get_id(),
			),
			array( '%s', '%d' )
		);

		$now = gmdate( 'Y-m-d H:i:s' );

		foreach ( $assignments as $assignment ) {
			if ( ! $assignment instanceof Assignment ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write path; the cache is invalidated at the end.
			$wpdb->insert(
				$table,
				array(
					'subject_type'  => $assignment->subject()->type(),
					'subject_id'    => $assignment->subject()->id(),
					'resource_type' => $target->get_type(),
					'resource_id'   => $target->get_id(),
					'effect'        => $assignment->is_deny() ? self::EFFECT_DENY : self::EFFECT_ALLOW,
					'priority'      => $assignment->priority(),
					'starts_at'     => null,
					'ends_at'       => null,
					'created_at'    => $now,
				),
				array( '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
			);
		}

		wp_cache_delete( $this->cache_key( $target ), self::CACHE_GROUP );

		// The list of what is restricted has just changed shape: a resource may
		// have gained its first rule or lost its last.
		wp_cache_delete( 'restricted_' . $target->get_type(), self::CACHE_GROUP );
	}

	/**
	 * Turn a stored row into an assignment, or nothing if it cannot be read.
	 *
	 * @param array<string, mixed> $row The stored row.
	 * @return Assignment|null
	 */
	private function hydrate( array $row ): ?Assignment {
		try {
			$subject = new Subject(
				(string) ( $row['subject_type'] ?? '' ),
				(string) ( $row['subject_id'] ?? '' )
			);

			return new Assignment(
				$subject,
				(int) ( $row['effect'] ?? self::EFFECT_ALLOW ) === self::EFFECT_DENY
					? Assignment::DENY
					: Assignment::ALLOW,
				(int) ( $row['priority'] ?? 10 ),
				$this->to_date( $row['starts_at'] ?? null ),
				$this->to_date( $row['ends_at'] ?? null )
			);
		} catch ( Exception $e ) {
			unset( $e );

			return null;
		}
	}

	/**
	 * Read a stored datetime as UTC.
	 *
	 * @param mixed $value The stored value.
	 * @return DateTimeImmutable|null
	 */
	private function to_date( $value ): ?DateTimeImmutable {
		if ( ! is_string( $value ) || '' === $value || '0000-00-00 00:00:00' === $value ) {
			return null;
		}

		try {
			return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			unset( $e );

			return null;
		}
	}

	/**
	 * The cache key for a resource.
	 *
	 * @param ResourceInterface $target The resource.
	 * @return string
	 */
	private function cache_key( ResourceInterface $target ): string {
		return $this->key_for( $target->get_type(), $target->get_id() );
	}

	/**
	 * Load the rules for many resources in one query.
	 *
	 * @param string    $type The resource type.
	 * @param list<int> $ids  The resource identifiers.
	 * @return void
	 */
	public function warm( string $type, array $ids ): void {
		global $wpdb;

		$wanted = array();

		foreach ( $ids as $id ) {
			$id = (int) $id;

			if ( $id > 0 && false === wp_cache_get( $this->key_for( $type, $id ), self::CACHE_GROUP ) ) {
				$wanted[ $id ] = $id;
			}
		}

		if ( array() === $wanted ) {
			return;
		}

		$table        = Migrator::table( Migrator::TABLE_ASSIGNMENTS );
		$placeholders = implode( ',', array_fill( 0, count( $wanted ), '%d' ) );
		$parameters   = array_merge( array( $type ), array_values( $wanted ) );

		// The table name is ours; every value is prepared, including the list of
		// identifiers, whose placeholders are counted rather than interpolated.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT resource_id, subject_type, subject_id, effect, priority, starts_at, ends_at
				 FROM {$table}
				 WHERE resource_type = %s AND resource_id IN ({$placeholders})
				 ORDER BY priority ASC, id ASC",
				$parameters
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// Every identifier asked about is cached, including the ones with no rows.
		// Caching only the hits would leave every unrestricted post on the page
		// asking the database again a moment later.
		$grouped = array_fill_keys( array_keys( $wanted ), array() );

		foreach ( (array) $rows as $row ) {
			$row        = (array) $row;
			$resource   = (int) ( $row['resource_id'] ?? 0 );
			$assignment = $this->hydrate( $row );

			if ( null !== $assignment && isset( $grouped[ $resource ] ) ) {
				$grouped[ $resource ][] = $assignment;
			}
		}

		foreach ( $grouped as $id => $assignments ) {
			wp_cache_set( $this->key_for( $type, (int) $id ), $assignments, self::CACHE_GROUP );
		}
	}

	/**
	 * Every resource of a type that anybody has said anything about.
	 *
	 * @param string $type The resource type.
	 * @return list<int>
	 */
	public function restricted_ids( string $type ): array {
		global $wpdb;

		$key    = 'restricted_' . $type;
		$cached = wp_cache_get( $key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return array_values( array_map( 'intval', $cached ) );
		}

		$table = Migrator::table( Migrator::TABLE_ASSIGNMENTS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_col(
			$wpdb->prepare( "SELECT DISTINCT resource_id FROM {$table} WHERE resource_type = %s", $type )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$ids = array_values( array_map( 'intval', (array) $rows ) );

		wp_cache_set( $key, $ids, self::CACHE_GROUP );

		return $ids;
	}

	/**
	 * The cache key for a resource type and identifier.
	 *
	 * @param string $type The resource type.
	 * @param int    $id   The resource identifier.
	 * @return string
	 */
	private function key_for( string $type, int $id ): string {
		return 'assignments_' . $type . '_' . $id;
	}
}
