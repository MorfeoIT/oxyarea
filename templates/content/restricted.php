<?php
/**
 * What stands in for a page somebody may not read.
 *
 * Says that it is private and nothing about what is in it. "Sign in to see your
 * invoices" tells a stranger there are invoices, which is the shape of leak this
 * whole sprint exists to close.
 *
 * Override by copying to `oxyarea/content/restricted.php` in a theme.
 *
 * @package OxyArea
 *
 * @var bool   $signed_in Whether the reader is signed in.
 * @var string $login_url Where the sign-in form is.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

?>
<div class="oxyarea-restricted oxyarea-notice" role="status">
	<?php if ( $signed_in ) : ?>
		<p><?php esc_html_e( 'This page is private, and it is not one your account has access to.', 'oxyarea' ); ?></p>
	<?php else : ?>
		<p><?php esc_html_e( 'This page is private.', 'oxyarea' ); ?></p>

		<?php if ( '' !== $login_url ) : ?>
			<p>
				<a class="oxyarea-button" href="<?php echo esc_url( $login_url ); ?>">
					<?php esc_html_e( 'Sign in', 'oxyarea' ); ?>
				</a>
			</p>
		<?php endif; ?>
	<?php endif; ?>
</div>
