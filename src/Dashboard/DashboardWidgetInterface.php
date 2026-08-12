<?php
/**
 * The contract a dashboard widget satisfies.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Dashboard;

/**
 * One block of a private dashboard.
 *
 * The free plugin registers the plain ones: a welcome line, the profile summary,
 * a list of links, the role's notices. PRO registers the ones that need to know
 * who is looking: personal documents, company documents, WooCommerce orders.
 *
 * A widget renders. It does not decide whether it should be rendered at all:
 * that answer comes from the access resolver before render() is reached, and a
 * widget that checks for itself has become a second authorisation system.
 */
interface DashboardWidgetInterface {

	/**
	 * The widget's identifier, unique across the site.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * The rendered HTML.
	 *
	 * Everything returned is echoed as-is, so escaping happens here and is not
	 * somebody else's problem.
	 *
	 * @param array<string, mixed> $context Render context, including the user the dashboard is being built for.
	 * @return string
	 */
	public function render( array $context ): string;
}
