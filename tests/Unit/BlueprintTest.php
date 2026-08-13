<?php
/**
 * What a site will accept from an import file.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Unit;

use InvalidArgumentException;
use OxyArea\Tools\Blueprint;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OxyArea\Tools\Blueprint
 */
final class BlueprintTest extends TestCase {

	public function test_what_goes_out_comes_back(): void {
		$blueprint = new Blueprint(
			array( 'login_page' => 12 ),
			array(
				array(
					'event'        => 'login',
					'subject_type' => 'role',
					'subject_id'   => 'customer',
					'destination'  => '/customers/',
					'priority'     => 5,
					'enabled'      => true,
				),
			),
			array(
				array(
					'title'    => 'Customers',
					'audience' => 'role:customer',
					'content'  => '<p>Hello.</p>',
				),
			)
		);

		$read = Blueprint::from_json( $blueprint->to_json( '0.1.0', '2026-08-12' ) );

		$this->assertSame( array( 'login_page' => 12 ), $read->settings() );
		$this->assertSame( '/customers/', $read->redirects()[0]['destination'] );
		$this->assertSame( 'Customers', $read->dashboards()[0]['title'] );
	}

	/**
	 * @dataProvider not_a_document
	 *
	 * @param string $json Something that is not an export.
	 */
	public function test_a_file_that_is_not_an_export_is_refused( string $json ): void {
		$this->expectException( InvalidArgumentException::class );

		Blueprint::from_json( $json );
	}

	/**
	 * Files that are not exports.
	 *
	 * @return array<string, array{string}>
	 */
	public static function not_a_document(): array {
		return array(
			'empty'                => array( '' ),
			'not json'             => array( 'this is not json' ),
			'a bare string'        => array( '"hello"' ),
			'a bare number'        => array( '42' ),
			'null'                 => array( 'null' ),
			'an object with no format' => array( '{"settings":{}}' ),
			'a format from later'  => array( '{"format":99,"settings":{}}' ),
			'a format of zero'     => array( '{"format":0}' ),
		);
	}

	public function test_a_document_with_nothing_in_it_is_accepted(): void {
		$blueprint = Blueprint::from_json( '{"format":1}' );

		$this->assertSame( array(), $blueprint->settings() );
		$this->assertSame( array(), $blueprint->redirects() );
		$this->assertSame( array(), $blueprint->dashboards() );
	}

	public function test_a_setting_that_is_not_a_scalar_is_dropped(): void {
		// A nested structure where a setting belongs is either corruption or
		// somebody testing what happens. Neither should reach the option.
		$blueprint = Blueprint::from_json(
			'{"format":1,"settings":{"good":"yes","bad":{"nested":true},"worse":[1,2]}}'
		);

		$this->assertSame( array( 'good' => 'yes' ), $blueprint->settings() );
	}

	public function test_a_redirect_with_no_destination_is_not_a_redirect(): void {
		$blueprint = Blueprint::from_json(
			'{"format":1,"redirects":[{"event":"login"},{"event":"login","destination":"   "},{"event":"login","destination":"/ok/"}]}'
		);

		$this->assertCount( 1, $blueprint->redirects() );
		$this->assertSame( '/ok/', $blueprint->redirects()[0]['destination'] );
	}

	public function test_a_redirect_with_no_event_is_not_a_redirect_either(): void {
		$blueprint = Blueprint::from_json( '{"format":1,"redirects":[{"destination":"/ok/"}]}' );

		$this->assertSame( array(), $blueprint->redirects() );
	}

	public function test_a_redirect_gets_the_defaults_it_did_not_state(): void {
		$blueprint = Blueprint::from_json( '{"format":1,"redirects":[{"event":"login","destination":"/ok/"}]}' );
		$rule      = $blueprint->redirects()[0];

		$this->assertSame( '', $rule['subject_type'] );
		$this->assertSame( 10, $rule['priority'] );
		$this->assertTrue( $rule['enabled'] );
	}

	public function test_a_priority_that_is_not_a_number_falls_back(): void {
		$blueprint = Blueprint::from_json(
			'{"format":1,"redirects":[{"event":"login","destination":"/ok/","priority":"soon"}]}'
		);

		$this->assertSame( 10, $blueprint->redirects()[0]['priority'] );
	}

	public function test_a_dashboard_without_a_title_is_dropped(): void {
		$blueprint = Blueprint::from_json(
			'{"format":1,"dashboards":[{"content":"orphan"},{"title":"  ","content":"also orphan"},{"title":"Real"}]}'
		);

		$this->assertCount( 1, $blueprint->dashboards() );
		$this->assertSame( 'Real', $blueprint->dashboards()[0]['title'] );
	}

	public function test_entries_that_are_not_arrays_are_stepped_over(): void {
		$blueprint = Blueprint::from_json(
			'{"format":1,"redirects":["nonsense",null,7],"dashboards":["nonsense",null]}'
		);

		$this->assertSame( array(), $blueprint->redirects() );
		$this->assertSame( array(), $blueprint->dashboards() );
	}

	public function test_sections_that_are_not_lists_are_ignored(): void {
		$blueprint = Blueprint::from_json( '{"format":1,"settings":"nope","redirects":"nope","dashboards":7}' );

		$this->assertSame( array(), $blueprint->settings() );
		$this->assertSame( array(), $blueprint->redirects() );
		$this->assertSame( array(), $blueprint->dashboards() );
	}

	public function test_markup_in_a_dashboard_is_carried_but_not_interpreted(): void {
		// Deciding what markup a dashboard may contain is a question about a
		// particular site, and is asked on the way in by the code that knows the
		// answer. This class carries the string.
		$blueprint = Blueprint::from_json(
			'{"format":1,"dashboards":[{"title":"T","content":"<script>alert(1)</script>"}]}'
		);

		$this->assertSame( '<script>alert(1)</script>', $blueprint->dashboards()[0]['content'] );
	}

	public function test_the_summary_counts_what_is_there(): void {
		$blueprint = Blueprint::from_json(
			'{"format":1,"settings":{"a":1,"b":2},"redirects":[{"event":"login","destination":"/x/"}],"dashboards":[{"title":"T"}]}'
		);

		$this->assertSame(
			array(
				'settings'   => 2,
				'redirects'  => 1,
				'dashboards' => 1,
				'extras'     => 0,
			),
			$blueprint->summary()
		);
	}

	public function test_the_document_says_what_wrote_it(): void {
		$json = ( new Blueprint() )->to_json( '0.1.0', '2026-08-12T10:00:00Z' );

		$this->assertStringContainsString( '"plugin": "oxyarea"', $json );
		$this->assertStringContainsString( '"version": "0.1.0"', $json );
		$this->assertStringContainsString( '"format": 1', $json );
	}

	public function test_an_add_on_section_is_carried_through_untouched(): void {
		// The free plugin validates that this is a map of arrays and nothing
		// more. What is inside belongs to whoever wrote it, and a plugin that
		// tried to understand another's data would be the wrong place for that
		// knowledge to live.
		$blueprint = Blueprint::from_json(
			'{"format":1,"extras":{"oxyarea-pro":{"companies":[{"name":"ACME"}],"anything":{"nested":true}}}}'
		);

		$this->assertSame(
			array(
				'companies' => array( array( 'name' => 'ACME' ) ),
				'anything'  => array( 'nested' => true ),
			),
			$blueprint->extra( 'oxyarea-pro' )
		);
	}

	public function test_a_section_from_an_add_on_that_is_not_installed_survives_the_round_trip(): void {
		// Dropping it would make an export quietly lossy: a site exporting with
		// PRO and importing without it would lose its companies for good, and
		// nobody would notice until they needed them.
		$original = '{"format":1,"extras":{"somebody-else":{"a":1}}}';

		$again = Blueprint::from_json( Blueprint::from_json( $original )->to_json() );

		$this->assertSame( array( 'a' => 1 ), $again->extra( 'somebody-else' ) );
	}

	public function test_a_section_that_is_not_a_section_is_dropped(): void {
		$blueprint = Blueprint::from_json(
			'{"format":1,"extras":{"good":{"a":1},"bad":"not an array","":{"a":1}}}'
		);

		$this->assertSame( array( 'good' ), array_keys( $blueprint->extras() ) );
	}

	public function test_extras_that_are_not_a_map_read_as_none(): void {
		$this->assertSame( array(), Blueprint::from_json( '{"format":1,"extras":"nonsense"}' )->extras() );
		$this->assertSame( array(), Blueprint::from_json( '{"format":1}' )->extras() );
	}

	public function test_asking_for_a_section_nobody_wrote_gives_an_empty_one(): void {
		$this->assertSame( array(), Blueprint::from_json( '{"format":1}' )->extra( 'oxyarea-pro' ) );
	}
}
