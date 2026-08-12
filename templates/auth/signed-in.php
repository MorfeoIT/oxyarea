<?php
/**
 * What the sign-in form shows to somebody already signed in.
 *
 * Override by copying to `oxyarea/auth/signed-in.php` in a theme.
 *
 * @package OxyArea
 *
 * @var list<string> $errors     What went wrong last time, in plain text.
 * @var string       $notice     A message to show once, in plain text.
 * @var \WP_User     $user       Who is signed in.
 * @var string       $logout_url Where to go to sign out.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

?>
<div class="oxyarea-form oxyarea-form--signed-in">

	<?php require __DIR__ . '/partials/messages.php'; ?>

	<p class="oxyarea-signed-in__who">
		<?php
		printf(
			/* translators: %s: the person's display name. */
			esc_html__( 'You are signed in as %s.', 'oxyarea' ),
			esc_html( (string) $user->display_name )
		);
		?>
	</p>

	<p class="oxyarea-form__aside">
		<a href="<?php echo esc_url( $logout_url ); ?>"><?php esc_html_e( 'Sign out', 'oxyarea' ); ?></a>
	</p>
</div>
