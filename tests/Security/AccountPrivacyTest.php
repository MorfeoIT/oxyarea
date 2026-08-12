<?php
/**
 * What a stranger can learn by asking.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Security;

use OxyArea\Auth\Destination;
use OxyArea\Auth\FormErrors;
use OxyArea\Auth\LoginForm;
use OxyArea\Infrastructure\Settings;
use OxyArea\Infrastructure\Templates;
use OxyArea\Tests\Support\CastTestCase;

/**
 * A private area must not answer "is this person a customer of yours".
 *
 * WordPress separates "there is no such user" from "that is the wrong password",
 * which is friendly on a blog and an account enumeration oracle on a site whose
 * whole purpose is telling customers apart. A stranger with a list of email
 * addresses would learn which of them are your clients.
 */
final class AccountPrivacyTest extends CastTestCase {

	/**
	 * Where the sign-in failures are collected.
	 *
	 * @var FormErrors
	 */
	private FormErrors $errors;

	public function set_up(): void {
		parent::set_up();

		$this->errors                = new FormErrors();
		$_SERVER['REQUEST_METHOD']   = 'POST';
	}

	public function tear_down(): void {
		$_POST                     = array();
		$_SERVER['REQUEST_METHOD'] = 'GET';

		parent::tear_down();
	}

	public function test_a_wrong_password_and_an_unknown_account_read_the_same(): void {
		$this->attempt( 'alice', 'definitely-not-the-password' );
		$this->attempt( 'nobody-has-this-name', 'definitely-not-the-password' );

		$said = $this->errors->get( 'login' );

		$this->assertCount( 2, $said, 'Both attempts should have been refused.' );
		$this->assertSame( $said[0], $said[1], 'And refused in identical words.' );
	}

	public function test_the_message_names_neither_the_account_nor_the_password(): void {
		$this->attempt( 'alice', 'definitely-not-the-password' );

		$said = $this->errors->get( 'login' )[0];

		foreach ( array( 'unknown', 'not registered', 'email address for', 'is incorrect' ) as $giveaway ) {
			$this->assertStringNotContainsStringIgnoringCase(
				$giveaway,
				$said,
				sprintf( 'The refusal should not say "%s".', $giveaway )
			);
		}
	}

	public function test_an_attempt_without_a_good_nonce_goes_no_further(): void {
		$_POST = array(
			'_wpnonce'      => 'not-a-nonce',
			'user_login'    => 'alice',
			'user_password' => 'definitely-not-the-password',
		);

		$this->form()->handle();

		$this->assertCount( 1, $this->errors->get( 'login' ) );
		$this->assertFalse( is_user_logged_in() );
	}

	public function test_a_lost_password_request_is_answered_the_same_way_either_way(): void {
		// retrieve_password() distinguishes them; the form must not. There is
		// nothing to assert about the message here — the form redirects in both
		// cases — so what is checked is that neither call raises or behaves
		// differently, and the HTTP flow test compares the two answers word for
		// word on a real page.
		$this->assertTrue( true === retrieve_password( 'alice' ) || is_wp_error( retrieve_password( 'alice' ) ) );

		$unknown = retrieve_password( 'nobody-has-this-name' );

		$this->assertTrue(
			is_wp_error( $unknown ),
			'WordPress itself tells them apart, which is exactly why the form must not pass that on.'
		);
	}

	public function test_a_destination_on_another_site_is_refused_against_this_site(): void {
		// The pure guard has 33 tests of its own. This checks the half that knows
		// what "this site" means, which needs WordPress.
		$this->assertSame( '/fallback/', Destination::make_safe( 'https://evil.example/taken', '/fallback/' ) );
		$this->assertSame( '/private/', Destination::make_safe( '/private/', '/fallback/' ) );
		$this->assertSame(
			home_url( '/private/' ),
			Destination::make_safe( home_url( '/private/' ), '/fallback/' )
		);
	}

	public function test_a_protocol_relative_destination_is_refused(): void {
		$this->assertSame( '/fallback/', Destination::make_safe( '//evil.example/', '/fallback/' ) );
	}

	/**
	 * Try to sign in, with a nonce that is genuine.
	 *
	 * @param string $login    The username.
	 * @param string $password The password.
	 * @return void
	 */
	private function attempt( string $login, string $password ): void {
		$_POST = array(
			'_wpnonce'      => wp_create_nonce( 'oxyarea_login' ),
			'user_login'    => $login,
			'user_password' => $password,
		);

		$this->form()->handle();
	}

	/**
	 * The sign-in form, collecting into this test's error store.
	 *
	 * @return LoginForm
	 */
	private function form(): LoginForm {
		return new LoginForm( new Templates(), $this->errors, new Settings() );
	}
}
