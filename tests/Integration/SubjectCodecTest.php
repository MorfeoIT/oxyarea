<?php
/**
 * Subjects, to and from the strings a form carries.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Integration;

use OxyArea\Access\Subject;
use OxyArea\Access\SubjectCodec;
use OxyArea\Tests\Support\CastTestCase;

/**
 * The round trip, which is the property everything else depends on.
 *
 * A subject that encodes to a value which does not decode back to it appears
 * unticked on the screen where it is stored, and is lost the next time somebody
 * presses Update. The failure is silent and it destroys configuration — and
 * until this class existed, three private copies of the same parser were free to
 * drift into exactly that.
 *
 * Written against a real WordPress because the interesting half is roles:
 * whether one exists, what it is called, and what happens to a rule naming one
 * that has been deleted. A stub would only prove the stub agrees with itself.
 */
final class SubjectCodecTest extends CastTestCase {

	/**
	 * The codec under test.
	 *
	 * @var SubjectCodec
	 */
	private SubjectCodec $codec;

	public function set_up(): void {
		parent::set_up();

		$this->codec = new SubjectCodec();
	}

	public function tear_down(): void {
		remove_all_filters( 'oxyarea_subject_decode' );
		remove_all_filters( 'oxyarea_subject_encode' );
		remove_all_filters( 'oxyarea_subject_label' );
		remove_all_filters( 'oxyarea_subject_values' );
		remove_all_actions( 'oxyarea_subject_controls' );

		parent::tear_down();
	}

	/**
	 * @dataProvider round_trips
	 *
	 * @param string $value The encoded form.
	 */
	public function test_what_encodes_decodes_back_to_itself( string $value ): void {
		$subject = $this->codec->decode( $value );

		$this->assertNotNull( $subject, sprintf( '"%s" should decode.', $value ) );
		$this->assertSame( $value, $this->codec->encode( $subject ) );
	}

	/**
	 * Every value the free plugin's own controls can produce.
	 *
	 * @return array<string, array{string}>
	 */
	public static function round_trips(): array {
		return array(
			'anonymous'     => array( 'anonymous' ),
			'authenticated' => array( 'authenticated' ),
			'a role'        => array( 'role:customer' ),
		);
	}

	public function test_a_role_that_does_not_exist_does_not_decode(): void {
		// A rule naming a role nobody holds is worse than no rule, because it
		// looks like protection while matching nobody.
		$this->assertNull( $this->codec->decode( 'role:nothing_like_this' ) );
	}

	/**
	 * @dataProvider nonsense
	 *
	 * @param string $value Something posted that is not a subject.
	 */
	public function test_nonsense_decodes_to_nothing_rather_than_something( string $value ): void {
		// This runs on data posted from a browser. "I do not know what this is"
		// has to mean nothing happens, never that something is guessed.
		$this->assertNull( $this->codec->decode( $value ) );
	}

	/**
	 * Values that must not become subjects.
	 *
	 * @return array<string, array{string}>
	 */
	public static function nonsense(): array {
		return array(
			'empty'          => array( '' ),
			'spaces'         => array( '   ' ),
			'a bare word'    => array( 'everybody' ),
			'an empty role'  => array( 'role:' ),
			'a partial'      => array( 'role' ),
			'an add-on type' => array( 'user:1' ),
			'a script'       => array( '<script>alert(1)</script>' ),
		);
	}

	public function test_whitespace_around_a_value_is_not_a_reason_to_lose_a_rule(): void {
		$this->assertNotNull( $this->codec->decode( "  authenticated \n" ) );
	}

	public function test_a_subject_type_it_cannot_draw_encodes_to_nothing(): void {
		// And "nothing" means the screen leaves it alone. A rule naming a subject
		// whose add-on has been deactivated must survive being looked at, not be
		// dropped by the screen that cannot draw it.
		$this->assertSame( '', $this->codec->encode( new Subject( Subject::USER, 7 ) ) );
	}

	public function test_an_add_on_can_teach_it_a_new_kind_of_subject(): void {
		// The whole point of the class. This is what OxyArea PRO does.
		add_filter(
			'oxyarea_subject_decode',
			static function ( $subject, string $value ) {
				return 0 === strpos( $value, 'user:' ) ? new Subject( Subject::USER, substr( $value, 5 ) ) : $subject;
			},
			10,
			2
		);

		add_filter(
			'oxyarea_subject_encode',
			static function ( string $value, Subject $subject ): string {
				return Subject::USER === $subject->type() ? 'user:' . $subject->id() : $value;
			},
			10,
			2
		);

		$decoded = $this->codec->decode( 'user:12' );

		$this->assertNotNull( $decoded );
		$this->assertSame( Subject::USER, $decoded->type() );
		$this->assertSame( '12', $decoded->id() );
		$this->assertSame( 'user:12', $this->codec->encode( $decoded ) );
	}

	public function test_a_filter_returning_rubbish_is_ignored_rather_than_trusted(): void {
		// An add-on's bug must not become an authorisation decision in this
		// plugin.
		add_filter( 'oxyarea_subject_decode', static fn () => 'not a subject' );
		add_filter( 'oxyarea_subject_encode', static fn () => array( 'nor', 'this' ) );

		$this->assertNull( $this->codec->decode( 'user:12' ) );
		$this->assertSame( '', $this->codec->encode( new Subject( Subject::USER, 12 ) ) );
	}

	public function test_a_role_is_named_the_way_the_site_names_it(): void {
		$this->assertSame( 'Customer', $this->codec->label( Subject::role( 'customer' ) ) );
	}

	public function test_a_deleted_role_says_so_rather_than_showing_a_slug(): void {
		$label = $this->codec->label( new Subject( Subject::ROLE, 'vanished' ) );

		$this->assertStringContainsString( 'vanished', $label );
		$this->assertStringContainsString( 'no longer exists', $label );
	}

	public function test_an_unknown_subject_is_named_by_an_add_on_if_one_offers(): void {
		add_filter(
			'oxyarea_subject_label',
			static function ( string $label, Subject $subject ): string {
				return Subject::USER === $subject->type() ? 'Mario Rossi' : $label;
			},
			10,
			2
		);

		$this->assertSame( 'Mario Rossi', $this->codec->label( new Subject( Subject::USER, 12 ) ) );
	}

	public function test_an_unknown_subject_with_nobody_to_name_it_still_reads_as_something(): void {
		$label = $this->codec->label( new Subject( Subject::USER, 12 ) );

		$this->assertNotSame( '', $label );
		$this->assertStringContainsString( '12', $label );
	}

	public function test_an_add_on_can_contribute_values_at_save_time(): void {
		add_filter(
			'oxyarea_subject_values',
			static function ( array $values, string $context ): array {
				return 'restriction' === $context ? array_merge( $values, array( 'user:12' ) ) : $values;
			},
			10,
			2
		);

		$this->assertSame(
			array( 'role:customer', 'user:12' ),
			$this->codec->gather( array( 'role:customer' ), 'restriction' )
		);

		$this->assertSame(
			array( 'role:customer' ),
			$this->codec->gather( array( 'role:customer' ), 'dashboard' ),
			'A filter that names a context must not affect the others.'
		);
	}

	public function test_gathering_drops_empties_and_duplicates(): void {
		add_filter(
			'oxyarea_subject_values',
			static fn ( array $values ): array => array_merge( $values, array( '', '   ', 'role:customer', 42 ) )
		);

		$this->assertSame( array( 'role:customer' ), $this->codec->gather( array( 'role:customer' ), 'restriction' ) );
	}

	public function test_a_filter_that_returns_something_absurd_leaves_the_values_alone(): void {
		add_filter( 'oxyarea_subject_values', static fn () => 'not an array' );

		$this->assertSame( array( 'role:customer' ), $this->codec->gather( array( 'role:customer' ), 'restriction' ) );
	}

	public function test_the_controls_action_says_which_screen_is_asking(): void {
		$seen = array();

		add_action(
			'oxyarea_subject_controls',
			static function ( string $context, array $chosen ) use ( &$seen ): void {
				$seen[] = array( $context, $chosen );
			},
			10,
			2
		);

		$this->codec->render_extra_controls( 'dashboard', array( 'role:customer' ) );

		$this->assertSame( array( array( 'dashboard', array( 'role:customer' ) ) ), $seen );
	}
}
