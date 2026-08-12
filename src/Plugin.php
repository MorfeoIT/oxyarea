<?php
/**
 * Wiring.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea;

use OxyArea\Infrastructure\Container;
use OxyArea\Infrastructure\Migrator;
use OxyArea\Infrastructure\Registrable;
use OxyArea\Infrastructure\Settings;
use OxyArea\Privacy\PrivacyPolicy;
use OxyArea\Roles\Capabilities;

/**
 * Builds the object graph and registers the hooks.
 *
 * The container exists for one reason: OxyArea PRO, and any third-party add-on,
 * has to be able to replace a service rather than edit a core file. Everything
 * it does beyond that is deliberately absent.
 */
final class Plugin {

	/**
	 * Service identifier of the settings store.
	 */
	public const SETTINGS = 'settings';

	/**
	 * Service identifier of the privacy policy contributor.
	 */
	public const PRIVACY_POLICY = 'privacy.policy';

	/**
	 * Start the plugin.
	 *
	 * @return void
	 */
	public function boot(): void {
		$migrator = new Migrator();

		if ( $migrator->needs_migration() ) {
			$migrator->migrate();
		}

		Capabilities::ensure_granted();

		$container = $this->container();

		/**
		 * Fires after OxyArea has registered its own services and before any of
		 * them is resolved.
		 *
		 * This is the extension point add-ons use: register a new service, or
		 * overwrite one of OxyArea's own with a compatible implementation.
		 * Nothing has been instantiated yet, so a replacement here is total.
		 *
		 * @since 0.1.0
		 *
		 * @param Container $container The service container.
		 */
		do_action( 'oxyarea_register_services', $container );

		foreach ( $container->ids() as $id ) {
			$service = $container->get( $id );

			if ( $service instanceof Registrable ) {
				$service->register();
			}
		}

		/**
		 * Fires when OxyArea has started and every service has registered.
		 *
		 * @since 0.1.0
		 *
		 * @param Container $container The service container.
		 */
		do_action( 'oxyarea_init', $container );
	}

	/**
	 * The services OxyArea itself provides.
	 *
	 * Factories are closures, so declaring a service costs nothing until
	 * something asks for it.
	 *
	 * @return Container
	 */
	private function container(): Container {
		$container = new Container();

		$container->set(
			self::SETTINGS,
			static fn (): Settings => new Settings()
		);

		$container->set(
			self::PRIVACY_POLICY,
			static fn (): PrivacyPolicy => new PrivacyPolicy()
		);

		return $container;
	}
}
