<?php
/**
 * The open-redirect guard.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Unit;

use OxyArea\Auth\SafeRedirect;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OxyArea\Auth\SafeRedirect
 */
final class SafeRedirectTest extends TestCase {

	private const HOME     = 'example.test';
	private const FALLBACK = '/dashboard/';

	/**
	 * @dataProvider allowed
	 *
	 * @param string $candidate Where the request asked to go.
	 */
	public function test_it_allows_destinations_on_this_site( string $candidate ): void {
		$this->assertSame( $candidate, $this->sanitise( $candidate ) );
	}

	/**
	 * Destinations that are this site.
	 *
	 * @return array<string, array{string}>
	 */
	public static function allowed(): array {
		return array(
			'a root-relative path'      => array( '/private/' ),
			'a path with a query'       => array( '/private/?tab=documents' ),
			'a path with a fragment'    => array( '/private/#documents' ),
			'the site itself over https' => array( 'https://example.test/private/' ),
			'the site itself over http' => array( 'http://example.test/private/' ),
			'the bare root'             => array( '/' ),
		);
	}

	/**
	 * @dataProvider refused
	 *
	 * @param string $candidate Where the request asked to go.
	 */
	public function test_it_refuses_everything_else( string $candidate ): void {
		$this->assertSame(
			self::FALLBACK,
			$this->sanitise( $candidate ),
			sprintf( '%s should not have been followed.', $candidate )
		);
	}

	/**
	 * Destinations that are somebody else, or nonsense.
	 *
	 * The phishing case is the first one and the reason the rest exist: a link to
	 * the real login form that lands the visitor somewhere else once they have
	 * signed in and stopped paying attention.
	 *
	 * @return array<string, array{string}>
	 */
	public static function refused(): array {
		return array(
			'another site'                     => array( 'https://evil.example/' ),
			'another site over http'           => array( 'http://evil.example/' ),
			'protocol-relative'                => array( '//evil.example/' ),
			'protocol-relative with a path'    => array( '//evil.example/private/' ),
			'javascript'                       => array( 'javascript:alert(1)' ),
			'javascript in capitals'           => array( 'JavaScript:alert(1)' ),
			'a data url'                       => array( 'data:text/html,<script>alert(1)</script>' ),
			'a vbscript url'                   => array( 'vbscript:msgbox(1)' ),
			'the host in the userinfo'         => array( 'https://example.test@evil.example/' ),
			'the host as a password'           => array( 'https://user:example.test@evil.example/' ),
			'a subdomain of somebody else'     => array( 'https://example.test.evil.example/' ),
			'the host as a path'               => array( 'https://evil.example/example.test' ),
			'a backslash browsers read as two' => array( '/\\evil.example/' ),
			'a backslash after the scheme'     => array( 'https:\\\\evil.example/' ),
			'a newline'                        => array( "/private/\nLocation: https://evil.example" ),
			'a carriage return'                => array( "/private/\r\nSet-Cookie: a=b" ),
			'a null byte in the middle'        => array( "/private\0/evil" ),
			'a tab inside the scheme'          => array( "java\tscript:alert(1)" ),
			'a relative path with no anchor'   => array( 'private/' ),
			'a bare word'                      => array( 'evil.example' ),
			'nothing at all'                   => array( '' ),
			'only whitespace'                  => array( '   ' ),
		);
	}

	public function test_a_trailing_null_byte_is_stripped_rather_than_refused(): void {
		// PHP's trim() removes NUL along with whitespace, so this never reaches
		// the control-character check. That is safe, and the reason is worth
		// writing down: the trimmed value is what every check runs against *and*
		// what is returned, so there is no gap between what was inspected and
		// what gets used. A null in the middle is a different matter, and is
		// refused above.
		$this->assertSame( '/private/', $this->sanitise( "/private/\0" ) );
	}

	public function test_leading_rubbish_cannot_smuggle_another_host_through(): void {
		$this->assertSame( self::FALLBACK, $this->sanitise( "\0  https://evil.example/" ) );
	}

	public function test_the_host_comparison_ignores_case(): void {
		$this->assertSame( 'https://EXAMPLE.TEST/private/', $this->sanitise( 'https://EXAMPLE.TEST/private/' ) );
	}

	public function test_surrounding_whitespace_does_not_smuggle_anything_through(): void {
		$this->assertSame( self::FALLBACK, $this->sanitise( '  https://evil.example/  ' ) );
	}

	public function test_a_port_on_another_host_is_still_another_host(): void {
		$this->assertSame( self::FALLBACK, $this->sanitise( 'https://evil.example:443/' ) );
	}

	/**
	 * Sanitise against this test's site and fallback.
	 *
	 * @param string $candidate Where the request asked to go.
	 * @return string
	 */
	private function sanitise( string $candidate ): string {
		return SafeRedirect::sanitise( $candidate, self::FALLBACK, self::HOME );
	}
}
