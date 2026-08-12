<?php
/**
 * Where the dashboards are.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Dashboard;

/**
 * Lists the dashboards a site has defined.
 *
 * An interface so the resolver can be tested against a list in memory, which is
 * what makes "which dashboard does this person get" answerable without a
 * database.
 */
interface DashboardRepositoryInterface {

	/**
	 * Every published dashboard, oldest first.
	 *
	 * @return list<Dashboard>
	 */
	public function all(): array;
}
