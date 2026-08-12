<?php
/**
 * The admin menu.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Admin;

use OxyArea\Infrastructure\Brand;
use OxyArea\Infrastructure\Registrable;
use OxyArea\Roles\Capabilities;

/**
 * Puts OxyArea in the admin sidebar.
 *
 * One entry for now. The specification's full menu — dashboards, content,
 * redirects, users, settings, tools — arrives with the sprints that build those
 * screens, because a menu of items that lead nowhere is worse than a short menu.
 */
final class Menu implements Registrable {

	/**
	 * The roles screen.
	 *
	 * @var RolesScreen
	 */
	private RolesScreen $roles;

	/**
	 * Build the menu.
	 *
	 * @param RolesScreen $roles The roles screen.
	 */
	public function __construct( RolesScreen $roles ) {
		$this->roles = $roles;
	}

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_pages' ) );
	}

	/**
	 * Register the screens.
	 *
	 * @return void
	 */
	public function add_pages(): void {
		add_menu_page(
			Brand::display_name(),
			Brand::name(),
			Capabilities::MANAGE,
			Brand::MENU_SLUG,
			array( $this->roles, 'render' ),
			'dashicons-lock',
			71
		);

		// Until the Overview screen exists, the top-level entry is the roles
		// screen, and the first submenu item says so rather than repeating the
		// plugin's name back at the reader.
		add_submenu_page(
			Brand::MENU_SLUG,
			__( 'Roles', 'oxyarea' ),
			__( 'Roles', 'oxyarea' ),
			Capabilities::MANAGE_ROLES,
			Brand::MENU_SLUG,
			array( $this->roles, 'render' )
		);
	}
}
