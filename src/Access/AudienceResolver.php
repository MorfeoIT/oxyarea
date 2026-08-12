<?php
/**
 * Everything a user counts as.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Access;

/**
 * Asks every registered provider what a user is, and merges the answers.
 *
 * The free plugin contributes one provider, reporting whether the visitor is
 * signed in and which roles they hold. PRO adds providers for the user
 * themselves, their companies and their capabilities. Nothing has to know how
 * many providers there are, which is what lets PRO extend the model without the
 * resolver changing.
 *
 * Duplicates are collapsed: two providers both reporting "role: customer" is not
 * an error and must not turn into two matches.
 */
final class AudienceResolver {

	/**
	 * The providers, in registration order.
	 *
	 * @var list<AudienceProviderInterface>
	 */
	private array $providers;

	/**
	 * Subjects already worked out, by user ID.
	 *
	 * Access is asked about the same user many times in one request — once per
	 * post in a list, once per widget on a dashboard — and the answer cannot
	 * change within a request.
	 *
	 * @var array<int, list<Subject>>
	 */
	private array $cache = array();

	/**
	 * Build the resolver.
	 *
	 * @param list<AudienceProviderInterface> $providers The providers to consult.
	 */
	public function __construct( array $providers = array() ) {
		$this->providers = array_values(
			array_filter(
				$providers,
				static fn ( $provider ): bool => $provider instanceof AudienceProviderInterface
			)
		);
	}

	/**
	 * Every subject this user presents.
	 *
	 * @param int $user_id User ID, or 0 for a signed-out visitor.
	 * @return list<Subject>
	 */
	public function subjects_for( int $user_id ): array {
		if ( isset( $this->cache[ $user_id ] ) ) {
			return $this->cache[ $user_id ];
		}

		$subjects = array();

		foreach ( $this->providers as $provider ) {
			foreach ( $provider->get_subjects( $user_id ) as $subject ) {
				if ( ! $subject instanceof Subject ) {
					continue;
				}

				$subjects[ $subject->key() ] = $subject;
			}
		}

		$this->cache[ $user_id ] = array_values( $subjects );

		return $this->cache[ $user_id ];
	}

	/**
	 * Whether a user presents a particular subject.
	 *
	 * @param int     $user_id User ID, or 0 for a signed-out visitor.
	 * @param Subject $subject The subject to look for.
	 * @return bool
	 */
	public function presents( int $user_id, Subject $subject ): bool {
		foreach ( $this->subjects_for( $user_id ) as $presented ) {
			if ( $presented->equals( $subject ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Forget what has been worked out.
	 *
	 * Needed when a user's roles change inside a single request, which is exactly
	 * what the role screen does.
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->cache = array();
	}
}
