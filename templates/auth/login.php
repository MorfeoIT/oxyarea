<?php
/**
 * The sign-in form.
 *
 * Override by copying to `oxyarea/auth/login.php` in a theme.
 *
 * @package OxyArea
 *
 * @var string       $action            The form's action value.
 * @var string       $nonce_action      The nonce action.
 * @var string       $field             The name of the hidden action field.
 * @var list<string> $errors            What went wrong last time, in plain text.
 * @var string       $notice            A message to show once, in plain text.
 * @var string       $redirect_to       Where to go afterwards, already made safe.
 * @var string       $lost_password_url Where to ask for a new password.
 * @var bool         $show_remember     Whether to offer "stay signed in".
 * @var bool         $show_lost         Whether to offer the lost-password link.
 * @var string       $label             The submit button's text.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

?>
<div class="oxyarea-form oxyarea-form--login">

	<?php require __DIR__ . '/partials/messages.php'; ?>

	<form class="oxyarea-form__form" method="post">
		<?php wp_nonce_field( $nonce_action ); ?>
		<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $action ); ?>" />
		<?php if ( '' !== $redirect_to ) : ?>
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
		<?php endif; ?>

		<p class="oxyarea-field">
			<label class="oxyarea-field__label" for="oxyarea-user-login">
				<?php esc_html_e( 'Username or email', 'oxyarea' ); ?>
			</label>
			<input
				class="oxyarea-field__input"
				type="text"
				name="user_login"
				id="oxyarea-user-login"
				autocomplete="username"
				autocapitalize="none"
				spellcheck="false"
				required
			/>
		</p>

		<p class="oxyarea-field">
			<label class="oxyarea-field__label" for="oxyarea-user-password">
				<?php esc_html_e( 'Password', 'oxyarea' ); ?>
			</label>
			<input
				class="oxyarea-field__input"
				type="password"
				name="user_password"
				id="oxyarea-user-password"
				autocomplete="current-password"
				required
			/>
		</p>

		<?php if ( $show_remember ) : ?>
			<p class="oxyarea-field oxyarea-field--check">
				<input type="checkbox" name="remember" id="oxyarea-remember" value="1" />
				<label for="oxyarea-remember"><?php esc_html_e( 'Keep me signed in on this device', 'oxyarea' ); ?></label>
			</p>
		<?php endif; ?>

		<p class="oxyarea-form__actions">
			<button type="submit" class="oxyarea-button"><?php echo esc_html( $label ); ?></button>
		</p>

		<?php if ( $show_lost && '' !== $lost_password_url ) : ?>
			<p class="oxyarea-form__aside">
				<a href="<?php echo esc_url( $lost_password_url ); ?>">
					<?php esc_html_e( 'Forgotten your password?', 'oxyarea' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</form>
</div>
