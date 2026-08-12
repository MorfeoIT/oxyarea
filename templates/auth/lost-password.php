<?php
/**
 * Asking for a link to set a new password.
 *
 * Override by copying to `oxyarea/auth/lost-password.php` in a theme.
 *
 * @package OxyArea
 *
 * @var string       $action       The form's action value.
 * @var string       $nonce_action The nonce action.
 * @var string       $field        The name of the hidden action field.
 * @var list<string> $errors       What went wrong last time, in plain text.
 * @var string       $notice       A message to show once, in plain text.
 * @var string       $label        The submit button's text.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

?>
<div class="oxyarea-form oxyarea-form--lost-password">

	<?php require __DIR__ . '/partials/messages.php'; ?>

	<form class="oxyarea-form__form" method="post">
		<?php wp_nonce_field( $nonce_action ); ?>
		<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $action ); ?>" />

		<p class="oxyarea-field">
			<label class="oxyarea-field__label" for="oxyarea-lost-login">
				<?php esc_html_e( 'Username or email', 'oxyarea' ); ?>
			</label>
			<input
				class="oxyarea-field__input"
				type="text"
				name="user_login"
				id="oxyarea-lost-login"
				autocomplete="username"
				autocapitalize="none"
				spellcheck="false"
				aria-describedby="oxyarea-lost-help"
				required
			/>
			<span class="oxyarea-field__help" id="oxyarea-lost-help">
				<?php esc_html_e( 'We will send a link to the address on the account.', 'oxyarea' ); ?>
			</span>
		</p>

		<p class="oxyarea-form__actions">
			<button type="submit" class="oxyarea-button"><?php echo esc_html( $label ); ?></button>
		</p>
	</form>
</div>
