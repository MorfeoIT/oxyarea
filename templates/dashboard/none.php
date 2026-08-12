<?php
/**
 * What appears where a dashboard would be, when there is not one.
 *
 * Two different sentences on purpose. An administrator is told what to do about
 * it; a customer is told nothing at all, because "no dashboard has been built
 * for the Customer role yet" describes the inside of somebody's admin to a
 * person who cannot act on it.
 *
 * Override by copying to `oxyarea/dashboard/none.php` in a theme.
 *
 * @package OxyArea
 *
 * @var bool $can_manage Whether the reader can build one.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

?>
<?php if ( $can_manage ) : ?>
	<div class="oxyarea-notice" role="status">
		<p><?php esc_html_e( 'There is no dashboard for this role yet, and only people who can manage OxyArea are seeing this message.', 'oxyarea' ); ?></p>
		<p>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=oxyarea_dashboard' ) ); ?>">
				<?php esc_html_e( 'Build one', 'oxyarea' ); ?>
			</a>
		</p>
	</div>
<?php endif; ?>
