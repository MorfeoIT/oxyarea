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
	 * The redirects screen.
	 *
	 * @var RedirectsScreen
	 */
	private RedirectsScreen $redirects;

	/**
	 * The dashboard preview.
	 *
	 * @var DashboardPreviewScreen
	 */
	private DashboardPreviewScreen $preview;

	/**
	 * The settings screen.
	 *
	 * @var SettingsScreen
	 */
	private SettingsScreen $settings;

	/**
	 * The tools screen.
	 *
	 * @var ToolsScreen
	 */
	private ToolsScreen $tools;

	/**
	 * The setup wizard.
	 *
	 * @var Wizard
	 */
	private Wizard $wizard;

	/**
	 * Build the menu.
	 *
	 * @param RolesScreen            $roles     The roles screen.
	 * @param RedirectsScreen        $redirects The redirects screen.
	 * @param DashboardPreviewScreen $preview   The dashboard preview.
	 * @param SettingsScreen         $settings  The settings screen.
	 * @param ToolsScreen            $tools     The tools screen.
	 * @param Wizard                 $wizard    The setup wizard.
	 */
	public function __construct(
		RolesScreen $roles,
		RedirectsScreen $redirects,
		DashboardPreviewScreen $preview,
		SettingsScreen $settings,
		ToolsScreen $tools,
		Wizard $wizard
	) {
		$this->roles     = $roles;
		$this->redirects = $redirects;
		$this->preview   = $preview;
		$this->settings  = $settings;
		$this->tools     = $tools;
		$this->wizard    = $wizard;
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

		add_submenu_page(
			Brand::MENU_SLUG,
			__( 'Dashboard preview', 'oxyarea' ),
			__( 'Preview', 'oxyarea' ),
			Capabilities::MANAGE_DASHBOARDS,
			DashboardPreviewScreen::SLUG,
			array( $this->preview, 'render' )
		);

		add_submenu_page(
			Brand::MENU_SLUG,
			__( 'Redirects', 'oxyarea' ),
			__( 'Redirects', 'oxyarea' ),
			Capabilities::MANAGE_REDIRECTS,
			RedirectsScreen::SLUG,
			array( $this->redirects, 'render' )
		);

		add_submenu_page(
			Brand::MENU_SLUG,
			__( 'Settings', 'oxyarea' ),
			__( 'Settings', 'oxyarea' ),
			Capabilities::MANAGE,
			SettingsScreen::SLUG,
			array( $this->settings, 'render' )
		);

		add_submenu_page(
			Brand::MENU_SLUG,
			__( 'Tools', 'oxyarea' ),
			__( 'Tools', 'oxyarea' ),
			Capabilities::MANAGE,
			ToolsScreen::SLUG,
			array( $this->tools, 'render' )
		);

		add_submenu_page(
			Brand::MENU_SLUG,
			__( 'Set up a private area', 'oxyarea' ),
			__( 'Setup', 'oxyarea' ),
			Capabilities::MANAGE,
			Wizard::SLUG,
			array( $this->wizard, 'render' )
		);
	}
}
