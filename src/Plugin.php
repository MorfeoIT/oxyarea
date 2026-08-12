<?php
/**
 * Wiring.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea;

use OxyArea\Access\AccessResolver;
use OxyArea\Access\AssignmentRepositoryInterface;
use OxyArea\Access\AudienceProviderInterface;
use OxyArea\Access\AudienceResolver;
use OxyArea\Access\HookedAccessResolver;
use OxyArea\Admin\Menu;
use OxyArea\Admin\RolesScreen;
use OxyArea\Infrastructure\ClockInterface;
use OxyArea\Infrastructure\Container;
use OxyArea\Infrastructure\Migrator;
use OxyArea\Infrastructure\Registrable;
use OxyArea\Infrastructure\Settings;
use OxyArea\Infrastructure\SystemClock;
use OxyArea\Persistence\AssignmentRepository;
use OxyArea\Privacy\PrivacyPolicy;
use OxyArea\Roles\Capabilities;
use OxyArea\Roles\CapabilityManagerCheck;
use OxyArea\Roles\ManagedRoles;
use OxyArea\Roles\RoleAudienceProvider;
use OxyArea\Roles\RoleManager;

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
	 * Service identifier of the clock.
	 */
	public const CLOCK = 'clock';

	/**
	 * Service identifier of the assignment repository.
	 */
	public const ASSIGNMENTS = 'access.assignments';

	/**
	 * Service identifier of the audience resolver.
	 */
	public const AUDIENCE = 'access.audience';

	/**
	 * Service identifier of the access resolver.
	 *
	 * The one every question about who may see what goes through.
	 */
	public const ACCESS = 'access.resolver';

	/**
	 * Service identifier of the managed-roles register.
	 */
	public const MANAGED_ROLES = 'roles.managed';

	/**
	 * Service identifier of the role manager.
	 */
	public const ROLE_MANAGER = 'roles.manager';

	/**
	 * Service identifier of the roles screen.
	 */
	public const ROLES_SCREEN = 'admin.roles';

	/**
	 * Service identifier of the admin menu.
	 */
	public const MENU = 'admin.menu';

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

		$container->set(
			self::CLOCK,
			static fn (): SystemClock => new SystemClock()
		);

		$container->set(
			self::ASSIGNMENTS,
			static fn (): AssignmentRepository => new AssignmentRepository()
		);

		$container->set(
			self::AUDIENCE,
			static function (): AudienceResolver {
				$providers = array( new RoleAudienceProvider() );

				/**
				 * Filters the providers that say what a user counts as.
				 *
				 * This is how PRO teaches OxyArea about companies, groups and
				 * individual users without the resolver knowing they exist. Add
				 * to the list; replacing it wholesale removes the role model the
				 * rest of the plugin is built on.
				 *
				 * @since 0.1.0
				 *
				 * @param list<AudienceProviderInterface> $providers The providers to consult.
				 */
				$filtered = (array) apply_filters( 'oxyarea_audience_providers', $providers );

				return new AudienceResolver( array_values( $filtered ) );
			}
		);

		$container->set(
			self::ACCESS,
			static function ( Container $c ): HookedAccessResolver {
				return new HookedAccessResolver(
					new AccessResolver(
						$c->get_typed( self::ASSIGNMENTS, AssignmentRepositoryInterface::class ),
						$c->get_typed( self::AUDIENCE, AudienceResolver::class ),
						new CapabilityManagerCheck(),
						$c->get_typed( self::CLOCK, ClockInterface::class )
					)
				);
			}
		);

		$container->set(
			self::MANAGED_ROLES,
			static fn (): ManagedRoles => new ManagedRoles()
		);

		$container->set(
			self::ROLE_MANAGER,
			static fn ( Container $c ): RoleManager => new RoleManager(
				$c->get_typed( self::MANAGED_ROLES, ManagedRoles::class )
			)
		);

		// Admin-only services are not built on a front-end request at all. The
		// hooks they add would never fire there, and the objects would be built
		// for nothing on every page view.
		if ( is_admin() ) {
			$container->set(
				self::ROLES_SCREEN,
				static fn ( Container $c ): RolesScreen => new RolesScreen(
					$c->get_typed( self::ROLE_MANAGER, RoleManager::class ),
					$c->get_typed( self::MANAGED_ROLES, ManagedRoles::class )
				)
			);

			$container->set(
				self::MENU,
				static fn ( Container $c ): Menu => new Menu(
					$c->get_typed( self::ROLES_SCREEN, RolesScreen::class )
				)
			);
		}

		return $container;
	}
}
