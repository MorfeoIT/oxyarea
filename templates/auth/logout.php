<?php
/**
 * The sign-out button.
 *
 * A button in a form, not a link: a link is something a prefetcher, a scanner or
 * another site's image tag can follow without anybody deciding to.
 *
 * Override by copying to `oxyarea/auth/logout.php` in a theme.
 *
 * @package OxyArea
 *
 * @var string       $action       The form's action value.
 * @var string       $nonce_action The nonce action.
 * @var string       $field        The name of the hidden action field.
 * @var list<string> $errors       What went wrong last time, in plain text.
 * @var string       $notice       A message to show once, in plain text.
 * @var string       $label        The button's text.
 * @var string       $redirect_to  Where to land afterwards.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

?>
<div class="oxyarea-form oxyarea-form--logout">

	<?php require __DIR__ . '/partials/messages.php'; ?>

	<form class="oxyarea-form__form" method="post">
		<?php wp_nonce_field( $nonce_action ); ?>
		<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $action ); ?>" />
		<?php if ( '' !== $redirect_to ) : ?>
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
		<?php endif; ?>

		<button type="submit" class="oxyarea-button oxyarea-button--quiet"><?php echo esc_html( $label ); ?></button>
	</form>
</div>
