<?php
/**
 * Fills the test bed with something worth photographing.
 *
 * The plugin directory shows these screenshots to somebody deciding whether to
 * install. Empty screens showing an empty plugin persuade nobody, and worse,
 * they are honest about nothing: what the product does is visible only when
 * there are roles, rules and a dashboard in front of you.
 *
 * OXYAREA_SEED=1 builds it. Without, it takes it all out again, so the flow
 * tests go back to a known state.
 *
 * No declare(strict_types=1): wp eval-file evaluates the file with a leading
 * `?>`, so nothing here is ever the first statement of a script.
 *
 * @package OxyArea
 */

use OxyArea\Access\Assignment;
use OxyArea\Access\ProtectedResource;
use OxyArea\Access\Subject;
use OxyArea\Dashboard\DashboardPostType;
use OxyArea\Infrastructure\Settings;
use OxyArea\Persistence\AssignmentRepository;
use OxyArea\Persistence\DashboardRepository;
use OxyArea\Persistence\RedirectRuleRepository;
use OxyArea\Redirect\RedirectEvent;
use OxyArea\Redirect\RedirectRule;
use OxyArea\Roles\ManagedRoles;
use OxyArea\Roles\RoleManager;

$oxyarea_marker     = '_oxyarea_screenshot_seed';
$oxyarea_settings   = new Settings();
$oxyarea_managed    = new ManagedRoles();
$oxyarea_manager    = new RoleManager( $oxyarea_managed );
$oxyarea_rules      = new RedirectRuleRepository();
$oxyarea_dashboards = new DashboardRepository();
$oxyarea_access     = new AssignmentRepository();
$oxyarea_admin      = get_user_by( 'login', 'oxysoft' );
$oxyarea_alice      = get_user_by( 'login', 'alice' );
$oxyarea_carol      = get_user_by( 'login', 'carol' );

if ( '1' !== (string) getenv( 'OXYAREA_SEED' ) ) {
	$oxyarea_ours = get_posts(
		array(
			'post_type'   => 'any',
			'post_status' => 'any',
			'numberposts' => 100,
			'fields'      => 'ids',
			'meta_key'    => $oxyarea_marker,
			'meta_value'  => '1',
		)
	);

	foreach ( (array) $oxyarea_ours as $oxyarea_id ) {
		$oxyarea_access->replace_for_resource( ProtectedResource::post( (int) $oxyarea_id ), array() );
		wp_delete_post( (int) $oxyarea_id, true );
	}

	foreach ( $oxyarea_rules->all() as $oxyarea_rule ) {
		$oxyarea_rules->delete( $oxyarea_rule->id() );
	}

	$oxyarea_alice->set_role( 'subscriber' );
	$oxyarea_carol->set_role( 'subscriber' );

	foreach ( array( 'customer', 'agent' ) as $oxyarea_slug ) {
		if ( $oxyarea_managed->contains( $oxyarea_slug ) ) {
			$oxyarea_manager->delete( $oxyarea_slug, 'subscriber', (int) $oxyarea_admin->ID );
		}
	}

	$oxyarea_settings->update( array( 'login_page' => 0 ) );
	delete_option( 'oxyarea_setup_done' );
	$oxyarea_dashboards->flush();

	echo "cleared\n";

	return;
}

// The preview fills the placeholders with the administrator's own details and
// says so. An administrator with no first name makes "Welcome back, ." — correct
// behaviour that reads like a defect in a screenshot.
wp_update_user(
	array(
		'ID'           => (int) $oxyarea_admin->ID,
		'first_name'   => 'Marco',
		'last_name'    => 'Bianchi',
		'display_name' => 'Marco Bianchi',
	)
);

// The editor's welcome guide is shown once per person and lands in the middle of
// any screenshot of the editor. Turning it off for this account is a test-bed
// preference, not a plugin behaviour.
update_user_meta(
	(int) $oxyarea_admin->ID,
	'wp_persisted_preferences',
	array(
		'_modified'      => gmdate( 'c' ),
		'core/edit-post' => array( 'welcomeGuide' => false ),
		'core'           => array( 'welcomeGuide' => false ),
	)
);

// --- roles ------------------------------------------------------------------

foreach ( array( 'Customer' => 'customer', 'Agent' => 'agent' ) as $oxyarea_name => $oxyarea_slug ) {
	if ( null === get_role( $oxyarea_slug ) ) {
		$oxyarea_manager->create( $oxyarea_name, $oxyarea_slug, array( 'read' ), (int) $oxyarea_admin->ID );
	}
}

$oxyarea_manager->assign_user( (int) $oxyarea_alice->ID, 'customer', (int) $oxyarea_admin->ID );
$oxyarea_manager->assign_user( (int) $oxyarea_carol->ID, 'agent', (int) $oxyarea_admin->ID );

// --- pages ------------------------------------------------------------------

/**
 * Make a page and mark it as ours.
 *
 * @param string $title   Its title.
 * @param string $slug    Its slug.
 * @param string $content Its content.
 * @param string $marker  The meta key marking it as seeded.
 * @return int
 */
function oxyarea_seed_page( string $title, string $slug, string $content, string $marker ): int {
	$existing = get_page_by_path( $slug );

	if ( null !== $existing ) {
		return (int) $existing->ID;
	}

	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
		)
	);

	update_post_meta( (int) $id, $marker, '1' );

	return (int) $id;
}

$oxyarea_sign_in = oxyarea_seed_page(
	'Sign in',
	'sign-in',
	"<!-- wp:oxyarea/login /-->\n<!-- wp:oxyarea/lost-password /-->\n<!-- wp:oxyarea/reset-password /-->",
	$oxyarea_marker
);

$oxyarea_area = oxyarea_seed_page(
	'My area',
	'my-area',
	"<!-- wp:oxyarea/dashboard /-->",
	$oxyarea_marker
);

$oxyarea_settings->update( array( 'login_page' => $oxyarea_sign_in ) );

// --- dashboards -------------------------------------------------------------

/**
 * Make a dashboard and mark it as ours.
 *
 * @param string $title   Its title.
 * @param string $role    Who it is for.
 * @param string $content Its content.
 * @param string $marker  The meta key marking it as seeded.
 * @return int
 */
function oxyarea_seed_dashboard( string $title, string $role, string $content, string $marker ): int {
	$id = wp_insert_post(
		array(
			'post_type'    => DashboardPostType::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $content,
		)
	);

	update_post_meta( (int) $id, DashboardPostType::AUDIENCE_META, 'role:' . $role );
	update_post_meta( (int) $id, $marker, '1' );

	return (int) $id;
}

oxyarea_seed_dashboard(
	'Customer area',
	'customer',
	"<!-- wp:oxyarea/welcome {\"text\":\"Welcome back, {{first_name}}.\"} /-->\n"
	. "<!-- wp:paragraph --><p>Your documents and your account, in one place.</p><!-- /wp:paragraph -->\n"
	. "<!-- wp:oxyarea/profile-summary /-->\n"
	. "<!-- wp:oxyarea/logout /-->",
	$oxyarea_marker
);

oxyarea_seed_dashboard(
	'Agent area',
	'agent',
	"<!-- wp:oxyarea/welcome {\"text\":\"Hello {{display_name}} — the agent desk.\"} /-->\n"
	. "<!-- wp:oxyarea/profile-summary /-->",
	$oxyarea_marker
);

// --- redirect rules ---------------------------------------------------------

$oxyarea_rules->save( new RedirectRule( RedirectEvent::LOGIN, null, '/', 50 ) );
$oxyarea_rules->save( new RedirectRule( RedirectEvent::LOGIN, Subject::role( 'customer' ), '/my-area/', 10 ) );
$oxyarea_rules->save( new RedirectRule( RedirectEvent::LOGIN, Subject::role( 'agent' ), '/my-area/', 5 ) );
$oxyarea_rules->save( new RedirectRule( RedirectEvent::LOGOUT, null, '/sign-in/', 10 ) );

// --- a restricted post ------------------------------------------------------

$oxyarea_contract = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Quarterly contract 2026',
		'post_name'    => 'quarterly-contract-2026',
		'post_content' => 'The terms agreed for this quarter.',
	)
);

update_post_meta( (int) $oxyarea_contract, $oxyarea_marker, '1' );

$oxyarea_access->replace_for_resource(
	ProtectedResource::post( (int) $oxyarea_contract ),
	array( new Assignment( Subject::role( 'customer' ) ) )
);

// The wizard's invitation is for a plugin nobody has configured, and these
// screenshots show one somebody has. Marking it done keeps the notice out of
// every admin shot.
update_option( 'oxyarea_setup_done', gmdate( 'c' ), false );

$oxyarea_dashboards->flush();

echo 'sign_in=' . $oxyarea_sign_in . ' area=' . $oxyarea_area . ' contract=' . (int) $oxyarea_contract . "\n";
