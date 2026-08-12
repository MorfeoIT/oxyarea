<?php
/**
 * What happens when the plugin is deleted.
 *
 * Deleting a plugin is not the same as deciding to throw away the private area
 * it was running, so by default **nothing is removed**. An administrator who
 * wants the data gone turns on "delete data on uninstall" first, and then means
 * it.
 *
 * This file runs outside the plugin: WordPress loads it directly, with none of
 * our classes autoloaded, so everything it needs is spelled out here. That
 * duplication is deliberate and has to be kept in step with Migrator and
 * Capabilities by hand.
 *
 * @package OxyArea
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove everything this plugin owns from one site.
 *
 * @param \wpdb $wpdb Database handle.
 * @return void
 */
function oxyarea_uninstall_site( $wpdb ): void {
	$settings = get_option( 'oxyarea_settings', array() );

	// The default is to keep everything. Only an explicit yes removes data.
	if ( ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	$tables = array(
		'oxyarea_assignments',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall, table names from a fixed list.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
	}

	$options = array(
		'oxyarea_settings',
		'oxyarea_db_version',
		'oxyarea_capabilities_version',
		'oxyarea_installed_at',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// The capabilities, from every role that has them. Roles the site created
	// with OxyArea are left alone: they may hold users and other capabilities,
	// and deleting a role out from under its users is not a cleanup.
	$capabilities = array(
		'manage_oxyarea',
		'manage_oxyarea_dashboards',
		'manage_oxyarea_roles',
		'manage_oxyarea_redirects',
	);

	$roles = wp_roles();

	foreach ( $roles->role_objects as $role ) {
		foreach ( $capabilities as $capability ) {
			$role->remove_cap( $capability );
		}
	}
}

global $wpdb;

if ( is_multisite() ) {
	// Each site keeps its own tables, its own settings and its own answer to the
	// question of whether the data should go.
	$oxyarea_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( (array) $oxyarea_sites as $oxyarea_site_id ) {
		switch_to_blog( (int) $oxyarea_site_id );
		oxyarea_uninstall_site( $wpdb );
		restore_current_blog();
	}
} else {
	oxyarea_uninstall_site( $wpdb );
}
