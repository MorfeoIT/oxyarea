<?php
/**
 * Who is allowed to administer OxyArea.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Roles;

/**
 * The plugin's own administrative capabilities.
 *
 * These govern who may configure the private area, not who may see it. Access to
 * private content is decided by the access resolver, per resource, per user, and
 * has nothing to do with this list.
 *
 * Splitting them at all is what lets a site give an office manager the run of
 * the dashboards without also handing over the ability to edit roles, which is
 * effectively the ability to grant themselves anything.
 */
final class Capabilities {

	/**
	 * See and use the OxyArea admin screens.
	 */
	public const MANAGE = 'manage_oxyarea';

	/**
	 * Create and edit dashboards.
	 */
	public const MANAGE_DASHBOARDS = 'manage_oxyarea_dashboards';

	/**
	 * Create roles and edit their capabilities.
	 */
	public const MANAGE_ROLES = 'manage_oxyarea_roles';

	/**
	 * Edit the redirect rules.
	 */
	public const MANAGE_REDIRECTS = 'manage_oxyarea_redirects';

	/**
	 * Bumped whenever the list below changes, so that an upgrade re-grants.
	 */
	private const GRANT_VERSION = 1;

	/**
	 * The option recording the granted version.
	 */
	private const GRANT_OPTION = 'oxyarea_capabilities_version';

	/**
	 * Every capability the free plugin defines.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::MANAGE,
			self::MANAGE_DASHBOARDS,
			self::MANAGE_ROLES,
			self::MANAGE_REDIRECTS,
		);
	}

	/**
	 * Make sure administrators hold the capabilities.
	 *
	 * Only the administrator role is touched, and only to add. A site that has
	 * deliberately given manage_oxyarea to an editor keeps that arrangement; a
	 * site that has deliberately taken it away from administrators will find it
	 * back after an upgrade, which is the lesser of the two surprises.
	 *
	 * @return void
	 */
	public static function ensure_granted(): void {
		if ( (int) get_option( self::GRANT_OPTION, 0 ) === self::GRANT_VERSION ) {
			return;
		}

		$administrator = get_role( 'administrator' );

		if ( null === $administrator ) {
			return;
		}

		foreach ( self::all() as $capability ) {
			$administrator->add_cap( $capability );
		}

		update_option( self::GRANT_OPTION, self::GRANT_VERSION, false );
	}
}
