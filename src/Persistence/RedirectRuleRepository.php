<?php
/**
 * The redirect rules, in the database.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Persistence;

use Exception;
use OxyArea\Access\Subject;
use OxyArea\Infrastructure\Migrator;
use OxyArea\Redirect\RedirectEvent;
use OxyArea\Conditions\Specifications;
use OxyArea\Redirect\RedirectRule;
use OxyArea\Redirect\RuleRepositoryInterface;

/**
 * Reads and writes wp_oxyarea_redirect_rules.
 *
 * Rules for an event are read once per request and cached. Signing in reads them
 * exactly once; the admin screen reads them all and can afford to.
 */
final class RedirectRuleRepository implements RuleRepositoryInterface {

	/**
	 * The object cache group.
	 */
	private const CACHE_GROUP = 'oxyarea';

	/**
	 * Every rule for a moment.
	 *
	 * @param string $event The moment.
	 * @return list<RedirectRule>
	 */
	public function for_event( string $event ): array {
		global $wpdb;

		$key    = 'redirects_' . $event;
		$cached = wp_cache_get( $key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return array_values(
				array_filter( $cached, static fn ( $entry ): bool => $entry instanceof RedirectRule )
			);
		}

		$table = Migrator::table( Migrator::TABLE_REDIRECT_RULES );

		// The table name is ours plus the site's prefix and cannot come from a
		// request; the value is prepared; the result is cached below.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE event = %s ORDER BY id ASC", $event ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$rules = $this->hydrate_all( (array) $rows );

		wp_cache_set( $key, $rules, self::CACHE_GROUP );

		return $rules;
	}

	/**
	 * Every rule there is.
	 *
	 * @return list<RedirectRule>
	 */
	public function all(): array {
		global $wpdb;

		$table = Migrator::table( Migrator::TABLE_REDIRECT_RULES );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY event ASC, id ASC", ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return $this->hydrate_all( (array) $rows );
	}

	/**
	 * Store a rule.
	 *
	 * @param RedirectRule $rule The rule.
	 * @return RedirectRule
	 */
	public function save( RedirectRule $rule ): RedirectRule {
		global $wpdb;

		$subject = $rule->subject();

		$data = array(
			'event'        => $rule->event(),
			'subject_type' => null === $subject ? '' : $subject->type(),
			'subject_id'   => null === $subject ? '' : $subject->id(),
			'destination'  => $rule->destination(),
			'priority'     => $rule->priority(),
			'enabled'      => $rule->is_enabled() ? 1 : 0,
			'conditions'   => Specifications::encode( $rule->conditions() ),
		);

		$formats = array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' );
		$table   = Migrator::table( Migrator::TABLE_REDIRECT_RULES );

		if ( $rule->id() > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write path; the cache is flushed below.
			$wpdb->update( $table, $data, array( 'id' => $rule->id() ), $formats, array( '%d' ) );

			$this->flush( $rule->event() );

			return $rule;
		}

		$data['created_at'] = gmdate( 'Y-m-d H:i:s' );
		$formats[]          = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write path; the cache is flushed below.
		$wpdb->insert( $table, $data, $formats );

		$this->flush( $rule->event() );

		return $rule->with_id( (int) $wpdb->insert_id );
	}

	/**
	 * Remove a rule.
	 *
	 * @param int $id The identifier.
	 * @return void
	 */
	public function delete( int $id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write path; the cache is flushed below.
		$wpdb->delete( Migrator::table( Migrator::TABLE_REDIRECT_RULES ), array( 'id' => $id ), array( '%d' ) );

		$this->flush();
	}

	/**
	 * Turn a rule on or off.
	 *
	 * @param int  $id      The identifier.
	 * @param bool $enabled Whether it should count.
	 * @return void
	 */
	public function set_enabled( int $id, bool $enabled ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write path; the cache is flushed below.
		$wpdb->update(
			Migrator::table( Migrator::TABLE_REDIRECT_RULES ),
			array( 'enabled' => $enabled ? 1 : 0 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);

		$this->flush();
	}

	/**
	 * Turn stored rows into rules, skipping any that cannot be read.
	 *
	 * @param array<int, mixed> $rows The stored rows.
	 * @return list<RedirectRule>
	 */
	private function hydrate_all( array $rows ): array {
		$rules = array();

		foreach ( $rows as $row ) {
			$rule = $this->hydrate( (array) $row );

			// A rule nobody can read is dropped rather than raised. Dropping one
			// falls back to a less specific rule or to the site's front page,
			// which is a dull outcome; a fatal on sign-in is not.
			if ( null !== $rule ) {
				$rules[] = $rule;
			}
		}

		return $rules;
	}

	/**
	 * Turn one stored row into a rule.
	 *
	 * @param array<string, mixed> $row The stored row.
	 * @return RedirectRule|null
	 */
	private function hydrate( array $row ): ?RedirectRule {
		try {
			$type = (string) ( $row['subject_type'] ?? '' );

			$subject = '' === $type
				? null
				: new Subject( $type, (string) ( $row['subject_id'] ?? '' ) );

			return new RedirectRule(
				(string) ( $row['event'] ?? '' ),
				$subject,
				(string) ( $row['destination'] ?? '' ),
				(int) ( $row['priority'] ?? 10 ),
				1 === (int) ( $row['enabled'] ?? 1 ),
				(int) ( $row['id'] ?? 0 ),
				Specifications::decode( $row['conditions'] ?? null )
			);
		} catch ( Exception $e ) {
			unset( $e );

			return null;
		}
	}

	/**
	 * Forget what was cached.
	 *
	 * @param string $event Only this event, or every event when empty.
	 * @return void
	 */
	private function flush( string $event = '' ): void {
		$events = '' === $event ? RedirectEvent::all() : array( $event );

		foreach ( $events as $one ) {
			wp_cache_delete( 'redirects_' . $one, self::CACHE_GROUP );
		}
	}
}
