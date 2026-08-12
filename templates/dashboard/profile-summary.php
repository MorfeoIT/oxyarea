<?php
/**
 * A read-only summary of the account.
 *
 * Read-only on purpose: the editable version is the profile form, which asks for
 * the current password before it will change an email address. A summary that
 * quietly became a second way to change things would be a second place to get it
 * wrong.
 *
 * Override by copying to `oxyarea/dashboard/profile-summary.php` in a theme.
 *
 * @package OxyArea
 *
 * @var \WP_User    $user  Whose account it is.
 * @var list<string> $roles The roles they hold, already translated.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

?>
<dl class="oxyarea-profile-summary">
	<dt class="oxyarea-profile-summary__label"><?php esc_html_e( 'Name', 'oxyarea' ); ?></dt>
	<dd class="oxyarea-profile-summary__value"><?php echo esc_html( (string) $user->display_name ); ?></dd>

	<dt class="oxyarea-profile-summary__label"><?php esc_html_e( 'Email address', 'oxyarea' ); ?></dt>
	<dd class="oxyarea-profile-summary__value"><?php echo esc_html( (string) $user->user_email ); ?></dd>

	<?php if ( array() !== $roles ) : ?>
		<dt class="oxyarea-profile-summary__label"><?php esc_html_e( 'Account type', 'oxyarea' ); ?></dt>
		<dd class="oxyarea-profile-summary__value"><?php echo esc_html( implode( ', ', $roles ) ); ?></dd>
	<?php endif; ?>
</dl>
