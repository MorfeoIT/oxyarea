<?php
/**
 * Taking a configuration out, and bringing one in.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Admin;

use InvalidArgumentException;
use OxyArea\Infrastructure\Registrable;
use OxyArea\Roles\Capabilities;
use OxyArea\Tools\Porter;

/**
 * Export to a file, import from one.
 *
 * The reason this exists in the free plugin rather than being held back: a site
 * owner who cannot get their configuration out of a plugin is a site owner the
 * plugin has trapped. "No vendor lock-in for customer data" is one of the
 * product principles, and an export button is what that principle looks like
 * when it is real rather than stated.
 *
 * Imported dashboards arrive as **drafts**. A file can describe a private area;
 * it should not be able to publish one on somebody's site the moment it is
 * opened.
 */
final class ToolsScreen implements Registrable {

	/**
	 * The page slug.
	 */
	public const SLUG = 'oxyarea-tools';

	/**
	 * The largest import file that will be read.
	 *
	 * Generous for a configuration and small enough that a mistaken upload of
	 * something else is refused before it is parsed.
	 */
	private const MAX_BYTES = 2097152;

	/**
	 * The porter.
	 *
	 * @var Porter
	 */
	private Porter $porter;

	/**
	 * Build the screen.
	 *
	 * @param Porter $porter The porter.
	 */
	public function __construct( Porter $porter ) {
		$this->porter = $porter;
	}

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_oxyarea_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_oxyarea_import', array( $this, 'handle_import' ) );
	}

	/**
	 * Draw the screen.
	 *
	 * @return void
	 */
	public function render(): void {
		$this->require_capability();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Tools', 'oxyarea' ) . '</h1>';

		Notices::show();

		echo '<h2>' . esc_html__( 'Export', 'oxyarea' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Settings, redirect rules and dashboards, as one file. It contains no personal data: no users, no accounts, no content anybody has written for a particular person.', 'oxyarea' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'oxyarea_export' );
		echo '<input type="hidden" name="action" value="oxyarea_export" />';
		submit_button( __( 'Download the file', 'oxyarea' ), 'secondary' );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Import', 'oxyarea' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Adds to what is here rather than replacing it. Dashboards arrive as drafts, and anything the file asks for that this site cannot do — a role it does not have, a destination on another domain — is skipped and listed.', 'oxyarea' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'oxyarea_import' );
		echo '<input type="hidden" name="action" value="oxyarea_import" />';
		echo '<p><label for="oxyarea-import-file">' . esc_html__( 'The file', 'oxyarea' ) . '</label> ';
		echo '<input type="file" name="blueprint" id="oxyarea-import-file" accept="application/json,.json" required /></p>';
		submit_button( __( 'Import', 'oxyarea' ), 'secondary' );
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Send the file.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		check_admin_referer( 'oxyarea_export' );
		$this->require_capability();

		$json = $this->porter->export();

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=oxyarea-' . gmdate( 'Y-m-d' ) . '.json' );
		header( 'Content-Length: ' . strlen( $json ) );

		// The body is JSON this plugin just built, being sent as a file rather
		// than rendered as a page. Escaping it for HTML would corrupt it.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $json;

		exit;
	}

	/**
	 * Read the file.
	 *
	 * @return void
	 */
	public function handle_import(): void {
		check_admin_referer( 'oxyarea_import' );
		$this->require_capability();

		$json = $this->uploaded_contents();

		if ( '' === $json ) {
			$this->go_back();
		}

		try {
			$applied = $this->porter->import( $json );
		} catch ( InvalidArgumentException $e ) {
			Notices::remember( 'error', esc_html( $e->getMessage() ) );

			$this->go_back();
		}

		$message = sprintf(
			/* translators: 1: number of settings, 2: number of redirect rules, 3: number of dashboards. */
			esc_html__( 'Imported: %1$d settings, %2$d redirect rules, %3$d dashboards (as drafts).', 'oxyarea' ),
			(int) $applied['settings'],
			(int) $applied['redirects'],
			(int) $applied['dashboards']
		);

		// What was skipped is said out loud. An import that quietly drops half the
		// file is worse than one that fails, because the site owner walks away
		// believing something untrue.
		if ( array() !== $applied['skipped'] ) {
			$message .= ' ' . esc_html__( 'Skipped:', 'oxyarea' ) . ' ' . esc_html( implode( ' ', $applied['skipped'] ) );
		}

		Notices::remember( array() === $applied['skipped'] ? 'success' : 'error', $message );

		$this->go_back();
	}

	/**
	 * The uploaded file's contents, or an empty string with a message left behind.
	 *
	 * @return string
	 */
	private function uploaded_contents(): string {
		// An upload is not a string and cannot be run through a sanitiser as one:
		// what matters about it is checked field by field below, and the only
		// check that decides anything is is_uploaded_file().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by the caller; each field is validated on its own terms below.
		$upload = isset( $_FILES['blueprint'] ) && is_array( $_FILES['blueprint'] ) ? $_FILES['blueprint'] : array();

		$error = isset( $upload['error'] ) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $error ) {
			Notices::remember( 'error', esc_html__( 'That file did not arrive. Try again.', 'oxyarea' ) );

			return '';
		}

		$path = isset( $upload['tmp_name'] ) ? sanitize_text_field( (string) $upload['tmp_name'] ) : '';

		// The one check that matters about the path: it has to be a file PHP
		// itself just received, not one named in the request.
		if ( '' === $path || ! is_uploaded_file( $path ) ) {
			Notices::remember( 'error', esc_html__( 'That file did not arrive. Try again.', 'oxyarea' ) );

			return '';
		}

		if ( filesize( $path ) > self::MAX_BYTES ) {
			Notices::remember( 'error', esc_html__( 'That file is far larger than a configuration should be, so it has not been read.', 'oxyarea' ) );

			return '';
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading an upload PHP has already placed on disk; WP_Filesystem is for the plugin's own directories.

		return is_string( $contents ) ? $contents : '';
	}

	/**
	 * Refuse anybody who may not do this.
	 *
	 * @return void
	 */
	private function require_capability(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to use these tools.', 'oxyarea' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Back to the screen.
	 *
	 * @return never
	 */
	private function go_back() {
		wp_safe_redirect( add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) ) );

		exit;
	}
}
