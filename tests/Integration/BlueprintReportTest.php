<?php
/**
 * The account an import gives of itself, and the seam add-ons speak through.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Integration;

use OxyArea\Infrastructure\Settings;
use OxyArea\Persistence\DashboardRepository;
use OxyArea\Persistence\RedirectRuleRepository;
use OxyArea\Tools\Blueprint;
use OxyArea\Tools\Porter;
use WP_UnitTestCase;

/**
 * What the site owner is told after pressing Import.
 *
 * The failure this guards against is the quiet one. An import that half worked
 * and said so is a support ticket; an import that half worked and said
 * "Imported: 3 settings" is a client area with a company missing from it, found
 * three weeks later by somebody who could not get to their contract.
 */
final class BlueprintReportTest extends WP_UnitTestCase {

	/**
	 * The porter under test.
	 *
	 * @var Porter
	 */
	private Porter $porter;

	/**
	 * Build one on the real repositories.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->porter = new Porter( new Settings(), new RedirectRuleRepository(), new DashboardRepository() );
	}

	public function test_the_report_says_what_arrived(): void {
		$report = $this->porter->import( ( new Blueprint( array( 'restricted_behaviour' => '404' ) ) )->to_json() );

		$this->assertSame( 1, $report['settings'] );
		$this->assertSame( array(), $report['skipped'] );
		$this->assertSame( array(), $report['notes'] );
	}

	public function test_an_addon_can_add_a_line_to_it(): void {
		add_filter(
			'oxyarea_blueprint_import_report',
			static function ( array $report ): array {
				$report['notes'][] = '2 companies.';

				return $report;
			}
		);

		$report = $this->porter->import( ( new Blueprint() )->to_json() );

		$this->assertSame( array( '2 companies.' ), $report['notes'] );
	}

	public function test_what_an_addon_could_not_apply_is_kept_apart_from_what_it_did(): void {
		// Two lists and not one. Sharing them would make an import that brought
		// in two companies look like an import that failed, and the screen
		// colours the notice from `skipped`.
		add_filter(
			'oxyarea_blueprint_import_report',
			static function ( array $report ): array {
				$report['notes'][]   = '2 companies.';
				$report['skipped'][] = 'There is nobody called dave on this site.';

				return $report;
			}
		);

		$report = $this->porter->import( ( new Blueprint() )->to_json() );

		$this->assertSame( array( '2 companies.' ), $report['notes'] );
		$this->assertSame( array( 'There is nobody called dave on this site.' ), $report['skipped'] );
	}

	public function test_a_filter_that_returns_rubbish_leaves_the_report_as_it_was(): void {
		add_filter( 'oxyarea_blueprint_import_report', static fn () => 'nonsense' );

		$report = $this->porter->import( ( new Blueprint( array( 'restricted_behaviour' => '404' ) ) )->to_json() );

		$this->assertSame( 1, $report['settings'] );
		$this->assertSame( array(), $report['skipped'] );
	}

	public function test_a_filter_cannot_put_something_unprintable_on_the_screen(): void {
		add_filter(
			'oxyarea_blueprint_import_report',
			static function ( array $report ): array {
				$report['skipped']  = array( array( 'an array' ), 5, '', '   ', 'a real reason' );
				$report['notes']    = array( null, 'a real note' );
				$report['settings'] = 'not a number';

				return $report;
			}
		);

		$report = $this->porter->import( ( new Blueprint( array( 'restricted_behaviour' => '404' ) ) )->to_json() );

		$this->assertSame( array( 'a real reason' ), $report['skipped'] );
		$this->assertSame( array( 'a real note' ), $report['notes'] );
		$this->assertSame( 1, $report['settings'] );
	}

	public function test_the_extras_are_applied_before_the_rules_that_name_them(): void {
		// A rule can refer to a company an add-on has just created, so the order
		// is part of the contract rather than an accident of where the line sits.
		$order = array();

		add_action(
			'oxyarea_blueprint_import_extras',
			static function () use ( &$order ): void {
				$order[] = 'extras';
			}
		);

		add_filter(
			'oxyarea_blueprint_import_report',
			static function ( array $report ) use ( &$order ): array {
				$order[] = 'report';

				return $report;
			}
		);

		$this->porter->import( ( new Blueprint() )->to_json() );

		$this->assertSame( array( 'extras', 'report' ), $order );
	}
}
