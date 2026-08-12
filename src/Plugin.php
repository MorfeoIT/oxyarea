<?php
/**
 * Wiring.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea;

use OxyArea\Access\AccessResolver;
use OxyArea\Access\AccessResolverInterface;
use OxyArea\Access\AssignmentRepositoryInterface;
use OxyArea\Access\AudienceProviderInterface;
use OxyArea\Access\AudienceResolver;
use OxyArea\Access\SubjectCodec;
use OxyArea\Access\HookedAccessResolver;
use OxyArea\Admin\DashboardPreviewScreen;
use OxyArea\Admin\Menu;
use OxyArea\Admin\RedirectsScreen;
use OxyArea\Admin\RestrictionMetabox;
use OxyArea\Admin\RolesScreen;
use OxyArea\Admin\SettingsScreen;
use OxyArea\Admin\ToolsScreen;
use OxyArea\Admin\Wizard;
use OxyArea\Auth\FormController;
use OxyArea\Auth\FormErrors;
use OxyArea\Auth\FormHandler;
use OxyArea\Auth\LoginForm;
use OxyArea\Auth\LogoutForm;
use OxyArea\Auth\LostPasswordForm;
use OxyArea\Auth\PasswordResetLinks;
use OxyArea\Auth\ProfileForm;
use OxyArea\Auth\ResetPasswordForm;
use OxyArea\Blocks\DashboardBlocks;
use OxyArea\Blocks\Registrar;
use OxyArea\Content\ContentGuard;
use OxyArea\Content\QueryGuard;
use OxyArea\Content\Restrictions;
use OxyArea\Dashboard\AudienceMetabox;
use OxyArea\Dashboard\DashboardPostType;
use OxyArea\Dashboard\DashboardRenderer;
use OxyArea\Dashboard\DashboardRepositoryInterface;
use OxyArea\Dashboard\DashboardResolver;
use OxyArea\Infrastructure\ClockInterface;
use OxyArea\Infrastructure\Container;
use OxyArea\Infrastructure\Migrator;
use OxyArea\Infrastructure\Registrable;
use OxyArea\Infrastructure\Settings;
use OxyArea\Infrastructure\SystemClock;
use OxyArea\Infrastructure\Templates;
use OxyArea\Persistence\AssignmentRepository;
use OxyArea\Persistence\DashboardRepository;
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
use OxyArea\Tools\Porter;

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
	 * Subjects, to and from the strings a form field carries.
	 *
	 * Shared by the three admin surfaces that let somebody name an audience, and
	 * the seam an add-on extends to add a kind of subject the free plugin has
	 * never heard of.
	 */
	public const SUBJECT_CODEC = 'access.subject_codec';

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
	 * Service identifier of the restriction lookup.
	 */
	public const RESTRICTIONS = 'content.restrictions';

	/**
	 * Service identifier of the guard on a single page.
	 */
	public const CONTENT_GUARD = 'content.guard';

	/**
	 * Service identifier of the guard on listings, feeds, REST and the sitemap.
	 */
	public const QUERY_GUARD = 'content.query-guard';

	/**
	 * Service identifier of the restriction metabox.
	 */
	public const RESTRICTION_METABOX = 'admin.restriction';

	/**
	 * Service identifier of the dashboard post type.
	 */
	public const DASHBOARD_POST_TYPE = 'dashboard.post-type';

	/**
	 * Service identifier of the dashboard store.
	 */
	public const DASHBOARDS = 'dashboard.repository';

	/**
	 * Service identifier of the dashboard resolver.
	 */
	public const DASHBOARD_RESOLVER = 'dashboard.resolver';

	/**
	 * Service identifier of the dashboard renderer.
	 */
	public const DASHBOARD_RENDERER = 'dashboard.renderer';

	/**
	 * Service identifier of the audience metabox.
	 */
	public const DASHBOARD_AUDIENCE = 'dashboard.audience';

	/**
	 * Service identifier of the dashboard blocks.
	 */
	public const DASHBOARD_BLOCKS = 'dashboard.blocks';

	/**
	 * Service identifier of the dashboard preview screen.
	 */
	public const DASHBOARD_PREVIEW = 'admin.dashboard-preview';

	/**
	 * Service identifier of the roles screen.
	 */
	public const ROLES_SCREEN = 'admin.roles';

	/**
	 * Service identifier of the import and export service.
	 */
	public const PORTER = 'tools.porter';

	/**
	 * Service identifier of the settings screen.
	 */
	public const SETTINGS_SCREEN = 'admin.settings';

	/**
	 * Service identifier of the tools screen.
	 */
	public const TOOLS_SCREEN = 'admin.tools';

	/**
	 * Service identifier of the setup wizard.
	 */
	public const WIZARD = 'admin.wizard';

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
			self::SUBJECT_CODEC,
			static fn (): SubjectCodec => new SubjectCodec()
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
			self::RESTRICTIONS,
			static fn ( Container $c ): Restrictions => new Restrictions(
				$c->get_typed( self::ASSIGNMENTS, AssignmentRepositoryInterface::class )
			)
		);

		$container->set(
			self::CONTENT_GUARD,
			static fn ( Container $c ): ContentGuard => new ContentGuard(
				$c->get_typed( self::ACCESS, AccessResolverInterface::class ),
				$c->get_typed( self::RESTRICTIONS, Restrictions::class ),
				$c->get_typed( self::SETTINGS, Settings::class ),
				$c->get_typed( self::TEMPLATES, Templates::class )
			)
		);

		$container->set(
			self::QUERY_GUARD,
			static fn ( Container $c ): QueryGuard => new QueryGuard(
				$c->get_typed( self::ACCESS, AccessResolverInterface::class ),
				$c->get_typed( self::RESTRICTIONS, Restrictions::class )
			)
		);

		$container->set(
			self::DASHBOARD_POST_TYPE,
			static fn (): DashboardPostType => new DashboardPostType()
		);

		$container->set(
			self::DASHBOARDS,
			static fn (): DashboardRepository => new DashboardRepository()
		);

		$container->set(
			self::DASHBOARD_RESOLVER,
			static fn (): DashboardResolver => new DashboardResolver()
		);

		$container->set(
			self::DASHBOARD_RENDERER,
			static fn ( Container $c ): DashboardRenderer => new DashboardRenderer(
				$c->get_typed( self::DASHBOARDS, DashboardRepositoryInterface::class ),
				$c->get_typed( self::DASHBOARD_RESOLVER, DashboardResolver::class ),
				$c->get_typed( self::AUDIENCE, AudienceResolver::class )
			)
		);

		$container->set(
			self::DASHBOARD_AUDIENCE,
			static fn ( Container $c ): AudienceMetabox => new AudienceMetabox(
				$c->get_typed( self::DASHBOARDS, DashboardRepository::class ),
				$c->get_typed( self::SUBJECT_CODEC, SubjectCodec::class )
			)
		);

		$container->set(
			self::DASHBOARD_BLOCKS,
			static fn ( Container $c ): DashboardBlocks => new DashboardBlocks(
				$c->get_typed( self::DASHBOARD_RENDERER, DashboardRenderer::class ),
				$c->get_typed( self::TEMPLATES, Templates::class )
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
					$c->get_typed( self::REDIRECTS, RedirectService::class ),
					$c->get_typed( self::SUBJECT_CODEC, SubjectCodec::class )
				)
			);

			$container->set(
				self::RESTRICTION_METABOX,
				static fn ( Container $c ): RestrictionMetabox => new RestrictionMetabox(
					$c->get_typed( self::ASSIGNMENTS, AssignmentRepositoryInterface::class ),
					$c->get_typed( self::RESTRICTIONS, Restrictions::class ),
					$c->get_typed( self::SUBJECT_CODEC, SubjectCodec::class )
				)
			);

			$container->set(
				self::DASHBOARD_PREVIEW,
				static fn ( Container $c ): DashboardPreviewScreen => new DashboardPreviewScreen(
					$c->get_typed( self::DASHBOARD_RENDERER, DashboardRenderer::class )
				)
			);

			$container->set(
				self::PORTER,
				static fn ( Container $c ): Porter => new Porter(
					$c->get_typed( self::SETTINGS, Settings::class ),
					$c->get_typed( self::REDIRECT_RULES, RuleRepositoryInterface::class ),
					$c->get_typed( self::DASHBOARDS, DashboardRepository::class )
				)
			);

			$container->set(
				self::SETTINGS_SCREEN,
				static fn ( Container $c ): SettingsScreen => new SettingsScreen(
					$c->get_typed( self::SETTINGS, Settings::class )
				)
			);

			$container->set(
				self::TOOLS_SCREEN,
				static fn ( Container $c ): ToolsScreen => new ToolsScreen(
					$c->get_typed( self::PORTER, Porter::class )
				)
			);

			$container->set(
				self::WIZARD,
				static fn ( Container $c ): Wizard => new Wizard(
					$c->get_typed( self::ROLE_MANAGER, RoleManager::class ),
					$c->get_typed( self::REDIRECT_RULES, RuleRepositoryInterface::class ),
					$c->get_typed( self::SETTINGS, Settings::class )
				)
			);

			$container->set(
				self::MENU,
				static fn ( Container $c ): Menu => new Menu(
					$c->get_typed( self::ROLES_SCREEN, RolesScreen::class ),
					$c->get_typed( self::REDIRECTS_SCREEN, RedirectsScreen::class ),
					$c->get_typed( self::DASHBOARD_PREVIEW, DashboardPreviewScreen::class ),
					$c->get_typed( self::SETTINGS_SCREEN, SettingsScreen::class ),
					$c->get_typed( self::TOOLS_SCREEN, ToolsScreen::class ),
					$c->get_typed( self::WIZARD, Wizard::class )
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
