<?php
/**
 * Who handles a submitted form.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Auth;

use OxyArea\Infrastructure\Registrable;

/**
 * Routes a submission to the form that owns it.
 *
 * Runs on `init` and not later. Signing somebody in and signing them out both
 * set cookies, and a cookie set after a theme has begun printing is a cookie
 * that never arrives.
 *
 * The controller itself verifies nothing. Each form checks its own nonce and its
 * own conditions, because the check belongs next to the fields it protects.
 */
final class FormController implements Registrable {

	/**
	 * The hidden field naming which form was submitted.
	 */
	public const FIELD = 'oxyarea_action';

	/**
	 * The forms, by action.
	 *
	 * @var array<string, FormHandler>
	 */
	private array $forms = array();

	/**
	 * Build the controller.
	 *
	 * @param list<FormHandler> $forms The forms it routes to.
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
		add_action( 'init', array( $this, 'dispatch' ) );
	}

	/**
	 * Hand the request to the form that claims it.
	 *
	 * @return void
	 */
	public function dispatch(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: '';

		if ( 'POST' !== $method ) {
			return;
		}

		// Reading which form was posted, so that its own handler can check its own
		// nonce. Nothing is acted on here.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = isset( $_POST[ self::FIELD ] ) ? sanitize_key( wp_unslash( $_POST[ self::FIELD ] ) ) : '';

		if ( '' === $action || ! isset( $this->forms[ $action ] ) ) {
			return;
		}

		$this->forms[ $action ]->handle();
	}
}
