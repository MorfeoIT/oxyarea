<?php
/**
 * Subjects and resources.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tests\Unit;

use InvalidArgumentException;
use OxyArea\Access\ProtectedResource;
use OxyArea\Access\Subject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OxyArea\Access\Subject
 * @covers \OxyArea\Access\ProtectedResource
 */
final class SubjectTest extends TestCase {

	public function test_a_role_subject_carries_its_slug(): void {
		$subject = Subject::role( 'customer' );

		$this->assertSame( Subject::ROLE, $subject->type() );
		$this->assertSame( 'customer', $subject->id() );
	}

	public function test_subjects_without_an_identifier_have_an_empty_one(): void {
		$this->assertSame( '', Subject::authenticated()->id() );
	}

	public function test_two_subjects_of_the_same_type_and_id_are_equal(): void {
		$this->assertTrue( Subject::role( 'customer' )->equals( Subject::role( 'customer' ) ) );
	}

	public function test_the_same_identifier_under_a_different_type_is_a_different_subject(): void {
		// Role 5 and user 5 must never collide: the whole object-level security
		// model rests on subject type and identifier being read together.
		$this->assertFalse(
			( new Subject( Subject::ROLE, '5' ) )->equals( new Subject( Subject::USER, '5' ) )
		);
	}

	public function test_numeric_identifiers_are_kept_as_strings(): void {
		$this->assertSame( '42', ( new Subject( Subject::USER, 42 ) )->id() );
	}

	public function test_the_key_distinguishes_type_from_identifier(): void {
		$this->assertSame( 'role:customer', Subject::role( 'customer' )->key() );
		$this->assertSame( 'authenticated', Subject::authenticated()->key() );
	}

	public function test_a_subject_must_have_a_type(): void {
		$this->expectException( InvalidArgumentException::class );

		new Subject( '   ' );
	}

	public function test_a_subject_identifier_longer_than_the_column_is_refused(): void {
		// Silently truncating here would make two different companies collide in
		// the unique key, which is a data-leak shaped bug.
		$this->expectException( InvalidArgumentException::class );

		new Subject( Subject::GROUP, str_repeat( 'a', 192 ) );
	}

	public function test_a_resource_carries_its_type_and_id(): void {
		$resource = ProtectedResource::post( 12 );

		$this->assertSame( ProtectedResource::POST, $resource->get_type() );
		$this->assertSame( 12, $resource->get_id() );
		$this->assertSame( 'post:12', $resource->key() );
	}

	public function test_a_resource_must_have_a_type(): void {
		$this->expectException( InvalidArgumentException::class );

		new ProtectedResource( '', 1 );
	}
}
