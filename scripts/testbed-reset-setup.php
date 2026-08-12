<?php
/**
 * Prepares the password reset flow test.
 *
 * Makes the sign-in page if it is not there, points the login_page setting at
 * it, and prints its identifier. With OXYAREA_RESTORE set it puts Alice's
 * password back instead, so the other flow scripts keep working.
 *
 * No declare(strict_types=1): wp eval-file evaluates the file with a leading
 * `?>`, so nothing here is ever the first statement of a script.
 *
 * @package OxyArea
 */

use OxyArea\Infrastructure\Settings;

$oxyarea_settings = new Settings();
$oxyarea_restore  = (string) getenv( 'OXYAREA_RESTORE' );

if ( '' !== $oxyarea_restore ) {
	$oxyarea_alice = get_user_by( 'login', 'alice' );

	wp_set_password( $oxyarea_restore, (int) $oxyarea_alice->ID );

	echo "restored\n";

	return;
}

$oxyarea_slug = 'oxyarea-reset-page';
$oxyarea_page = get_page_by_path( $oxyarea_slug );

if ( null === $oxyarea_page ) {
	$oxyarea_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Sign in',
			'post_name'    => $oxyarea_slug,
			'post_content' => "<!-- wp:oxyarea/login /-->\n<!-- wp:oxyarea/lost-password /-->\n<!-- wp:oxyarea/reset-password /-->",
		)
	);
} else {
	$oxyarea_id = (int) $oxyarea_page->ID;
}

// The setting is what makes PasswordResetLinks rewrite the email so the link
// comes back to this page rather than to wp-login.php. Without it the flow
// works and then abandons the person on the screen the plugin exists to keep
// them away from.
$oxyarea_settings->update( array( 'login_page' => (int) $oxyarea_id ) );

echo 'page=' . (int) $oxyarea_id . "\n";
