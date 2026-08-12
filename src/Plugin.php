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
use OxyArea\Admin\RedirectsScreen;
use OxyArea\Admin\RolesScreen;
use OxyArea\Auth\FormController;
use OxyArea\Auth\FormErrors;
use OxyArea\Auth\FormHandler;
use OxyArea\Auth\LoginForm;
use OxyArea\Auth\LogoutForm;
use OxyArea\Auth\LostPasswordForm;
use OxyArea\Auth\PasswordResetLinks;
use OxyArea\Auth\ProfileForm;
use OxyArea\Auth\ResetPasswordForm;
use OxyArea\Blocks\Registrar;
use OxyArea\Infrastructure\ClockInterface;
use OxyArea\Infrastructure\Container;
use OxyArea\Infrastructure\Migrator;
use OxyArea\Infrastructure\Registrable;
use OxyArea\Infrastructure\Settings;
use OxyArea\Infrastructure\SystemClock;
use OxyArea\Infrastructure\Templates;
use OxyArea\Persistence\AssignmentRepository;
use OxyArea\Persistence\RedirectRuleRepository;
use OxyArea\Privacy\PrivacyPolicy;
use OxyArea\Redirect\RedirectResolver;
use OxyArea\Redirect\RedirectService;
use OxyArea\Redirect\RuleRepositoryInterface;
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
	 * Service identifier of the template renderer.
	 */
	public const TEMPLATES = 'templates';

	/**
	 * Service identifier of the frontend form error store.
	 */
	public const FORM_ERRORS = 'auth.errors';

	/**
	 * Service identifier of the sign-in form.
	 */
	public const FORM_LOGIN = 'auth.login';

	/**
	 * Service identifier of the sign-out form.
	 */
	public const FORM_LOGOUT = 'auth.logout';

	/**
	 * Service identifier of the forgotten-password form.
	 */
	public const FORM_LOST_PASSWORD = 'auth.lost-password';

	/**
	 * Service identifier of the set-a-new-password form.
	 */
	public const FORM_RESET_PASSWORD = 'auth.reset-password';

	/**
	 * Service identifier of the profile form.
	 */
	public const FORM_PROFILE = 'auth.profile';

	/**
	 * Service identifier of the frontend form router.
	 */
	public const FORM_CONTROLLER = 'auth.controller';

	/**
	 * Service identifier of the password-flow link rewriter.
	 */
	public const PASSWORD_RESET_LINKS = 'auth.reset-links';

	/**
	 * Service identifier of the block and shortcode registrar.
	 */
	public const BLOCKS = 'blocks';

	/**
	 * Service identifier of the redirect rule store.
	 */
	public const REDIRECT_RULES = 'redirect.rules';

	/**
	 * Service identifier of the redirect engine.
	 */
	public const REDIRECT_RESOLVER = 'redirect.resolver';

	/**
	 * Service identifier of the service that wires the engine to WordPress.
	 */
	public const REDIRECTS = 'redirect.service';

	/**
	 * Service identifier of the redirects screen.
	 */
	public const REDIRECTS_SCREEN = 'admin.redirects';

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

		$container->set(
			self::TEMPLATES,
			static fn (): Templates => new Templates()
		);

		$container->set(
			self::FORM_ERRORS,
			static fn (): FormErrors => new FormErrors()
		);

		$container->set(
			self::FORM_LOGIN,
			static fn ( Container $c ): LoginForm => new LoginForm(
				$c->get_typed( self::TEMPLATES, Templates::class ),
				$c->get_typed( self::FORM_ERRORS, FormErrors::class ),
				$c->get_typed( self::SETTINGS, Settings::class )
			)
		);

		$container->set(
			self::FORM_LOGOUT,
			static fn ( Container $c ): LogoutForm => new LogoutForm(
				$c->get_typed( self::TEMPLATES, Templates::class ),
				$c->get_typed( self::FORM_ERRORS, FormErrors::class )
			)
		);

		$container->set(
			self::FORM_LOST_PASSWORD,
			static fn ( Container $c ): LostPasswordForm => new LostPasswordForm(
				$c->get_typed( self::TEMPLATES, Templates::class ),
				$c->get_typed( self::FORM_ERRORS, FormErrors::class )
			)
		);

		$container->set(
			self::FORM_RESET_PASSWORD,
			static fn ( Container $c ): ResetPasswordForm => new ResetPasswordForm(
				$c->get_typed( self::TEMPLATES, Templates::class ),
				$c->get_typed( self::FORM_ERRORS, FormErrors::class )
			)
		);

		$container->set(
			self::FORM_PROFILE,
			static fn ( Container $c ): ProfileForm => new ProfileForm(
				$c->get_typed( self::TEMPLATES, Templates::class ),
				$c->get_typed( self::FORM_ERRORS, FormErrors::class )
			)
		);

		$container->set(
			self::FORM_CONTROLLER,
			static fn ( Container $c ): FormController => new FormController( self::forms( $c ) )
		);

		$container->set(
			self::BLOCKS,
			static fn ( Container $c ): Registrar => new Registrar( self::forms( $c ) )
		);

		$container->set(
			self::PASSWORD_RESET_LINKS,
			static fn ( Container $c ): PasswordResetLinks => new PasswordResetLinks(
				$c->get_typed( self::SETTINGS, Settings::class )
			)
		);

		$container->set(
			self::REDIRECT_RULES,
			static fn (): RedirectRuleRepository => new RedirectRuleRepository()
		);

		$container->set(
			self::REDIRECT_RESOLVER,
			static fn (): RedirectResolver => new RedirectResolver()
		);

		$container->set(
			self::REDIRECTS,
			static fn ( Container $c ): RedirectService => new RedirectService(
				$c->get_typed( self::REDIRECT_RESOLVER, RedirectResolver::class ),
				$c->get_typed( self::REDIRECT_RULES, RuleRepositoryInterface::class ),
				$c->get_typed( self::AUDIENCE, AudienceResolver::class ),
				$c->get_typed( self::SETTINGS, Settings::class )
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
				self::REDIRECTS_SCREEN,
				static fn ( Container $c ): RedirectsScreen => new RedirectsScreen(
					$c->get_typed( self::REDIRECT_RULES, RuleRepositoryInterface::class ),
					$c->get_typed( self::REDIRECTS, RedirectService::class )
				)
			);

			$container->set(
				self::MENU,
				static fn ( Container $c ): Menu => new Menu(
					$c->get_typed( self::ROLES_SCREEN, RolesScreen::class ),
					$c->get_typed( self::REDIRECTS_SCREEN, RedirectsScreen::class )
				)
			);
		}

		return $container;
	}

	/**
	 * The authentication forms, in the order they were introduced.
	 *
	 * The same objects back both the router and the blocks. One instance each, so
	 * a failure recorded while handling a submission is there when the form
	 * renders again further down the same request.
	 *
	 * @param Container $c The container.
	 * @return list<FormHandler>
	 */
	private static function forms( Container $c ): array {
		return array(
			$c->get_typed( self::FORM_LOGIN, LoginForm::class ),
			$c->get_typed( self::FORM_LOGOUT, LogoutForm::class ),
			$c->get_typed( self::FORM_LOST_PASSWORD, LostPasswordForm::class ),
			$c->get_typed( self::FORM_RESET_PASSWORD, ResetPasswordForm::class ),
			$c->get_typed( self::FORM_PROFILE, ProfileForm::class ),
		);
	}
}
