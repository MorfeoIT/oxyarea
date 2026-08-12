<?php
/**
 * The service container.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Infrastructure;

/**
 * A small registry of lazily built, shared services.
 *
 * Deliberately not a PSR-11 implementation with autowiring: OxyArea needs a
 * place where an add-on can swap one object for another, not a reflection
 * engine. Every service is a singleton within a request, because that is what a
 * WordPress plugin's services are.
 */
final class Container {

	/**
	 * Factories, by identifier.
	 *
	 * @var array<string, callable(Container): object>
	 */
	private array $factories = array();

	/**
	 * Services already built, by identifier.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Identifiers currently being resolved, to catch cycles.
	 *
	 * @var array<string, true>
	 */
	private array $resolving = array();

	/**
	 * Declare a service.
	 *
	 * Calling this twice with the same identifier replaces the factory, which is
	 * how an add-on substitutes its own implementation. Replacing a service that
	 * has already been built is refused rather than silently ignored: two halves
	 * of a request holding two different objects is a bug that surfaces far from
	 * its cause.
	 *
	 * @param string                      $id      Service identifier.
	 * @param callable(Container): object $factory Builds the service.
	 * @return void
	 *
	 * @throws ContainerException If the service has already been built.
	 */
	public function set( string $id, callable $factory ): void {
		if ( isset( $this->instances[ $id ] ) ) {
			throw new ContainerException(
				sprintf( 'The OxyArea service "%s" has already been built and cannot be replaced.', $id )
			);
		}

		$this->factories[ $id ] = $factory;
	}

	/**
	 * Whether a service is declared.
	 *
	 * @param string $id Service identifier.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] );
	}

	/**
	 * Every declared identifier, in declaration order.
	 *
	 * @return list<string>
	 */
	public function ids(): array {
		return array_keys( $this->factories );
	}

	/**
	 * Get a service, building it the first time it is asked for.
	 *
	 * @param string $id Service identifier.
	 * @return object
	 *
	 * @throws ContainerException If the service is unknown, depends on itself, or
	 *                            its factory does not return an object.
	 */
	public function get( string $id ): object {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new ContainerException(
				sprintf( 'The OxyArea service "%s" is not registered.', $id )
			);
		}

		if ( isset( $this->resolving[ $id ] ) ) {
			throw new ContainerException(
				sprintf(
					'The OxyArea service "%s" depends on itself, through: %s.',
					$id,
					implode( ' -> ', array_keys( $this->resolving ) )
				)
			);
		}

		$this->resolving[ $id ] = true;

		try {
			$service = ( $this->factories[ $id ] )( $this );
		} finally {
			unset( $this->resolving[ $id ] );
		}

		if ( ! is_object( $service ) ) {
			throw new ContainerException(
				sprintf( 'The factory for the OxyArea service "%s" did not return an object.', $id )
			);
		}

		$this->instances[ $id ] = $service;

		return $service;
	}
}
