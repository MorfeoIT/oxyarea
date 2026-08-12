<?php
/**
 * The contract a frontend form satisfies.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Auth;

/**
 * A form that both renders itself and handles its own submission.
 *
 * Kept together on purpose. The field names, the nonce action and the code that
 * reads them are the same decision, and splitting them across two classes is how
 * a form ends up checking a nonce nobody issues any more.
 */
interface FormHandler {

	/**
	 * The value of the hidden action field that identifies this form.
	 *
	 * @return string
	 */
	public function action(): string;

	/**
	 * Act on a submission of this form.
	 *
	 * Runs on `init`, before anything is sent to the browser, because signing
	 * somebody in sets cookies. Verifies its own nonce first. On success it
	 * redirects and stops; on failure it records the reason and returns, and the
	 * form renders again further down the same request.
	 *
	 * @return void
	 */
	public function handle(): void;

	/**
	 * The form's HTML.
	 *
	 * @param array<string, mixed> $attributes Block attributes, if any.
	 * @return string
	 */
	public function render( array $attributes = array() ): string;
}
