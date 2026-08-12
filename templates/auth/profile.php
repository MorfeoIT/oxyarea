<?php
/**
 * Your own details.
 *
 * Override by copying to `oxyarea/auth/profile.php` in a theme.
 *
 * @package OxyArea
 *
 * @var string       $action        The form's action value.
 * @var string       $nonce_action  The nonce action.
 * @var string       $field         The name of the hidden action field.
 * @var list<string> $errors        What went wrong last time, in plain text.
 * @var string       $notice        A message to show once, in plain text.
 * @var \WP_User     $user          Whose details these are.
 * @var bool         $show_password Whether to offer the password fields.
 * @var string       $label         The submit button's text.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

?>
<div class="oxyarea-form oxyarea-form--profile">

	<?php require __DIR__ . '/partials/messages.php'; ?>

	<form class="oxyarea-form__form" method="post">
		<?php wp_nonce_field( $nonce_action ); ?>
		<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $action ); ?>" />

		<p class="oxyarea-field">
			<label class="oxyarea-field__label" for="oxyarea-first-name"><?php esc_html_e( 'First name', 'oxyarea' ); ?></label>
			<input class="oxyarea-field__input" type="text" name="first_name" id="oxyarea-first-name" autocomplete="given-name" value="<?php echo esc_attr( (string) $user->first_name ); ?>" />
		</p>

		<p class="oxyarea-field">
			<label class="oxyarea-field__label" for="oxyarea-last-name"><?php esc_html_e( 'Last name', 'oxyarea' ); ?></label>
			<input class="oxyarea-field__input" type="text" name="last_name" id="oxyarea-last-name" autocomplete="family-name" value="<?php echo esc_attr( (string) $user->last_name ); ?>" />
		</p>

		<p class="oxyarea-field">
			<label class="oxyarea-field__label" for="oxyarea-display-name"><?php esc_html_e( 'Shown as', 'oxyarea' ); ?></label>
			<input class="oxyarea-field__input" type="text" name="display_name" id="oxyarea-display-name" value="<?php echo esc_attr( (string) $user->display_name ); ?>" />
		</p>

		<p class="oxyarea-field">
			<label class="oxyarea-field__label" for="oxyarea-email"><?php esc_html_e( 'Email address', 'oxyarea' ); ?></label>
			<input class="oxyarea-field__input" type="email" name="user_email" id="oxyarea-email" autocomplete="email" value="<?php echo esc_attr( (string) $user->user_email ); ?>" required />
		</p>

		<?php if ( $show_password ) : ?>
			<fieldset class="oxyarea-fieldset">
				<legend class="oxyarea-fieldset__legend"><?php esc_html_e( 'Change your password', 'oxyarea' ); ?></legend>
				<p class="oxyarea-field__help" id="oxyarea-password-help">
					<?php esc_html_e( 'Leave these empty to keep the password you have.', 'oxyarea' ); ?>
				</p>

				<p class="oxyarea-field">
					<label class="oxyarea-field__label" for="oxyarea-new-pass1"><?php esc_html_e( 'New password', 'oxyarea' ); ?></label>
					<input class="oxyarea-field__input" type="password" name="pass1" id="oxyarea-new-pass1" autocomplete="new-password" aria-describedby="oxyarea-password-help" />
				</p>

				<p class="oxyarea-field">
					<label class="oxyarea-field__label" for="oxyarea-new-pass2"><?php esc_html_e( 'New password again', 'oxyarea' ); ?></label>
					<input class="oxyarea-field__input" type="password" name="pass2" id="oxyarea-new-pass2" autocomplete="new-password" />
				</p>
			</fieldset>
		<?php endif; ?>

		<p class="oxyarea-field">
			<label class="oxyarea-field__label" for="oxyarea-current-password"><?php esc_html_e( 'Your current password', 'oxyarea' ); ?></label>
			<input class="oxyarea-field__input" type="password" name="current_password" id="oxyarea-current-password" autocomplete="current-password" aria-describedby="oxyarea-current-help" />
			<span class="oxyarea-field__help" id="oxyarea-current-help">
				<?php esc_html_e( 'Only needed if you are changing your email address or your password.', 'oxyarea' ); ?>
			</span>
		</p>

		<p class="oxyarea-form__actions">
			<button type="submit" class="oxyarea-button"><?php echo esc_html( $label ); ?></button>
		</p>
	</form>
</div>
