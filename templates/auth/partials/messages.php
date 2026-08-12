<?php
/**
 * The notice and the errors, above every form.
 *
 * Errors are announced: `role="alert"` makes a screen reader interrupt and read
 * them, which is what somebody who has just submitted a form and had nothing
 * visible happen actually needs. The success notice is `role="status"`, which
 * waits for a pause, because it is not urgent.
 *
 * Neither relies on colour alone. The words say what happened.
 *
 * @package OxyArea
 *
 * @var list<string> $errors What went wrong, in plain text.
 * @var string       $notice A message to show once, in plain text.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

?>
<?php if ( '' !== $notice ) : ?>
	<p class="oxyarea-notice" role="status"><?php echo esc_html( $notice ); ?></p>
<?php endif; ?>

<?php if ( array() !== $errors ) : ?>
	<div class="oxyarea-errors" role="alert">
		<?php if ( 1 === count( $errors ) ) : ?>
			<p class="oxyarea-errors__item"><?php echo esc_html( (string) reset( $errors ) ); ?></p>
		<?php else : ?>
			<ul class="oxyarea-errors__list">
				<?php foreach ( $errors as $oxyarea_error ) : ?>
					<li class="oxyarea-errors__item"><?php echo esc_html( (string) $oxyarea_error ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
<?php endif; ?>
