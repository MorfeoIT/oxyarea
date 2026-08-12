<?php
/**
 * What happens when the plugin is switched off.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Infrastructure;

/**
 * Stops the plugin without taking anything with it.
 *
 * Deactivation is a diagnostic step as often as it is a decision. Somebody is
 * bisecting a conflict and will switch OxyArea back on in ninety seconds; if
 * that costs them their dashboards, the plugin has done something unforgivable.
 *
 * So: no tables dropped, no options deleted, no capabilities removed, no
 * assignments cleared. Only work that would keep running is stopped.
 */
final class Deactivator {

	/**
	 * Stop the plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Rewrite rules for the dashboard routes are added in a later sprint;
		// flushing here is what removes them cleanly when they exist, and is
		// harmless until then.
		flush_rewrite_rules();

		/**
		 * Fires when OxyArea is deactivated.
		 *
		 * Add-ons unschedule their own work here. An add-on that deletes data in
		 * response to this hook is misusing it.
		 *
		 * @since 0.1.0
		 */
		do_action( 'oxyarea_deactivate' );
	}
}
