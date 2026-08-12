<?php
/**
 * What a signed-in-only block shows to somebody who is not.
 *
 * Deliberately says nothing about what is behind it. "Sign in to see your
 * invoices" tells a stranger there are invoices.
 *
 * Override by copying to `oxyarea/auth/signed-out.php` in a theme.
 *
 * @package OxyArea
 *
 * @var list<string> $errors What went wrong last time, in plain text.
 * @var string       $notice A message to show once, in plain text.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

?>
<div class="oxyarea-form oxyarea-form--signed-out">

	<?php require __DIR__ . '/partials/messages.php'; ?>

	<p class="oxyarea-signed-out__prompt">
		<?php esc_html_e( 'You need to be signed in to see this.', 'oxyarea' ); ?>
	</p>
</div>
