<?php
/**
 * The widgets a dashboard can contain.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Dashboard;

use OxyArea\Infrastructure\Registrable;

/**
 * Collects the widgets add-ons offer, and puts them where a dashboard asks.
 *
 * `DashboardWidgetInterface` has existed since the dashboard sprint, documented
 * as the thing PRO would use — and nothing collected one, so the interface was
 * unusable from outside. This is the missing half.
 *
 * ## One shortcode rather than one per widget
 *
 * `[oxyarea_widget name="oxyarea_pro/documents"]`, not
 * `[oxyarea_pro_documents]`. A dashboard is written in the block editor by a
 * site owner, and one shortcode with a name is a thing they can be shown a list
 * of; a shortcode per widget is a list they have to be given and keep. It also
 * means an add-on registering a widget does not register anything global, so two
 * add-ons cannot collide over a shortcode name.
 *
 * ## Whose dashboard is being drawn
 *
 * Not always the person making the request. The preview screen renders somebody
 * else's, and a widget asked to draw itself for the wrong person is a widget
 * that shows one customer another's documents. So the renderer says who it is
 * drawing for, for the length of that render, and widgets are told rather than
 * left to guess.
 */
final class Widgets implements Registrable {

	/**
	 * The widgets on offer, by name.
	 *
	 * @var array<string, DashboardWidgetInterface>|null
	 */
	private ?array $widgets = null;

	/**
	 * Whose dashboard is being drawn, or 0 when nothing is.
	 *
	 * @var int
	 */
	private int $drawing_for = 0;

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'oxyarea_widget', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Every widget this site has.
	 *
	 * @return array<string, DashboardWidgetInterface>
	 */
	public function all(): array {
		if ( null !== $this->widgets ) {
			return $this->widgets;
		}

		/**
		 * The widgets a dashboard may contain.
		 *
		 * The free plugin contributes none: the plain parts of a dashboard are
		 * blocks, and a widget is for the things that have to know who is
		 * looking — personal documents, a company's folder, recent orders. Those
		 * belong to PRO.
		 *
		 * Return objects implementing DashboardWidgetInterface. Anything else is
		 * dropped rather than trusted.
		 *
		 * @since 0.1.0
		 *
		 * @param list<DashboardWidgetInterface> $widgets The widgets gathered so far.
		 */
		$offered = apply_filters( 'oxyarea_dashboard_widgets', array() );

		$this->widgets = array();

		foreach ( is_array( $offered ) ? $offered : array() as $widget ) {
			if ( ! $widget instanceof DashboardWidgetInterface ) {
				continue;
			}

			$name = trim( $widget->get_name() );

			// First one wins, as with conditions: last-one-wins would make the
			// answer depend on the order plugins happen to load.
			if ( '' !== $name && ! isset( $this->widgets[ $name ] ) ) {
				$this->widgets[ $name ] = $widget;
			}
		}

		return $this->widgets;
	}

	/**
	 * Say whose dashboard is being drawn, and for how long.
	 *
	 * @param int $user_id The person, or 0 to stop.
	 * @return void
	 */
	public function drawing_for( int $user_id ): void {
		$this->drawing_for = max( 0, $user_id );
	}

	/**
	 * Render the widget a shortcode names.
	 *
	 * An unknown name renders nothing at all, rather than an error: a dashboard
	 * whose add-on has been deactivated should be a dashboard missing a section,
	 * not a page with a red sentence on it that a customer has to read.
	 *
	 * @param mixed $attributes The shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $attributes ): string {
		$attributes = is_array( $attributes ) ? $attributes : array();

		$name = isset( $attributes['name'] ) ? trim( (string) $attributes['name'] ) : '';

		if ( '' === $name ) {
			return '';
		}

		$widgets = $this->all();

		if ( ! isset( $widgets[ $name ] ) ) {
			return '';
		}

		$user_id = $this->drawing_for > 0 ? $this->drawing_for : get_current_user_id();

		return $widgets[ $name ]->render(
			array_merge(
				$attributes,
				array( 'user_id' => $user_id )
			)
		);
	}

	/**
	 * Forget what was gathered.
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->widgets = null;
	}
}
