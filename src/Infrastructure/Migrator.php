<?php
/**
 * Database schema, and how it moves forward.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Infrastructure;

/**
 * Creates and upgrades the tables OxyArea owns.
 *
 * Migrations are numbered, ordered and idempotent. They run at activation and,
 * as a safety net, on the first request after an upgrade that raised the schema
 * version, because a plugin updated over FTP or by WP-CLI never fires an
 * activation hook.
 */
final class Migrator {

	/**
	 * The option holding the schema version applied to this site.
	 */
	public const VERSION_OPTION = 'oxyarea_db_version';

	/**
	 * The schema version this code expects.
	 */
	public const TARGET_VERSION = 1;

	/**
	 * The assignments table, without the WordPress prefix.
	 */
	public const TABLE_ASSIGNMENTS = 'oxyarea_assignments';

	/**
	 * Whether the stored schema is behind the code.
	 *
	 * @return bool
	 */
	public function needs_migration(): bool {
		return $this->current_version() < self::TARGET_VERSION;
	}

	/**
	 * The schema version currently applied.
	 *
	 * @return int
	 */
	public function current_version(): int {
		return (int) get_option( self::VERSION_OPTION, 0 );
	}

	/**
	 * Bring the schema up to date.
	 *
	 * Each step records its own version, so a run interrupted halfway resumes
	 * from where it stopped rather than starting again.
	 *
	 * @return void
	 */
	public function migrate(): void {
		$from = $this->current_version();

		foreach ( $this->migrations() as $version => $migration ) {
			if ( $version <= $from ) {
				continue;
			}

			$migration();

			update_option( self::VERSION_OPTION, $version, false );
		}
	}

	/**
	 * The full name of one of OxyArea's tables.
	 *
	 * @param string $table Table name without the WordPress prefix.
	 * @return string
	 */
	public static function table( string $table ): string {
		global $wpdb;

		return $wpdb->prefix . $table;
	}

	/**
	 * The migrations, in order.
	 *
	 * @return array<int, callable(): void>
	 */
	private function migrations(): array {
		return array(
			1 => fn (): void => $this->create_assignments_table(),
		);
	}

	/**
	 * Who is allowed to see what.
	 *
	 * This is the one relational fact the whole product turns on, which is why it
	 * is a table and not post meta: a site with two thousand customers and forty
	 * documents each is eighty thousand rows to be filtered by subject, and a
	 * serialised option cannot be asked that question.
	 *
	 * The subject columns are deliberately generic. In the free plugin a subject
	 * is a role, or the fact of being logged in at all. PRO adds users, companies
	 * and capabilities as further subject types without touching the schema.
	 *
	 * @return void
	 */
	private function create_assignments_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table( self::TABLE_ASSIGNMENTS );
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			subject_type varchar(32) NOT NULL,
			subject_id varchar(191) NOT NULL DEFAULT '',
			resource_type varchar(32) NOT NULL,
			resource_id bigint(20) unsigned NOT NULL DEFAULT 0,
			effect tinyint(1) unsigned NOT NULL DEFAULT 1,
			priority smallint(5) NOT NULL DEFAULT 10,
			starts_at datetime DEFAULT NULL,
			ends_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY assignment (resource_type,resource_id,subject_type,subject_id),
			KEY resource (resource_type,resource_id),
			KEY subject (subject_type,subject_id),
			KEY window_end (ends_at)
		) {$collate};";

		dbDelta( $sql );
	}
}
