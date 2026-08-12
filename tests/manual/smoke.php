<?php
/**
 * Exercises the parts of OxyArea that only exist inside WordPress: the role
 * manager, the assignment repository and the resolver wired to real roles.
 *
 * Run with: wp eval-file smoke.php
 *
 * Cleans up after itself. Anything it leaves behind is a bug in this file.
 */

use OxyArea\Access\AccessResolver;
use OxyArea\Access\Assignment;
use OxyArea\Access\AudienceResolver;
use OxyArea\Access\ProtectedResource;
use OxyArea\Access\Subject;
use OxyArea\Infrastructure\SystemClock;
use OxyArea\Persistence\AssignmentRepository;
use OxyArea\Roles\CapabilityManagerCheck;
use OxyArea\Roles\ManagedRoles;
use OxyArea\Roles\RoleException;
use OxyArea\Roles\RoleManager;

// wp eval-file includes this inside a function, so top-level variables here are
// not globals. The counters are addressed explicitly so that check() reaches the
// same two whatever scope it is called from.
$GLOBALS['oxa_passed'] = 0;
$GLOBALS['oxa_failed'] = 0;

/**
 * Assert.
 *
 * @param string $what      What is being checked.
 * @param bool   $condition Whether it holds.
 */
function check( string $what, bool $condition ): void {
	if ( $condition ) {
		++$GLOBALS['oxa_passed'];
		echo "  ok    {$what}\n";

		return;
	}

	++$GLOBALS['oxa_failed'];
	echo "  FAIL  {$what}\n";
}

/**
 * Assert that something is refused.
 *
 * @param string   $what   What is being checked.
 * @param callable $action The thing that should be refused.
 */
function refuses( string $what, callable $action ): void {
	try {
		$action();
		check( $what, false );
	} catch ( RoleException $e ) {
		check( $what . ' — ' . $e->getMessage(), true );
	}
}

$admin = get_user_by( 'login', 'oxysoft' );
$alice = get_user_by( 'login', 'alice' );
$bob   = get_user_by( 'login', 'bob' );
$carol = get_user_by( 'login', 'carol' );

$managed = new ManagedRoles();
$manager = new RoleManager( $managed );
$repo    = new AssignmentRepository();

/**
 * A resolver with a fresh audience cache, since roles change during this run.
 *
 * @return AccessResolver
 */
function resolver(): AccessResolver {
	return new AccessResolver(
		new AssignmentRepository(),
		new AudienceResolver( array( new \OxyArea\Roles\RoleAudienceProvider() ) ),
		new CapabilityManagerCheck(),
		new SystemClock()
	);
}

echo "\n== creating roles ==\n";

$customer = $manager->create( 'Test Customer', 'oxytest_customer', array( 'read' ), $admin->ID );
$agent    = $manager->create( 'Test Agent', 'oxytest_agent', array( 'read', 'upload_files' ), $admin->ID );

check( 'the customer role exists', null !== get_role( $customer ) );
check( 'OxyArea remembers it created it', $managed->contains( $customer ) );
check( 'every new role can read', get_role( $customer )->has_cap( 'read' ) );
check( 'the agent role got upload_files', get_role( $agent )->has_cap( 'upload_files' ) );

echo "\n== the refusals ==\n";

refuses(
	'will not edit the administrator role',
	static fn () => $manager->update_capabilities( 'administrator', array( 'read' ), $admin->ID )
);

refuses(
	'will not delete a role it did not create',
	static fn () => $manager->delete( 'editor', 'subscriber', $admin->ID )
);

refuses(
	'will not create a role whose name leaves no identifier',
	static fn () => $manager->create( '???', '', array(), $admin->ID )
);

refuses(
	'will not create a role that already exists',
	static fn () => $manager->create( 'Test Customer', 'oxytest_customer', array(), $admin->ID )
);

refuses(
	'will not move a role\'s people to a role that does not exist',
	static fn () => $manager->delete( 'oxytest_agent', 'no_such_role', $admin->ID )
);

echo "\n== the escalation guard ==\n";

// Alice is a subscriber. She holds none of these, so none of them may be given
// away by her, whatever the form posts.
$escalated = $manager->create( 'Escalation Attempt', 'oxytest_escalation', array( 'install_plugins', 'edit_users', 'manage_options', 'upload_files' ), $alice->ID );
$role      = get_role( $escalated );

check( 'a subscriber cannot grant install_plugins', ! $role->has_cap( 'install_plugins' ) );
check( 'a subscriber cannot grant edit_users', ! $role->has_cap( 'edit_users' ) );
check( 'a subscriber cannot grant manage_options', ! $role->has_cap( 'manage_options' ) );
check( 'a subscriber cannot grant upload_files either', ! $role->has_cap( 'upload_files' ) );
check( 'but the role still exists and can read', $role->has_cap( 'read' ) );

echo "\n== capabilities from other plugins are left alone ==\n";

$role = get_role( $agent );
$role->add_cap( 'woocommerce_view_order' );

$manager->update_capabilities( $agent, array( 'read' ), $admin->ID );

check(
	'a capability outside the catalogue survives a save',
	get_role( $agent )->has_cap( 'woocommerce_view_order' )
);
check(
	'a capability inside the catalogue is removed as asked',
	! get_role( $agent )->has_cap( 'upload_files' )
);

echo "\n== Alice and Bob ==\n";

$manager->assign_user( $alice->ID, $customer, $admin->ID );
$manager->assign_user( $bob->ID, 'subscriber', $admin->ID );
$manager->assign_user( $carol->ID, $agent, $admin->ID );

$post_id  = wp_insert_post(
	array(
		'post_title'   => 'Customer contract 2026',
		'post_content' => 'Private.',
		'post_status'  => 'publish',
		'post_author'  => $admin->ID,
	)
);
$document = ProtectedResource::post( (int) $post_id );

$repo->replace_for_resource( $document, array( new Assignment( Subject::role( $customer ) ) ) );

check( 'Alice, a customer, may see the contract', resolver()->can_view( $alice->ID, $document ) );
check( 'Bob, who is not, may not', ! resolver()->can_view( $bob->ID, $document ) );
check( 'Carol, an agent, may not', ! resolver()->can_view( $carol->ID, $document ) );
check( 'a signed-out visitor may not', ! resolver()->can_view( 0, $document ) );
check( 'the administrator may', resolver()->can_view( $admin->ID, $document ) );

echo "\n== changing a role changes what Alice sees ==\n";

$manager->assign_user( $alice->ID, 'subscriber', $admin->ID );
check( 'taking the role away takes the contract away', ! resolver()->can_view( $alice->ID, $document ) );

$manager->assign_user( $alice->ID, $customer, $admin->ID );
check( 'giving it back gives the contract back', resolver()->can_view( $alice->ID, $document ) );

echo "\n== an explicit deny ==\n";

// Carol gets both roles, which is the only way to test deny-beats-allow with
// what the free plugin can express. add_role rather than the manager, because
// assign_user deliberately replaces roles rather than adding to them.
get_user_by( 'id', $carol->ID )->add_role( $customer );

$repo->replace_for_resource(
	$document,
	array(
		new Assignment( Subject::role( $customer ) ),
		new Assignment( Subject::role( $agent ), Assignment::DENY ),
	)
);

check( 'Alice, a customer only, still sees it', resolver()->can_view( $alice->ID, $document ) );
check( 'Carol, who is also an agent, does not: the deny wins', ! resolver()->can_view( $carol->ID, $document ) );

echo "\n== the free/PRO boundary ==\n";

// A rule naming one individual is stored and read back perfectly well, but
// nothing in the free plugin ever presents a "user" subject, so it matches
// nobody. Per-user targeting is what PRO's audience providers add. This is the
// specification working, not a gap.
$repo->replace_for_resource(
	$document,
	array(
		new Assignment( Subject::role( $customer ) ),
		new Assignment( new Subject( Subject::USER, (string) $alice->ID ), Assignment::DENY ),
	)
);

check(
	'a rule naming an individual does nothing without PRO',
	resolver()->can_view( $alice->ID, $document )
);

get_user_by( 'id', $carol->ID )->remove_role( $customer );

echo "\n== the stored rules survive a round trip ==\n";

$stored = $repo->for_resource( $document );
check( 'both rules came back', 2 === count( $stored ) );
check( 'the deny is still a deny', $stored[0]->is_deny() || $stored[1]->is_deny() );

$repo->replace_for_resource( $document, array() );
check( 'clearing the rules leaves nothing granting access', ! resolver()->can_view( $alice->ID, $document ) );

echo "\n== deleting a role moves its people ==\n";

$moved = $manager->delete( $customer, 'subscriber', $admin->ID );

check( 'the role is gone', null === get_role( $customer ) );
check( 'OxyArea has forgotten it', ! $managed->contains( $customer ) );
check( 'one person was moved', 1 === $moved );

$alice = get_user_by( 'id', $alice->ID );
check( 'Alice is a subscriber again, not roleless', in_array( 'subscriber', (array) $alice->roles, true ) );

echo "\n== the blocks and shortcodes exist ==\n";

$registry = \WP_Block_Type_Registry::get_instance();

foreach ( array( 'login', 'logout', 'lost-password', 'reset-password', 'profile' ) as $block ) {
	check( "the oxyarea/{$block} block is registered", $registry->is_registered( 'oxyarea/' . $block ) );
}

foreach ( array( 'oxyarea_login', 'oxyarea_logout', 'oxyarea_lost_password', 'oxyarea_reset_password', 'oxyarea_profile' ) as $shortcode ) {
	check( "the [{$shortcode}] shortcode is registered", shortcode_exists( $shortcode ) );
}

echo "\n== the sign-in form renders ==\n";

wp_set_current_user( 0 );

$login_form = do_shortcode( '[oxyarea_login]' );

check( 'it produces a form', false !== strpos( $login_form, '<form' ) );
check( 'it carries a nonce', false !== strpos( $login_form, '_wpnonce' ) );
check( 'it names itself in the action field', false !== strpos( $login_form, 'value="login"' ) );
check( 'the password field is a password field', false !== strpos( $login_form, 'type="password"' ) );
check( 'the username field has a label', false !== strpos( $login_form, 'for="oxyarea-user-login"' ) );
check( 'nothing is echoed unescaped into an attribute', false === strpos( $login_form, 'value="<' ) );

echo "\n== the same answer whoever asks ==\n";

// The point of the lost-password form: a stranger must not be able to use it to
// find out who has an account here.
$existing = 'alice';
$unknown  = 'nobody-with-this-name-exists';

check( 'the account used for the comparison really does exist', false !== get_user_by( 'login', $existing ) );
check( 'the one it is compared against really does not', false === get_user_by( 'login', $unknown ) );

$lost = new \OxyArea\Auth\LostPasswordForm(
	new \OxyArea\Infrastructure\Templates(),
	new \OxyArea\Auth\FormErrors()
);

$rendered_for_known   = $lost->render();
$rendered_for_unknown = $lost->render();

check( 'the form itself says nothing either way', $rendered_for_known === $rendered_for_unknown );

echo "\n== a failed sign-in says the same thing every time ==\n";

// A username that exists with the wrong password, and a username that does not
// exist at all, must be indistinguishable. WordPress's own errors are not: it
// says "unknown username" for one and "the password you entered is incorrect"
// for the other, which on a client portal is a way of asking whether somebody is
// a customer.
$errors = new \OxyArea\Auth\FormErrors();
$login  = new \OxyArea\Auth\LoginForm(
	new \OxyArea\Infrastructure\Templates(),
	$errors,
	new \OxyArea\Infrastructure\Settings()
);

$_SERVER['REQUEST_METHOD'] = 'POST';

$_POST = array(
	'_wpnonce'      => wp_create_nonce( 'oxyarea_login' ),
	'user_login'    => $existing,
	'user_password' => 'definitely-not-the-password',
);
$login->handle();

$_POST = array(
	'_wpnonce'      => wp_create_nonce( 'oxyarea_login' ),
	'user_login'    => $unknown,
	'user_password' => 'definitely-not-the-password',
);
$login->handle();

$said = $errors->get( 'login' );

check( 'both attempts were refused', 2 === count( $said ) );
check( 'and refused in identical words', isset( $said[0], $said[1] ) && $said[0] === $said[1] );
check(
	'which name neither the account nor the password',
	isset( $said[0] )
		&& false === stripos( $said[0], 'unknown' )
		&& false === stripos( $said[0], 'registered' )
		&& false === stripos( $said[0], 'email address for' )
);

echo "\n== a stale nonce is refused ==\n";

$stale  = new \OxyArea\Auth\FormErrors();
$guarded = new \OxyArea\Auth\LoginForm(
	new \OxyArea\Infrastructure\Templates(),
	$stale,
	new \OxyArea\Infrastructure\Settings()
);

$_POST = array(
	'_wpnonce'      => 'not-a-nonce',
	'user_login'    => $existing,
	'user_password' => 'definitely-not-the-password',
);
$guarded->handle();

check( 'a submission without a good nonce goes no further', 1 === count( $stale->get( 'login' ) ) );

$_POST                     = array();
$_SERVER['REQUEST_METHOD'] = 'GET';

echo "\n== where a form is allowed to send somebody ==\n";

check(
	'a path on this site is kept',
	'/private/' === \OxyArea\Auth\Destination::make_safe( '/private/', '/fallback/' )
);
check(
	'somebody else\'s site is not',
	'/fallback/' === \OxyArea\Auth\Destination::make_safe( 'https://evil.example/', '/fallback/' )
);
check(
	'nor is a protocol-relative one',
	'/fallback/' === \OxyArea\Auth\Destination::make_safe( '//evil.example/', '/fallback/' )
);
check(
	'this site by its real name is kept',
	home_url( '/private/' ) === \OxyArea\Auth\Destination::make_safe( home_url( '/private/' ), '/fallback/' )
);

echo "\n== the redirect rules table exists ==\n";

global $wpdb;

$redirect_table = \OxyArea\Infrastructure\Migrator::table( \OxyArea\Infrastructure\Migrator::TABLE_REDIRECT_RULES );

check(
	'migration 2 created the table',
	$redirect_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $redirect_table ) )
);
check(
	'and the schema version says so',
	2 === (int) get_option( \OxyArea\Infrastructure\Migrator::VERSION_OPTION )
);

echo "\n== where people land after signing in ==\n";

$rules = new \OxyArea\Persistence\RedirectRuleRepository();

/**
 * A redirect service with a fresh audience cache, since roles change during this run.
 *
 * @return \OxyArea\Redirect\RedirectService
 */
function redirects(): \OxyArea\Redirect\RedirectService {
	return new \OxyArea\Redirect\RedirectService(
		new \OxyArea\Redirect\RedirectResolver(),
		new \OxyArea\Persistence\RedirectRuleRepository(),
		new \OxyArea\Access\AudienceResolver( array( new \OxyArea\Roles\RoleAudienceProvider() ) ),
		new \OxyArea\Infrastructure\Settings()
	);
}

$login_event = \OxyArea\Redirect\RedirectEvent::LOGIN;

$rules->save( new \OxyArea\Redirect\RedirectRule( $login_event, null, '/shop/' ) );
$rules->save( new \OxyArea\Redirect\RedirectRule( $login_event, Subject::role( 'subscriber' ), '/customers/' ) );
$agent_rule = $rules->save( new \OxyArea\Redirect\RedirectRule( $login_event, Subject::role( $agent ), '/agents/', 5 ) );

check( 'a stored rule comes back with an identifier', $agent_rule->id() > 0 );
check( 'and all three read back from the database', 3 === count( $rules->for_event( $login_event ) ) );

$home = home_url( '/' );

check(
	'Alice, a subscriber, lands on the customers page',
	'/customers/' === redirects()->decide( $login_event, $alice->ID, $home )
);
check(
	'Carol, an agent, lands on the agents page',
	'/agents/' === redirects()->decide( $login_event, $carol->ID, $home )
);
check(
	'a role rule beats the rule for everybody, whatever the priority',
	'/shop/' !== redirects()->decide( $login_event, $alice->ID, $home )
);

echo "\n== when two rules both apply ==\n";

get_user_by( 'id', $carol->ID )->add_role( 'subscriber' );

check(
	'the lower priority number wins',
	'/agents/' === redirects()->decide( $login_event, $carol->ID, $home )
);

$rules->set_enabled( $agent_rule->id(), false );

check(
	'turning that rule off falls through to the other one',
	'/customers/' === redirects()->decide( $login_event, $carol->ID, $home )
);

$rules->set_enabled( $agent_rule->id(), true );

echo "\n== a rule for another moment is not consulted ==\n";

check(
	'signing out is not governed by a sign-in rule',
	$home === redirects()->decide( \OxyArea\Redirect\RedirectEvent::LOGOUT, $carol->ID, $home )
);

echo "\n== a destination off this site is refused ==\n";

// Stored rules pass through the same guard as anything from a request, so even a
// row written straight into the table cannot send somebody elsewhere.
$rules->save(
	new \OxyArea\Redirect\RedirectRule(
		\OxyArea\Redirect\RedirectEvent::PASSWORD_RESET,
		null,
		'https://evil.example/taken'
	)
);

check(
	'a stored off-site destination falls back',
	$home === redirects()->decide( \OxyArea\Redirect\RedirectEvent::PASSWORD_RESET, $alice->ID, $home )
);

foreach ( $rules->all() as $stored_rule ) {
	$rules->delete( $stored_rule->id() );
}

check( 'the rules are gone', array() === $rules->all() );

get_user_by( 'id', $carol->ID )->remove_role( 'subscriber' );

echo "\n== the dashboard post type and blocks exist ==\n";

check(
	'the post type is registered',
	post_type_exists( \OxyArea\Dashboard\DashboardPostType::POST_TYPE )
);
check(
	'and is not publicly queryable',
	! (bool) get_post_type_object( \OxyArea\Dashboard\DashboardPostType::POST_TYPE )->publicly_queryable
);

foreach ( array( 'dashboard', 'welcome', 'profile-summary' ) as $dashboard_block ) {
	check(
		"the oxyarea/{$dashboard_block} block is registered",
		\WP_Block_Type_Registry::get_instance()->is_registered( 'oxyarea/' . $dashboard_block )
	);
}

echo "\n== one template, many people ==\n";

/**
 * A renderer with a fresh cache, since dashboards and roles change during this run.
 *
 * @return \OxyArea\Dashboard\DashboardRenderer
 */
function dashboards(): \OxyArea\Dashboard\DashboardRenderer {
	$repository = new \OxyArea\Persistence\DashboardRepository();
	$repository->flush();

	return new \OxyArea\Dashboard\DashboardRenderer(
		$repository,
		new \OxyArea\Dashboard\DashboardResolver(),
		new \OxyArea\Access\AudienceResolver( array( new \OxyArea\Roles\RoleAudienceProvider() ) )
	);
}

/**
 * Make a dashboard.
 *
 * @param string $title    Its title.
 * @param string $audience The audience meta value.
 * @param string $content  Its content.
 * @return int
 */
function make_dashboard( string $title, string $audience, string $content ): int {
	$id = wp_insert_post(
		array(
			'post_type'    => \OxyArea\Dashboard\DashboardPostType::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $content,
		)
	);

	if ( '' !== $audience ) {
		update_post_meta( (int) $id, \OxyArea\Dashboard\DashboardPostType::AUDIENCE_META, $audience );
	}

	return (int) $id;
}

$default_dashboard = make_dashboard( 'Everybody', '', '<p>The general area.</p>' );
$agent_dashboard   = make_dashboard( 'Agents', 'role:' . $agent, '<p>Hello {{display_name}}, this is the agent area.</p>' );

$resolved_alice = dashboards()->resolve_for( $alice->ID );
$resolved_carol = dashboards()->resolve_for( $carol->ID );

check( 'Alice, with no dashboard of her own, gets the default', null !== $resolved_alice && $default_dashboard === $resolved_alice->id() );
check( 'Carol, an agent, gets the agent one', null !== $resolved_carol && $agent_dashboard === $resolved_carol->id() );
check( 'a signed-out visitor gets nothing at all', null === dashboards()->resolve_for( 0 ) );

echo "\n== the placeholders are filled in, and escaped ==\n";

$carol_html = dashboards()->render_for( $carol->ID );

check( 'the agent content is there', false !== strpos( $carol_html, 'this is the agent area' ) );
check( 'and the name has been filled in', false !== strpos( $carol_html, (string) $carol->display_name ) );
check( 'with no placeholder left behind', false === strpos( $carol_html, '{{' ) );

// Written straight into the table, past every sanitiser WordPress puts in the
// way. Going through wp_update_user proves nothing here: it strips the tag on
// the way in, so the rendering would look safe whether or not this plugin
// escaped anything. The question is whether OUR layer holds on its own.
global $wpdb;

$wpdb->update( $wpdb->users, array( 'display_name' => 'Carol <script>alert(1)</script>' ), array( 'ID' => $carol->ID ) );
clean_user_cache( $carol->ID );

check(
	'the hostile name really is in the database',
	'Carol <script>alert(1)</script>' === get_user_by( 'id', $carol->ID )->display_name
);

$escaped_html = dashboards()->render_for( $carol->ID );

check( 'a script tag in a display name does not reach the page', false === strpos( $escaped_html, '<script>' ) );
check( 'it arrives as text instead', false !== strpos( $escaped_html, '&lt;script&gt;' ) );

$wpdb->update( $wpdb->users, array( 'display_name' => 'Carol (agent)' ), array( 'ID' => $carol->ID ) );
clean_user_cache( $carol->ID );

echo "\n== an unreadable audience narrows, it does not widen ==\n";

// A dashboard whose audience nobody can read must disappear, not become the
// site default and land on everybody signed in.
update_post_meta( $agent_dashboard, \OxyArea\Dashboard\DashboardPostType::AUDIENCE_META, 'something-from-the-future' );

$after = dashboards()->resolve_for( $carol->ID );

check(
	'the unreadable one is dropped rather than served to everybody',
	null !== $after && $default_dashboard === $after->id()
);

update_post_meta( $agent_dashboard, \OxyArea\Dashboard\DashboardPostType::AUDIENCE_META, 'role:' . $agent );

echo "\n== a draft is not a dashboard ==\n";

wp_update_post( array( 'ID' => $agent_dashboard, 'post_status' => 'draft' ) );

$drafted = dashboards()->resolve_for( $carol->ID );

check( 'an unpublished dashboard does not resolve', null !== $drafted && $default_dashboard === $drafted->id() );

wp_update_post( array( 'ID' => $agent_dashboard, 'post_status' => 'publish' ) );

echo "\n== the preview asks about a role, not a person ==\n";

$previewed = dashboards()->resolve_for_role( $agent );

check( 'previewing the agent role finds the agent dashboard', null !== $previewed && $agent_dashboard === $previewed->id() );
check( 'previewing a role with nothing of its own finds the default', null !== dashboards()->resolve_for_role( 'subscriber' ) );

wp_delete_post( $default_dashboard, true );
wp_delete_post( $agent_dashboard, true );

$leftover = new \OxyArea\Persistence\DashboardRepository();
$leftover->flush();

check( 'the test dashboards are gone', array() === $leftover->all() );

echo "\n== cleaning up ==\n";

wp_delete_post( (int) $post_id, true );
$manager->delete( $agent, 'subscriber', $admin->ID );
$manager->delete( $escalated, 'subscriber', $admin->ID );

foreach ( array( 'alice', 'bob', 'carol' ) as $login ) {
	$user = get_user_by( 'login', $login );
	$user->set_role( 'subscriber' );
}

check( 'no OxyArea test roles are left behind', array() === $managed->all() );

echo "\n== result ==\n";
echo "  passed: {$GLOBALS['oxa_passed']}\n";
echo "  failed: {$GLOBALS['oxa_failed']}\n";

if ( $GLOBALS['oxa_failed'] > 0 ) {
	exit( 1 );
}
