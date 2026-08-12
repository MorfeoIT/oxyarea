# OxyArea

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
  Dashboard/             the widget contract
  Infrastructure/        container, settings, migrations, activation, branding
  Persistence/           assignments and redirect rules, in the database
  Redirect/              where people go next — framework-free, unit-testable
  Privacy/               suggested privacy policy text
  Roles/                 role manager, capability catalogue, audience provider
tests/
  Unit/                  no WordPress, no database, runs anywhere
  Support/               test doubles: in-memory rules, stub audience, fixed clock
  Integration/           needs a WordPress test install
  Security/              the Alice/Bob isolation suite
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
composer test       # unit suite alone
composer lint:fix   # phpcbf
```

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
```

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
It is not PHPUnit; it is what covers those classes until the integration suite
exists.

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

Verified on WordPress 7.0.3: the plugin activates without a single PHP notice,
**Plugin Check reports no errors**, 157 unit tests pass, the 73 checks in
`tests/manual/smoke.php` pass inside a real installation, and the 21 checks in
the two flow scripts pass over HTTP.

Next: Sprint E — dashboards. The dashboard model, resolution by role, the blocks
and the preview.

Still outstanding: the PHPUnit `integration` and `security` suites are empty. The
manual scripts cover the same ground for now, but they are scripts with a pass
counter, not test suites, and they do not run in CI.
