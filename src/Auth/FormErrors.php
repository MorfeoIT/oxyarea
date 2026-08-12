<?php
/**
 * What went wrong, for the form that is about to render again.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Auth;

/**
 * Carries failures from the handler on `init` to the form later in the request.
 *
 * Deliberately request-scoped and in memory. A failed submission does not
 * redirect, so there is nothing to survive; storing this in a transient or a
 * cookie would mean a stale error appearing on somebody else's page load.
 *
 * Messages held here are plain text. They are escaped where they are printed.
 */
final class FormErrors {

	/**
	 * Messages, by form action.
	 *
	 * @var array<string, list<string>>
	 */
	private array $errors = array();

	/**
	 * Record a failure.
	 *
	 * @param string $form    The form's action.
	 * @param string $message What to tell the person, in plain text.
	 * @return void
	 */
	public function add( string $form, string $message ): void {
		$this->errors[ $form ][] = $message;
	}

	/**
	 * Whether a form has anything to report.
	 *
	 * @param string $form The form's action.
	 * @return bool
	 */
	public function has( string $form ): bool {
		return array() !== ( $this->errors[ $form ] ?? array() );
	}

	/**
	 * What a form has to report.
	 *
	 * @param string $form The form's action.
	 * @return list<string>
	 */
	public function get( string $form ): array {
		return $this->errors[ $form ] ?? array();
	}
}
