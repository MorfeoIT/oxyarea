# OxyArea

[![CI](https://github.com/MorfeoIT/oxyarea/actions/workflows/ci.yml/badge.svg)](https://github.com/MorfeoIT/oxyarea/actions/workflows/ci.yml)

**OxyArea – Private Client Area & User Portal** — a private-area and client-portal
framework for WordPress. Part of the [OxyWP](https://oxywp.com/) family, by
Oxysoft.

Product specification lives in `docs/`. This file is for whoever has to build it.

## What this repository is

The **free** plugin, the one submitted to WordPress.org as `oxyarea`. OxyArea PRO
is a separate repository and a separate package; nothing here is PRO code waiting
for a licence key, and nothing here downloads or installs PRO.

## Requirements

| | |
|---|---|
| PHP | 8.1+ |
| WordPress | 6.5+ |
| Runtime dependencies | none |

Composer is a development tool here. The plugin autoloads `src/` with its own
PSR-4 loader, so no `vendor/` directory is shipped.

## Layout

```
oxyarea.php              headers, constants, autoloader, requirements guard, boot
uninstall.php            runs outside the plugin; destroys nothing by default
src/
  Plugin.php             builds the object graph, fires oxyarea_register_services
  Access/                who may see what — framework-free, unit-testable
  Admin/                 the admin menu and its screens
  Content/               who may read a page, and where it must not be listed
  Dashboard/             which dashboard, and the placeholders in it
  Infrastructure/        container, settings, migrations, activation, branding
  Persistence/           assignments and redirect rules, in the database
  Redirect/              where people go next — framework-free, unit-testable
  Privacy/               suggested privacy policy text
  Roles/                 role manager, capability catalogue, audience provider
tests/
  Unit/                  no WordPress, no database, runs anywhere
  Support/               test doubles, and the cast every WordPress test uses
  Integration/           the harness itself, and the role manager's refusals
  Security/              content restriction and account privacy
.wordpress-org/          directory assets: icons, banners, screenshots
```

## How a decision is made

```
AccessResolver::explain( user, resource )
  │
  ├─ administers OxyArea?          → allow, and say so
  ├─ AudienceResolver              → what the user counts as
  │    └─ RoleAudienceProvider     → anonymous | authenticated + roles
  │       (PRO adds: the user, their companies, their capabilities)
  ├─ AssignmentRepository          → the rules on this resource
  │    └─ drop the ones outside their window
  ├─ any matching deny             → refuse
  ├─ any matching allow            → permit
  └─ otherwise                     → refuse
```

`Decision` carries the reasoning as well as the verdict, and `can_view()` is
`explain()->is_allowed()`. There is no second code path, which is what will let
PRO's permission inspector show the decision the site actually made.

## Commands

```bash
composer install
composer check      # phpcs, then phpstan, then the unit suite
composer test       # unit suite alone: no WordPress, no database, anywhere
composer lint:fix   # phpcbf
```

The other two suites need a WordPress and a database of their own. Build both,
then point PHPUnit at what the script wrote:

```bash
export WP_PHPUNIT__TESTS_CONFIG="$(scripts/wordpress-test-env.sh)"
composer test:wordpress
```

`WP_DB_NAME`, `WP_DB_USER`, `WP_DB_PASSWORD` and `WP_DB_HOST` say where. Without
`WP_PHPUNIT__TESTS_CONFIG` the bootstrap loads no WordPress at all, which is why
`composer test` works on a machine that has no database.

CI runs all of it on every push: standards and PHPStan, the unit suite on PHP
8.1 through 8.4, the WordPress suites on 8.1 and 8.3 against MySQL 8, and a job
that builds the distributable with `git archive` and inspects it. That last one
is there because `.gitattributes` is easy to forget when a directory is added,
and the plugin directory's reviewers are not. It caught a missing `LICENSE` on
its first run.

## The extension API

`OxyArea\API_VERSION` is the version of the contract add-ons are invited to
rely on, and it moves independently of `OxyArea\VERSION`. An add-on requires an
API version, never a release.

```php
if ( ! defined( 'OxyArea\API_VERSION' ) || version_compare( OxyArea\API_VERSION, '1.0', '<' ) ) {
    return; // Do nothing at all, and say why in an admin notice.
}
```

What the contract covers:

| | |
|---|---|
| Services | `oxyarea_register_services` — add to the container before anything is built |
| Audiences | `oxyarea_audience_providers` — teach the resolver what a user counts as |
| Decisions | `oxyarea_access_decision` — observe or override a verdict, with its reasoning |
| Subjects | `oxyarea_subject_decode` / `_encode` / `_label` — round-trip and name a kind of subject this plugin has never heard of |
| Screens | `oxyarea_subject_controls` to draw your own control on the three audience screens, `oxyarea_subject_values` to contribute what it collected |
| Conditions | `oxyarea_conditions` — contribute a `ConditionInterface`, and a rule can carry "only when…" |
| Widgets | `oxyarea_dashboard_widgets` — contribute a `DashboardWidgetInterface`, placed with `[oxyarea_widget name="…"]` |
| Interfaces | `Access\*Interface`, `Dashboard\*Interface`, `Redirect\RuleRepositoryInterface`, `Infrastructure\ClockInterface` |
| Windows | `Assignment::starts_at()` / `ends_at()` — a rule that begins or stops on a date, stored and enforced |
| Events | `oxyarea_init`, `oxyarea_role_*`, `oxyarea_user_role_assigned`, `oxyarea_password_reset*`, `oxyarea_content_refused`, `oxyarea_dashboard_rendered`, `oxyarea_*_destination`, `oxyarea_unauthorised_behaviour`, `oxyarea_brand_*` |

The major rises when something there is removed or changes meaning, so an add-on
built against the old contract refuses to load rather than failing halfway
through a request. The minor rises when something is added.

`Conditions\` is the seam for tests a rule carries. A condition is **not** a
subject: a subject says who somebody is and decides how specific the rule is; a
condition says what this request is like and has no specificity at all. So a rule
applies when its subject matches **and** every condition holds, and the ordering
afterwards is unchanged.

The free plugin ships **no condition types**, deliberately: every one anybody has
asked for — a first sign-in, the page they wanted, a value on their account, an
order in a shop — belongs to an add-on. And a condition this site cannot judge
makes its rule **not apply**, rather than apply to everybody: deactivating the
add-on that provided "only on their first sign-in" must not start sending every
customer where one of them was meant to go.

`Access\SubjectCodec` is the one parser for the strings a form field carries —
`authenticated`, `role:editor`, and whatever an add-on adds. The property to
preserve when extending it is the round trip: a subject that encodes to a value
which does not decode back to it appears unticked on the screen where it is
stored, and is lost the next time somebody presses Update.

## The rules this codebase is held to

1. **One access resolver.** Every question about who may see what goes through
   `AccessResolverInterface`. Blocks, templates, REST routes, AJAX handlers and
   download controllers ask it; none of them re-implements it.
2. **Deny by default.** A resolver that cannot answer says no.
3. **UI visibility is not authorisation.** A hidden menu is a courtesy.
4. **Nonces are not authorisation either.** They stop cross-site requests. Pair
   every one with a capability check and an object check.
5. **Free stays whole.** Deactivating PRO must leave a working private area.
6. **Nothing is deleted that was not asked to be deleted.** Deactivation removes
   nothing; uninstall removes nothing unless the setting is on.
7. **No phoning home.** The free plugin contacts nothing.
8. **Security is not a feature tier.** Fixes ship to every edition at once.

## Test fixtures

Every integration and security test builds the same cast, because the release
gate is written in terms of them:

| | |
|---|---|
| Admin | administrator |
| Alice | role Customer, company ACME |
| Bob | role Customer, company Beta |
| Carol | role Agent |
| Dave | signed out |

`tests/Support/CastTestCase.php` builds them, so a test reads as the thing it
checks rather than as ten lines of setup.

Nothing ships if Alice can reach Bob's data by guessing a URL, changing an ID, or
through REST, AJAX, search, a feed, the sitemap or a static file URL.

## The test bed

`https://test.44123.it/oxyarea`, on the Hestia server. A clean WordPress with no
theme framework and no other plugins except `plugin-check`: a test bed exists to
make it obvious which plugin caused what, and every extra plugin on it is a
suspect. WP_DEBUG on, logging to file. The cast — alice, bob, carol — already
exists as users.

```bash
scripts/testbed-setup.sh           # once, as root: database, WordPress, plugin-check, users
scripts/testbed-deploy.sh          # each time, as root: unpack /tmp/oxyarea.tar and activate
scripts/testbed-login-flow.sh      # signs in over HTTP, the way a person would
scripts/testbed-redirect-flow.sh   # checks where the browser is actually sent
scripts/testbed-dashboard-flow.sh  # checks a dashboard reaches the right browser
scripts/testbed-restriction-flow.sh # the release blockers: search, feed, REST, sitemap
scripts/testbed-reset-flow.sh       # forgotten password, from the email to signing in
scripts/testbed-screenshot-seed.php # fills the bed with roles, rules and dashboards
scripts/screenshots.mjs             # photographs seven screens as three different people
```

The screenshots need `npm install puppeteer` somewhere and three credentials in
the environment; they are a release asset, so nothing about them is in
`composer.json` or shipped:

```bash
OXYAREA_BASIC=user:pass OXYAREA_ADMIN=user:pass OXYAREA_CUSTOMER=user:pass   node scripts/screenshots.mjs .wordpress-org
```

The reset flow needs `scripts/testbed-mail-capture.php` installed as an
mu-plugin, which writes outgoing mail to `wp-content/mail.log` instead of sending
it. Not a shortcut: the fixtures have `@example.test` addresses that resolve to
nothing by design, so no mail server could deliver to them. What needs proving is
the link in the message and what happens when somebody follows it.

Build the package the way the directory will see it, `export-ignore` and all:

```bash
git archive --format=tar --prefix=oxyarea/ -o /tmp/oxyarea.tar HEAD
```

Then, on the server:

```bash
wp plugin check oxyarea
wp eval-file tests/manual/smoke.php
```

`tests/manual/smoke.php` exercises what only exists inside WordPress — the role
manager's refusals, the escalation guard, the assignment repository, the resolver
wired to real roles, and the authentication forms — and cleans up after itself.
It is not PHPUnit. It predates the integration suite and overlaps it; what it
still adds is that it runs inside the *deployed* plugin on the test bed, against
the package as unpacked, rather than against the working copy.

`scripts/testbed-login-flow.sh` is the only check that goes through HTTP: a real
page, a real form, a real nonce, a real session cookie. Everything else proves
the pieces work; this proves the flow does. It is what would notice a form that
renders perfectly and submits into nothing.

**Never point the WordPress PHPUnit test library at a site's database.** It drops
and recreates every table on each run. It needs a database of its own.

## Status

Sprint A complete: bootstrap, container, schema migrations, capabilities,
settings, activation/deactivation, uninstall, privacy text, access contracts.

Sprint B complete: the access resolver, the audience model, the assignment
repository, the role manager with its refusals, the capability catalogue and the
Roles screen.

Sprint C complete: frontend sign in, sign out, forgotten password, set a new
password and profile, as five blocks and five shortcodes, with the open-redirect
guard and the account-enumeration fixes behind them.

Sprint D complete: the redirect engine. Rules by role for signing in, signing
out, registering and resetting a password; a fallback per event; and an ordering
that is total rather than usually right.

Sprint E complete: dashboards. One template per role, built in the block editor,
with placeholders, three blocks of our own, and a preview that answers "what does
this role get" without signing in as anybody.

Sprint F complete: content restriction. A page protected by role, refused when
opened and absent from search, feeds, the REST API, the sitemap and neighbour
links.

Sprint G complete: the release candidate. Settings, export and import, a setup
wizard, and 274 translatable strings in `languages/oxyarea.pot`.

Verified on WordPress 7.0.3: the plugin activates without a single PHP notice,
**Plugin Check reports no errors and one warning**, 225 unit tests pass, 49
WordPress tests pass in the `integration` and `security` suites, the 109 checks
in `tests/manual/smoke.php` pass inside a real installation, and the 70 checks in
the five flow scripts pass over HTTP — including the release blockers asked as a
stranger and then as each of two customers, and the forgotten-password flow from
the email to signing in with the new password.

**Read [`docs/SUBMISSION_READINESS.md`](docs/SUBMISSION_READINESS.md) before
submitting.** One thing is outstanding and it is not code: the Name Lock
checklist has a manual trademark similarity step nobody has done.

PRO begins only after that, as the master prompt requires.
