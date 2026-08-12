# Changelog

All notable changes to OxyArea are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project follows
[semantic versioning](https://semver.org/).

## [Unreleased]

### Added — Sprint B, authorisation and roles

- **The access resolver.** One class decides who may see what, and it contains no
  WordPress call at all: the rules, the subjects, whether the user administers
  the plugin and what time it is all arrive through the constructor. That is what
  makes it possible to test every branch of the authorisation logic rather than
  assert that it looks right.
- Its rules, in order: somebody who administers OxyArea may read what OxyArea
  protects; a rule outside its validity period does not count; an explicit deny
  beats every allow; one matching subject out of several is enough; anything else
  is a refusal, including a resource with no rules at all.
- `Assignment`, the stored rule: a subject, an effect, a priority and an optional
  window. A window that ends before it starts never applies, because the safe
  reading of corrupt data in an access rule is that it grants nothing.
- `AudienceResolver`, which asks every registered provider what a user counts as
  and merges the answers, collapsing duplicates and caching per request.
- `RoleAudienceProvider`: signed in or not, plus every role held. A signed-out
  visitor presents "anonymous" and nothing else — it is a distinct audience, not
  a synonym for everybody.
- `HookedAccessResolver`, a thin wrapper carrying the `oxyarea_access_decision`
  filter, so the resolver itself stays free of WordPress. A filter returning
  anything that is not a Decision is ignored rather than trusted.
- `AssignmentRepository`, reading and writing the assignments table with prepared
  values and a per-request cache, skipping rows it cannot make sense of and
  refusing to trust the shape of what a shared object cache hands back.
- **The role manager**, with the refusals that matter: nobody edits the
  administrator role; nobody deletes a role OxyArea did not create; nobody grants
  a capability they do not hold themselves; nobody edits themselves out of the
  role screen; and capabilities outside the catalogue are left untouched, so a
  role carrying WooCommerce's capabilities still carries them afterwards.
- `CapabilityCatalogue`, which separates everyday capabilities from the ones that
  hand over the site, and treats any capability it has never heard of as
  dangerous.
- The Roles admin screen: list, create, clone the capabilities of an existing
  role, edit capabilities by group, assign a user, and delete with reassignment.
  Forms post to admin-post.php, so nothing mutates on a GET.
- `Container::get_typed()`, so the object graph is checked by static analysis
  rather than hoped about.
- 54 further unit tests, including the case that found a real hole: the resolver
  trusted its manager check for signed-out visitors, which would have handed
  every protected resource to the open internet had an add-on shipped a careless
  implementation. The guard now lives in the resolver.

### Added — Sprint A, foundation

- Plugin bootstrap: headers, constants, PSR-4 autoloader and a requirements
  guard that refuses to activate on PHP below 8.1 or WordPress below 6.5 rather
  than half-starting.
- A service container whose only purpose is to let OxyArea PRO and third-party
  add-ons replace a service instead of editing a core file, through the
  `oxyarea_register_services` action.
- Schema migrations: numbered, ordered, idempotent, resumable, and run both at
  activation and on the first request after an upgrade, because a plugin updated
  over FTP never fires an activation hook.
- The `oxyarea_assignments` table: which subjects may see which resources.
- The plugin's administrative capabilities, granted to administrators.
- A single settings option with a pure, testable sanitiser that discards keys it
  does not recognise.
- Activation, including per-site setup across a multisite network.
- Deactivation that removes nothing at all.
- Uninstall that removes nothing unless the administrator turned on
  `delete_data_on_uninstall` first, and is multisite-aware when they have.
- Suggested privacy policy text. No exporter or eraser yet: the free plugin
  stores no personal data, and shipping empty ones would tell an administrator
  their export was complete when it was not.
- The access contracts — `AccessResolverInterface`, `AudienceProviderInterface`,
  `Subject`, `ProtectedResource`, `Decision` — and the dashboard widget contract.
  `Decision` carries the reasoning as well as the verdict, so that the permission
  inspector can later show the decision the site actually made rather than a
  second implementation of it.
- Centralised branding, so a pre-launch rename stays cheap and the Agency tier
  has one place to white-label.
- Toolchain: Composer, PHPCS against WordPress standards, PHPStan at level 8,
  PHPUnit with unit, integration and security suites.
- 33 unit tests over the container, decisions, subjects, resources and settings
  sanitisation.
- Fixed before anything else ran: the migration list used `fn (): void`, which
  cannot compile, and would have white-screened the plugin on activation.

### Notes

- Nothing is released yet. Version 0.1.0 is the working version, not a tag.

[Unreleased]: https://github.com/MorfeoIT/oxyarea
