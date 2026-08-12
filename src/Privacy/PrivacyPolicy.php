<?php
/**
 * What the site has to tell its users.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Privacy;

use OxyArea\Infrastructure\Registrable;

/**
 * Contributes suggested text to the site's privacy policy.
 *
 * WordPress collects these suggestions from plugins and offers them to whoever
 * writes the policy. It is a draft for a human, not a policy, and it says so.
 *
 * There is no personal data exporter or eraser registered yet, and that is not
 * an omission: the free plugin stores no personal data. Assignments name roles,
 * not people. Exporters arrive in the sprint that introduces something to
 * export, and registering empty ones now would tell an administrator their
 * export is complete when it has not been written.
 */
final class PrivacyPolicy implements Registrable {

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'add_suggested_content' ) );
	}

	/**
	 * Offer the site owner some wording.
	 *
	 * @return void
	 */
	public function add_suggested_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p class="privacy-policy-tutorial">'
			. esc_html__( 'Suggested text for sites running a private client area. Edit it to describe what your own private area actually holds.', 'oxyarea' )
			. '</p><p>'
			. esc_html__( 'This site provides a private area that is only available to signed-in users. To decide what each user may see, the site records which roles a page, dashboard or document is available to, and reads the account you are signed in with. This information stays on this site and is not sent anywhere else.', 'oxyarea' )
			. '</p><p>'
			. esc_html__( 'Signing in, resetting a password and staying signed in use the standard WordPress account and cookie mechanisms, which are described elsewhere in this policy.', 'oxyarea' )
			. '</p>';

		wp_add_privacy_policy_content(
			__( 'OxyArea', 'oxyarea' ),
			wp_kses_post( wpautop( $content, false ) )
		);
	}
}
