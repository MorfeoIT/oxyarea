<?php
/**
 * The decision.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Access;

use OxyArea\Infrastructure\ClockInterface;

/**
 * The one place OxyArea decides who may see what.
 *
 * Contains no WordPress call whatsoever. Everything it needs — the rules, the
 * subjects, whether the user administers the plugin, what time it is — arrives
 * through a constructor argument. That is what makes it possible to write a test
 * for every branch of the authorisation logic, which is the only way anybody can
 * honestly claim to know what it does.
 *
 * The rules it applies, in order:
 *
 * 1. Someone who administers OxyArea may read anything OxyArea protects. They
 *    could grant themselves access in three clicks anyway.
 * 2. A rule outside its validity period does not count.
 * 3. An explicit deny that matches beats every allow. Taking access away has to
 *    be reliable or it is not worth offering.
 * 4. An allow that matches any subject the user presents grants access. A user
 *    with four roles needs only one of them to match.
 * 5. Anything else is a refusal, including the case of a resource with no rules
 *    at all.
 *
 * Point 5 deserves saying plainly: this class answers "may this user see this
 * protected thing", not "is this thing protected". Deciding that a post carries
 * no restriction and never asking is the restriction layer's job. If it does
 * ask, the answer for a resource nobody has granted anything on is no.
 */
final class AccessResolver implements AccessResolverInterface {

	/**
	 * Where the rules live.
	 *
	 * @var AssignmentRepositoryInterface
	 */
	private AssignmentRepositoryInterface $assignments;

	/**
	 * What users count as.
	 *
	 * @var AudienceResolver
	 */
	private AudienceResolver $audience;

	/**
	 * Who administers the plugin.
	 *
	 * @var ManagerCheckInterface
	 */
	private ManagerCheckInterface $manager;

	/**
	 * What time it is.
	 *
	 * @var ClockInterface
	 */
	private ClockInterface $clock;

	/**
	 * Build the resolver.
	 *
	 * @param AssignmentRepositoryInterface $assignments Where the rules live.
	 * @param AudienceResolver              $audience    What users count as.
	 * @param ManagerCheckInterface         $manager     Who administers the plugin.
	 * @param ClockInterface                $clock       What time it is.
	 */
	public function __construct(
		AssignmentRepositoryInterface $assignments,
		AudienceResolver $audience,
		ManagerCheckInterface $manager,
		ClockInterface $clock
	) {
		$this->assignments = $assignments;
		$this->audience    = $audience;
		$this->manager     = $manager;
		$this->clock       = $clock;
	}

	/**
	 * Whether a user may view a resource.
	 *
	 * @param int               $user_id User ID, or 0 for a signed-out visitor.
	 * @param ResourceInterface $target  The resource in question.
	 * @return bool
	 */
	public function can_view( int $user_id, ResourceInterface $target ): bool {
		return $this->explain( $user_id, $target )->is_allowed();
	}

	/**
	 * The same question, answered with its reasoning.
	 *
	 * @param int               $user_id User ID, or 0 for a signed-out visitor.
	 * @param ResourceInterface $target  The resource in question.
	 * @return Decision
	 */
	public function explain( int $user_id, ResourceInterface $target ): Decision {
		$signed_in = $user_id > 0;

		// The signed-in test comes first and is not delegated. A manager check
		// that answered yes for user 0 — a buggy add-on, a stub left in place —
		// would otherwise hand every protected resource to the open internet.
		if ( $signed_in && $this->manager->is_manager( $user_id ) ) {
			return Decision::allow( 'Administers OxyArea, and so may read what OxyArea protects' )
				->with_step( $signed_in, $this->signed_in_step( $signed_in ) );
		}

		$subjects = $this->audience->subjects_for( $user_id );
		$rules    = $this->applicable_rules( $target );

		return $this->verdict( $subjects, $rules )
			->with_step( true, $this->subjects_step( $subjects ) )
			->with_step( $signed_in, $this->signed_in_step( $signed_in ) );
	}

	/**
	 * The rules on a resource that count right now.
	 *
	 * @param ResourceInterface $target The resource.
	 * @return list<Assignment>
	 */
	private function applicable_rules( ResourceInterface $target ): array {
		$now = $this->clock->now();

		return array_values(
			array_filter(
				$this->assignments->for_resource( $target ),
				static fn ( Assignment $assignment ): bool => $assignment->applies_at( $now )
			)
		);
	}

	/**
	 * Match the subjects against the rules.
	 *
	 * @param list<Subject>    $subjects What the user counts as.
	 * @param list<Assignment> $rules    The rules that currently count.
	 * @return Decision
	 */
	private function verdict( array $subjects, array $rules ): Decision {
		if ( array() === $rules ) {
			return Decision::deny( 'Nothing grants access to this resource' );
		}

		$matched = array();

		foreach ( $rules as $rule ) {
			foreach ( $subjects as $subject ) {
				if ( $rule->subject()->equals( $subject ) ) {
					$matched[] = $rule;

					break;
				}
			}
		}

		foreach ( $matched as $rule ) {
			if ( $rule->is_deny() ) {
				return Decision::deny(
					sprintf( 'A rule explicitly denies %s', $rule->subject()->key() )
				);
			}
		}

		if ( array() !== $matched ) {
			return Decision::allow(
				sprintf( 'A rule grants access to %s', $matched[0]->subject()->key() )
			);
		}

		return Decision::deny( 'No rule on this resource matches the user' );
	}

	/**
	 * How the signed-in step reads.
	 *
	 * @param bool $signed_in Whether the visitor is signed in.
	 * @return string
	 */
	private function signed_in_step( bool $signed_in ): string {
		return $signed_in ? 'Signed in' : 'Not signed in';
	}

	/**
	 * How the subjects step reads.
	 *
	 * @param list<Subject> $subjects What the user counts as.
	 * @return string
	 */
	private function subjects_step( array $subjects ): string {
		if ( array() === $subjects ) {
			return 'Counts as nothing the rules can match';
		}

		return 'Counts as: ' . implode(
			', ',
			array_map( static fn ( Subject $subject ): string => $subject->key(), $subjects )
		);
	}
}
