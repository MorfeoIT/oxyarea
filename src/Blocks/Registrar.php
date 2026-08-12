<?php
/**
 * Blocks and shortcodes.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Blocks;

use OxyArea\Auth\FormHandler;
use OxyArea\Infrastructure\Registrable;

use const OxyArea\PLUGIN_FILE;
use const OxyArea\VERSION;

/**
 * Publishes each form as both a block and a shortcode.
 *
 * The same object renders both, so the two can never drift. A site that has not
 * moved to the block editor, or a theme that puts the login form in a widget
 * area, gets the identical markup and the identical security decisions.
 *
 * There is no JavaScript build step. Blocks are registered from their
 * block.json on the server and rendered in PHP; the editor gets one small
 * hand-written file that draws a labelled placeholder. That keeps the
 * distributed package readable as source — which the plugin directory asks for —
 * and spares the project a toolchain it would otherwise have to maintain and
 * ship the output of.
 */
final class Registrar implements Registrable {

	/**
	 * The editor script handle, referenced from every block.json.
	 */
	private const EDITOR_HANDLE = 'oxyarea-blocks';

	/**
	 * The frontend style handle, referenced from every block.json.
	 */
	private const STYLE_HANDLE = 'oxyarea-forms';

	/**
	 * Which form backs which block and shortcode.
	 *
	 * @var array<string, FormHandler>
	 */
	private array $forms = array();

	/**
	 * Build the registrar.
	 *
	 * @param list<FormHandler> $forms The forms to publish.
	 */
	public function __construct( array $forms ) {
		foreach ( $forms as $form ) {
			$this->forms[ $form->action() ] = $form;
		}
	}

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_assets' ), 5 );
		add_action( 'init', array( $this, 'register_blocks' ), 20 );
		add_action( 'init', array( $this, 'register_shortcodes' ), 20 );
	}

	/**
	 * Register the handles the block.json files point at.
	 *
	 * Runs before the blocks, because block.json resolves a handle that is not a
	 * `file:` path by looking for one that already exists.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		wp_register_script(
			self::EDITOR_HANDLE,
			plugins_url( 'assets/js/blocks.js', PLUGIN_FILE ),
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ),
			VERSION,
			true
		);

		wp_set_script_translations( self::EDITOR_HANDLE, 'oxyarea', plugin_dir_path( PLUGIN_FILE ) . 'languages' );

		wp_register_style(
			self::STYLE_HANDLE,
			plugins_url( 'assets/css/forms.css', PLUGIN_FILE ),
			array(),
			VERSION
		);
	}

	/**
	 * Register every block that has a block.json and a form behind it.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		$directory = plugin_dir_path( PLUGIN_FILE ) . 'blocks/';

		foreach ( array_keys( $this->forms ) as $name ) {
			$path = $directory . $name;

			if ( ! is_readable( $path . '/block.json' ) ) {
				continue;
			}

			register_block_type(
				$path,
				array(
					'render_callback' => function ( array $attributes ) use ( $name ): string {
						return $this->forms[ $name ]->render( $attributes );
					},
				)
			);
		}
	}

	/**
	 * Register the shortcode twin of every block.
	 *
	 * @return void
	 */
	public function register_shortcodes(): void {
		foreach ( $this->forms as $name => $form ) {
			add_shortcode(
				'oxyarea_' . str_replace( '-', '_', $name ),
				function ( $attributes ) use ( $form ): string {
					return $form->render( is_array( $attributes ) ? $this->camel_case( $attributes ) : array() );
				}
			);
		}
	}

	/**
	 * Turn shortcode attribute names into the block's spelling.
	 *
	 * Shortcodes lowercase their attribute names before anybody sees them, so
	 * `showRemember` arrives as `showremember` and would silently miss. The forms
	 * read one set of names; this is where the other set is translated into it.
	 *
	 * @param array<string, mixed> $attributes Shortcode attributes.
	 * @return array<string, mixed>
	 */
	private function camel_case( array $attributes ): array {
		$known = array(
			'showremember'     => 'showRemember',
			'showlostpassword' => 'showLostPassword',
			'showpassword'     => 'showPassword',
			'redirectto'       => 'redirectTo',
			'label'            => 'label',
		);

		$translated = array();

		foreach ( $attributes as $key => $value ) {
			$lower = strtolower( (string) $key );

			if ( isset( $known[ $lower ] ) ) {
				$translated[ $known[ $lower ] ] = $value;
			}
		}

		return $translated;
	}
}
