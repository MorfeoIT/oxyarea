<?php
/**
 * The dashboard blocks.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Blocks;

use OxyArea\Dashboard\DashboardRenderer;
use OxyArea\Dashboard\Tokens;
use OxyArea\Infrastructure\Registrable;
use OxyArea\Infrastructure\Templates;

use const OxyArea\PLUGIN_FILE;

/**
 * Registers the three blocks a dashboard is built from, and their shortcodes.
 *
 * Three, and not thirteen. WordPress already has a paragraph, a heading, a list,
 * a set of columns and a navigation block, and they are better than anything
 * this plugin would write to replace them. What is here is the part core cannot
 * do: the container that resolves *which* dashboard to show, a greeting that
 * knows who is reading it, and a summary of the account.
 */
final class DashboardBlocks implements Registrable {

	/**
	 * The blocks this class registers, by directory name.
	 */
	private const BLOCKS = array( 'dashboard', 'welcome', 'profile-summary' );

	/**
	 * The renderer.
	 *
	 * @var DashboardRenderer
	 */
	private DashboardRenderer $renderer;

	/**
	 * Rendering.
	 *
	 * @var Templates
	 */
	private Templates $templates;

	/**
	 * Build the registrar.
	 *
	 * @param DashboardRenderer $renderer  The renderer.
	 * @param Templates         $templates Rendering.
	 */
	public function __construct( DashboardRenderer $renderer, Templates $templates ) {
		$this->renderer  = $renderer;
		$this->templates = $templates;
	}

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ), 20 );
		add_action( 'init', array( $this, 'register_shortcodes' ), 20 );
	}

	/**
	 * Register the blocks.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		$directory = plugin_dir_path( PLUGIN_FILE ) . 'blocks/';

		foreach ( self::BLOCKS as $name ) {
			$path = $directory . $name;

			if ( ! is_readable( $path . '/block.json' ) ) {
				continue;
			}

			register_block_type(
				$path,
				array(
					'render_callback' => function ( array $attributes ) use ( $name ): string {
						return $this->render( $name, $attributes );
					},
				)
			);
		}
	}

	/**
	 * Register the shortcode twin of each block.
	 *
	 * @return void
	 */
	public function register_shortcodes(): void {
		foreach ( self::BLOCKS as $name ) {
			add_shortcode(
				'oxyarea_' . str_replace( '-', '_', $name ),
				function ( $attributes ) use ( $name ): string {
					return $this->render( $name, is_array( $attributes ) ? $attributes : array() );
				}
			);
		}
	}

	/**
	 * Render one of the blocks.
	 *
	 * @param string               $name       Which block.
	 * @param array<string, mixed> $attributes Its attributes.
	 * @return string
	 */
	private function render( string $name, array $attributes ): string {
		switch ( $name ) {
			case 'dashboard':
				return $this->render_dashboard();
			case 'welcome':
				return $this->render_welcome( $attributes );
			case 'profile-summary':
				return $this->render_profile_summary();
			default:
				return '';
		}
	}

	/**
	 * The dashboard that belongs to whoever is reading.
	 *
	 * Takes no identifier, from the attributes or from anywhere else. What
	 * appears is what the resolver decided, and the only way to change it is to
	 * change the person's roles.
	 *
	 * @return string
	 */
	private function render_dashboard(): string {
		if ( ! is_user_logged_in() ) {
			return $this->templates->render(
				'auth/signed-out',
				array(
					'errors' => array(),
					'notice' => '',
				)
			);
		}

		$html = $this->renderer->render_for( get_current_user_id() );

		if ( '' === $html ) {
			return $this->templates->render(
				'dashboard/none',
				array( 'can_manage' => current_user_can( \OxyArea\Roles\Capabilities::MANAGE_DASHBOARDS ) )
			);
		}

		return '<div class="oxyarea-dashboard">' . $html . '</div>';
	}

	/**
	 * A greeting that knows who is reading it.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	private function render_welcome( array $attributes ): string {
		$text = (string) ( $attributes['text'] ?? '' );

		if ( '' === trim( $text ) ) {
			$text = __( 'Welcome, {{display_name}}.', 'oxyarea' );
		}

		// The authored text is escaped first, then the placeholders are filled in
		// with values that are already escaped. Doing it the other way round would
		// escape the escaping and put &amp;#039; on the page where somebody has an
		// apostrophe in their name.
		$filled = Tokens::replace(
			esc_html( $text ),
			DashboardRenderer::values_for( get_current_user_id() )
		);

		return '<p class="oxyarea-welcome">' . $filled . '</p>';
	}

	/**
	 * What the account says about itself.
	 *
	 * @return string
	 */
	private function render_profile_summary(): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user  = wp_get_current_user();
		$names = wp_roles()->get_names();
		$roles = array();

		foreach ( (array) $user->roles as $role ) {
			$roles[] = translate_user_role( (string) ( $names[ $role ] ?? $role ) );
		}

		return $this->templates->render(
			'dashboard/profile-summary',
			array(
				'user'  => $user,
				'roles' => $roles,
			)
		);
	}
}
