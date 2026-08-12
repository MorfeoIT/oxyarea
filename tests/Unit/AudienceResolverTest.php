<?php
/**
 * Working out what a user counts as.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Unit;

use OxyArea\Access\AudienceResolver;
use OxyArea\Access\Subject;
use OxyArea\Tests\Support\StubAudienceProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @covers \OxyArea\Access\AudienceResolver
 */
final class AudienceResolverTest extends TestCase {

	public function test_with_no_providers_a_user_counts_as_nothing(): void {
		$this->assertSame( array(), ( new AudienceResolver() )->subjects_for( 7 ) );
	}

	public function test_it_merges_what_every_provider_reports(): void {
		$resolver = new AudienceResolver(
			array(
				new StubAudienceProvider( array( 7 => array( Subject::authenticated() ) ) ),
				new StubAudienceProvider( array( 7 => array( Subject::role( 'customer' ) ) ) ),
			)
		);

		$this->assertSame(
			array( 'authenticated', 'role:customer' ),
			array_map( static fn ( Subject $s ): string => $s->key(), $resolver->subjects_for( 7 ) )
		);
	}

	public function test_the_same_subject_from_two_providers_counts_once(): void {
		// Otherwise a duplicated subject would match a rule twice, which is
		// harmless today and exactly the sort of thing that stops being harmless
		// once priorities and counting get involved.
		$resolver = new AudienceResolver(
			array(
				new StubAudienceProvider( array( 7 => array( Subject::role( 'customer' ) ) ) ),
				new StubAudienceProvider( array( 7 => array( Subject::role( 'customer' ) ) ) ),
			)
		);

		$this->assertCount( 1, $resolver->subjects_for( 7 ) );
	}

	public function test_anything_that_is_not_a_provider_is_dropped(): void {
		$resolver = new AudienceResolver(
			array(
				new stdClass(),
				'not a provider',
				new StubAudienceProvider( array( 7 => array( Subject::authenticated() ) ) ),
			)
		);

		$this->assertCount( 1, $resolver->subjects_for( 7 ) );
	}

	public function test_anything_a_provider_returns_that_is_not_a_subject_is_dropped(): void {
		$provider = new class() implements \OxyArea\Access\AudienceProviderInterface {
			/**
			 * Deliberately returns rubbish alongside one real subject.
			 *
			 * @param int $user_id User ID.
			 * @return array<int, mixed>
			 */
			public function get_subjects( int $user_id ): array {
				unset( $user_id );

				return array( Subject::authenticated(), 'role:customer', null );
			}
		};

		$this->assertCount( 1, ( new AudienceResolver( array( $provider ) ) )->subjects_for( 7 ) );
	}

	public function test_providers_are_asked_once_per_user_per_request(): void {
		$provider = new StubAudienceProvider( array( 7 => array( Subject::authenticated() ) ) );
		$resolver = new AudienceResolver( array( $provider ) );

		$resolver->subjects_for( 7 );
		$resolver->subjects_for( 7 );
		$resolver->subjects_for( 7 );

		$this->assertSame( 1, $provider->calls() );
	}

	public function test_different_users_are_worked_out_separately(): void {
		$resolver = new AudienceResolver(
			array(
				new StubAudienceProvider(
					array(
						7 => array( Subject::role( 'customer' ) ),
						8 => array( Subject::role( 'agent' ) ),
					)
				),
			)
		);

		$this->assertTrue( $resolver->presents( 7, Subject::role( 'customer' ) ) );
		$this->assertFalse( $resolver->presents( 7, Subject::role( 'agent' ) ) );
		$this->assertTrue( $resolver->presents( 8, Subject::role( 'agent' ) ) );
	}

	public function test_flushing_makes_it_ask_again(): void {
		$provider = new StubAudienceProvider( array( 7 => array( Subject::authenticated() ) ) );
		$resolver = new AudienceResolver( array( $provider ) );

		$resolver->subjects_for( 7 );
		$resolver->flush();
		$resolver->subjects_for( 7 );

		$this->assertSame( 2, $provider->calls() );
	}
}
