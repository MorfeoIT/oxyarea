<?php
/**
 * The placeholders a dashboard may contain.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Dashboard;

/**
 * Replaces `{{display_name}}` and its handful of siblings, and nothing else.
 *
 * This is the feature that makes one template serve a thousand people, and it is
 * also the obvious place to put a scripting language. The specification forbids
 * that in as many words, and the reason is worth restating: an admin field that
 * evaluates PHP turns every account that can edit a dashboard into an account
 * that can run code on the server, which is the same as an administrator, which
 * means the role editor two sprints ago was decoration.
 *
 * So: substitution only, from a list the caller supplies. No expressions, no
 * conditionals, no function calls, no way to reach a value the caller did not
 * decide to offer.
 *
 * Unknown placeholders are removed rather than left on the page. A mistyped
 * `{{frist_name}}` should look like nothing, not like a broken template shown to
 * a customer — and it means somebody who writes `{{user_pass}}` hoping to see
 * something gets an empty space, both times.
 *
 * Values arrive already escaped. This class has no idea whether it is filling in
 * a paragraph or an attribute, and guessing is how a name containing a quotation
 * mark becomes a hole.
 */
final class Tokens {

	/**
	 * The pattern a placeholder takes.
	 *
	 * Deliberately narrow: letters, digits and underscores between double braces,
	 * with optional spaces. Anything more permissive is an invitation to smuggle
	 * something through the substitution.
	 */
	private const PATTERN = '/\{\{\s*([a-z0-9_]+)\s*\}\}/i';

	/**
	 * Fill in the placeholders.
	 *
	 * @param string                $subject The text or markup to fill in.
	 * @param array<string, string> $values  Values by placeholder name, already escaped.
	 * @return string
	 */
	public static function replace( string $subject, array $values ): string {
		$replaced = preg_replace_callback(
			self::PATTERN,
			static function ( array $found ) use ( $values ): string {
				$name = strtolower( $found[1] );

				return isset( $values[ $name ] ) ? (string) $values[ $name ] : '';
			},
			$subject
		);

		// preg_replace_callback returns null on failure, which for this pattern
		// means the subject was longer than PCRE's backtrack limit. Returning the
		// original is the harmless answer: placeholders stay visible, and nothing
		// half-substituted reaches the page.
		return null === $replaced ? $subject : $replaced;
	}

	/**
	 * The placeholder names this class recognises, for showing in the editor.
	 *
	 * The list is documentation, not enforcement: what actually gets substituted
	 * is whatever the caller passes to replace(). Keeping the two in step is the
	 * caller's job, and there is one caller.
	 *
	 * @return list<string>
	 */
	public static function known(): array {
		return array(
			'display_name',
			'first_name',
			'last_name',
			'username',
			'user_email',
			'user_id',
		);
	}

	/**
	 * Whether a string contains anything this class would act on.
	 *
	 * @param string $subject The text to look at.
	 * @return bool
	 */
	public static function present_in( string $subject ): bool {
		return 1 === preg_match( self::PATTERN, $subject );
	}
}
