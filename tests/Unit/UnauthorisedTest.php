<?php
/**
 * What a refusal looks like.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Unit;

use OxyArea\Content\Unauthorised;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OxyArea\Content\Unauthorised
 */
final class UnauthorisedTest extends TestCase {

	private const LOGIN_URL = 'https://example.test/sign-in/';

	public function test_a_signed_out_visitor_is_sent_to_sign_in(): void {
		$this->assertSame(
			Unauthorised::LOGIN,
			Unauthorised::decide( Unauthorised::LOGIN, false, self::LOGIN_URL )
		);
	}

	public function test_somebody_already_signed_in_is_not(): void {
		// They have an account; it is simply not the right one. Sending them to
		// sign in would bring them back here, be refused, and send them again.
		$this->assertSame(
			Unauthorised::MESSAGE,
			Unauthorised::decide( Unauthorised::LOGIN, true, self::LOGIN_URL )
		);
	}

	public function test_with_no_sign_in_page_configured_there_is_nowhere_to_send_them(): void {
		$this->assertSame(
			Unauthorised::MESSAGE,
			Unauthorised::decide( Unauthorised::LOGIN, false, '' )
		);
	}

	public function test_whitespace_is_not_a_sign_in_page(): void {
		$this->assertSame(
			Unauthorised::MESSAGE,
			Unauthorised::decide( Unauthorised::LOGIN, false, '   ' )
		);
	}

	/**
	 * @dataProvider unconditional
	 *
	 * @param string $behaviour The configured behaviour.
	 */
	public function test_the_other_three_do_not_depend_on_who_is_asking( string $behaviour ): void {
		$this->assertSame( $behaviour, Unauthorised::decide( $behaviour, false, self::LOGIN_URL ) );
		$this->assertSame( $behaviour, Unauthorised::decide( $behaviour, true, self::LOGIN_URL ) );
		$this->assertSame( $behaviour, Unauthorised::decide( $behaviour, false, '' ) );
	}

	/**
	 * The behaviours that are the same for everybody.
	 *
	 * @return array<string, array{string}>
	 */
	public static function unconditional(): array {
		return array(
			'a message' => array( Unauthorised::MESSAGE ),
			'a 403'     => array( Unauthorised::FORBIDDEN ),
			'a 404'     => array( Unauthorised::NOT_FOUND ),
		);
	}

	/**
	 * @dataProvider nonsense
	 *
	 * @param string $configured Something that is not a behaviour.
	 */
	public function test_a_value_nobody_recognises_becomes_the_quietest_answer( string $configured ): void {
		// Not the friendliest. A setting that has been corrupted, or written by a
		// version this code does not know, should not decide to start showing
		// pages.
		$this->assertSame(
			Unauthorised::NOT_FOUND,
			Unauthorised::decide( $configured, false, self::LOGIN_URL )
		);
	}

	/**
	 * Values that are not behaviours.
	 *
	 * @return array<string, array{string}>
	 */
	public static function nonsense(): array {
		return array(
			'empty'            => array( '' ),
			'a typo'           => array( 'mesage' ),
			'something later'  => array( 'redirect-to-shop' ),
			'a number'         => array( '200' ),
			'the word allow'   => array( 'allow' ),
		);
	}

	public function test_the_four_behaviours_are_the_ones_the_specification_names(): void {
		$this->assertSame( array( 'login', 'message', '403', '404' ), Unauthorised::all() );
	}
}
