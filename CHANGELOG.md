# Changelog

All notable changes to OxyArea are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project follows
[semantic versioning](https://semver.org/).

## [Unreleased]

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

### Notes

- Nothing is released yet. Version 0.1.0 is the working version, not a tag.

[Unreleased]: https://github.com/MorfeoIT/oxyarea
