<?php
/**
 * Adds or clears a sign-in redirect rule, for the flow test.
 *
 * Exists as a file rather than as `wp eval '...'` because a namespaced class
 * name has to survive bash, ssh, sudo and WP-CLI's own eval, and it does not.
 *
 * Run with: OXYAREA_ROLE=... OXYAREA_DEST=... wp eval-file testbed-redirect-rule.php
 * With no OXYAREA_DEST, every rule is removed instead.
 *
 * No declare(strict_types=1) here: wp eval-file wraps the file in an eval(),
 * so nothing can be the very first statement of a script.
 *
 * @package OxyArea
 */

use OxyArea\Access\Subject;
use OxyArea\Persistence\RedirectRuleRepository;
use OxyArea\Redirect\RedirectEvent;
use OxyArea\Redirect\RedirectRule;

$oxyarea_rules       = new RedirectRuleRepository();
$oxyarea_destination = (string) getenv( 'OXYAREA_DEST' );
$oxyarea_role        = (string) getenv( 'OXYAREA_ROLE' );

if ( '' === $oxyarea_destination ) {
	foreach ( $oxyarea_rules->all() as $oxyarea_rule ) {
		$oxyarea_rules->delete( $oxyarea_rule->id() );
	}

	echo "cleared\n";

	return;
}

$oxyarea_rules->save(
	new RedirectRule(
		RedirectEvent::LOGIN,
		'' === $oxyarea_role ? null : Subject::role( $oxyarea_role ),
		$oxyarea_destination
	)
);

echo count( $oxyarea_rules->all() ) . "\n";
