<?php
/**
 * Dashboard placeholders.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Unit;

use OxyArea\Dashboard\Tokens;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OxyArea\Dashboard\Tokens
 */
final class TokensTest extends TestCase {

	private const VALUES = array(
		'display_name' => 'Alice Rossi',
		'first_name'   => 'Alice',
		'user_id'      => '11',
	);

	public function test_it_fills_in_a_placeholder(): void {
		$this->assertSame(
			'Welcome, Alice Rossi.',
			Tokens::replace( 'Welcome, {{display_name}}.', self::VALUES )
		);
	}

	public function test_it_fills_in_every_occurrence(): void {
		$this->assertSame(
			'Alice and Alice',
			Tokens::replace( '{{first_name}} and {{first_name}}', self::VALUES )
		);
	}

	public function test_spaces_inside_the_braces_are_allowed(): void {
		$this->assertSame( 'Alice', Tokens::replace( '{{  first_name  }}', self::VALUES ) );
	}

	public function test_the_name_is_matched_whatever_its_case(): void {
		$this->assertSame( 'Alice', Tokens::replace( '{{FIRST_NAME}}', self::VALUES ) );
	}

	public function test_a_placeholder_nobody_offered_becomes_nothing(): void {
		// Not left on the page: a mistyped placeholder should look like nothing,
		// rather than like a broken template shown to a customer.
		$this->assertSame( 'Hello .', Tokens::replace( 'Hello {{frist_name}}.', self::VALUES ) );
	}

	public function test_asking_for_something_private_gets_nothing(): void {
		// There is no list of forbidden names, and there does not need to be:
		// nothing is substituted that the caller did not offer.
		$this->assertSame( '', Tokens::replace( '{{user_pass}}', self::VALUES ) );
		$this->assertSame( '', Tokens::replace( '{{user_activation_key}}', self::VALUES ) );
	}

	public function test_text_with_no_placeholders_comes_back_untouched(): void {
		$html = '<p>Nothing to see here.</p>';

		$this->assertSame( $html, Tokens::replace( $html, self::VALUES ) );
	}

	/**
	 * @dataProvider not_placeholders
	 *
	 * @param string $subject Something that only looks like a placeholder.
	 */
	public function test_it_leaves_alone_what_is_not_a_placeholder( string $subject ): void {
		$this->assertSame( $subject, Tokens::replace( $subject, self::VALUES ) );
	}

	/**
	 * Things that must not be treated as placeholders.
	 *
	 * The pattern is narrow on purpose. Anything that allows punctuation,
	 * brackets or spaces inside the name is an invitation to smuggle something
	 * through the substitution.
	 *
	 * @return array<string, array{string}>
	 */
	public static function not_placeholders(): array {
		return array(
			'a single brace'          => array( '{display_name}' ),
			'a php tag'               => array( '<?php echo $user; ?>' ),
			'a function call'         => array( '{{ get_user_meta(1) }}' ),
			'a name with a dash'      => array( '{{display-name}}' ),
			'a name with a dot'       => array( '{{user.email}}' ),
			'a name with a slash'     => array( '{{../secret}}' ),
			'a shortcode'             => array( '[oxyarea_login]' ),
			'an unclosed placeholder' => array( '{{display_name' ),
			'empty braces'            => array( '{{}}' ),
		);
	}

	public function test_it_does_not_substitute_into_what_it_just_substituted(): void {
		// A value that itself looks like a placeholder must not be expanded again.
		$values = array( 'display_name' => '{{user_id}}' );

		$this->assertSame( '{{user_id}}', Tokens::replace( '{{display_name}}', $values ) );
	}

	public function test_it_can_say_whether_there_is_anything_to_do(): void {
		$this->assertTrue( Tokens::present_in( 'Hello {{display_name}}' ) );
		$this->assertFalse( Tokens::present_in( 'Hello there' ) );
		$this->assertFalse( Tokens::present_in( 'Hello {display_name}' ) );
	}

	public function test_the_advertised_names_are_the_ones_a_site_owner_would_expect(): void {
		$this->assertSame(
			array( 'display_name', 'first_name', 'last_name', 'username', 'user_email', 'user_id' ),
			Tokens::known()
		);
	}
}
