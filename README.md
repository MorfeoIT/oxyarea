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
  Dashboard/             the widget contract
  Infrastructure/        container, settings, migrations, activation, branding
  Privacy/               suggested privacy policy text
  Roles/                 the plugin's own administrative capabilities
tests/
  Unit/                  no WordPress, no database, runs anywhere
  Integration/           needs a WordPress test install
  Security/              the Alice/Bob isolation suite
.wordpress-org/          directory assets: icons, banners, screenshots
```

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

## Status

Sprint A complete: bootstrap, container, schema migrations, capabilities,
settings, activation/deactivation, uninstall, privacy text, access contracts and
the unit suite around them.

Next: Sprint B — the access resolver itself, the role manager and its screens.
