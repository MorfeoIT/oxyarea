<?php
/**
 * Setting a new password, from the link in the email.
 *
 * Override by copying to `oxyarea/auth/reset-password.php` in a theme.
 *
 * @package OxyArea
 *
 * @var string       $action       The form's action value.
 * @var string       $nonce_action The nonce action.
 * @var string       $field        The name of the hidden action field.
 * @var list<string> $errors       What went wrong last time, in plain text.
 * @var string       $notice       A message to show once, in plain text.
 * @var bool         $usable       Whether the link is still good.
 * @var string       $key          The key from the email.
 * @var string       $login        The account the key belongs to.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

?>
<div class="oxyarea-form oxyarea-form--reset-password">

	<?php require __DIR__ . '/partials/messages.php'; ?>

	<?php if ( ! $usable ) : ?>
		<p class="oxyarea-form__aside">
			<?php esc_html_e( 'This link cannot be used any more. Ask for a new one and use the most recent email.', 'oxyarea' ); ?>
		</p>
	<?php else : ?>
		<form class="oxyarea-form__form" method="post" autocomplete="off">
			<?php wp_nonce_field( $nonce_action ); ?>
			<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $action ); ?>" />
			<input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>" />
			<input type="hidden" name="login" value="<?php echo esc_attr( $login ); ?>" />

			<p class="oxyarea-field">
				<label class="oxyarea-field__label" for="oxyarea-pass1">
					<?php esc_html_e( 'New password', 'oxyarea' ); ?>
				</label>
				<input
					class="oxyarea-field__input"
					type="password"
					name="pass1"
					id="oxyarea-pass1"
					autocomplete="new-password"
					required
				/>
			</p>

			<p class="oxyarea-field">
				<label class="oxyarea-field__label" for="oxyarea-pass2">
					<?php esc_html_e( 'New password again', 'oxyarea' ); ?>
				</label>
				<input
					class="oxyarea-field__input"
					type="password"
					name="pass2"
					id="oxyarea-pass2"
					autocomplete="new-password"
					required
				/>
			</p>

			<p class="oxyarea-form__actions">
				<button type="submit" class="oxyarea-button"><?php esc_html_e( 'Set my password', 'oxyarea' ); ?></button>
			</p>
		</form>
	<?php endif; ?>
</div>
