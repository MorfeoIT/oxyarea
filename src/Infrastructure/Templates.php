<?php
/**
 * Rendering, and letting a theme take it over.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Infrastructure;

use const OxyArea\PLUGIN_FILE;

/**
 * Renders a template, preferring the theme's copy over the plugin's.
 *
 * A theme drops `oxyarea/auth/login.php` into its own directory and owns that
 * form from then on, without touching the plugin and without losing it at the
 * next update. It is the oldest extension mechanism WordPress has, it is what
 * WooCommerce taught everybody to expect, and it costs one function.
 *
 * The context is extracted into the template's scope. Templates receive plain
 * values and escape them themselves, because a template is the place that knows
 * whether a value is going into an attribute, a URL or a paragraph.
 */
final class Templates {

	/**
	 * The directory a theme puts its overrides in.
	 */
	private const THEME_DIRECTORY = 'oxyarea';

	/**
	 * Render a template and return what it produced.
	 *
	 * An unknown template returns an empty string rather than an error. The
	 * alternative is a fatal on somebody's front page because a file was renamed.
	 *
	 * @param string               $name    Template name, without extension, relative to the templates directory.
	 * @param array<string, mixed> $context Values the template may use.
	 * @return string
	 */
	public function render( string $name, array $context = array() ): string {
		$file = $this->locate( $name );

		if ( '' === $file ) {
			return '';
		}

		ob_start();

		( static function ( string $oxyarea_template, array $oxyarea_context ): void {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- The template's whole purpose is to receive these as named variables; the keys are ours, never a request's.
			extract( $oxyarea_context, EXTR_SKIP );

			require $oxyarea_template;
		} )( $file, $context );

		return (string) ob_get_clean();
	}

	/**
	 * Where a template lives: the theme's copy if there is one, ours otherwise.
	 *
	 * @param string $name Template name, without extension.
	 * @return string Absolute path, or an empty string if there is no such template.
	 */
	private function locate( string $name ): string {
		// The name comes from our own code, never from a request. Normalising it
		// anyway costs nothing and means a future caller cannot turn this into a
		// way of reading files elsewhere on the disk.
		$name = str_replace( '\\', '/', $name );
		$name = preg_replace( '#[^a-z0-9/_-]#i', '', $name );
		$name = (string) $name;

		if ( '' === $name || false !== strpos( $name, '..' ) ) {
			return '';
		}

		$relative = self::THEME_DIRECTORY . '/' . $name . '.php';
		$theme    = locate_template( array( $relative ) );

		if ( '' !== $theme ) {
			return $theme;
		}

		$plugin = plugin_dir_path( PLUGIN_FILE ) . 'templates/' . $name . '.php';

		return is_readable( $plugin ) ? $plugin : '';
	}
}
