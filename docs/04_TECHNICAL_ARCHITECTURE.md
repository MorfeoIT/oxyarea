# OxyArea — Technical Architecture

## Baseline

Recommended:
- PHP 8.1+
- current supported WordPress versions
- Composer PSR-4
- WordPress Coding Standards
- PHPUnit + WordPress test suite
- browser E2E tests
- PHPStan/Psalm
- ESLint for JS
- `block.json` for Gutenberg blocks

## FREE structure

```text
oxyarea/
├── oxyarea.php
├── readme.txt
├── uninstall.php
├── composer.json
├── package.json
├── assets/
├── languages/
├── src/
│   ├── Plugin.php
│   ├── Access/
│   ├── Auth/
│   ├── Roles/
│   ├── Redirect/
│   ├── Dashboard/
│   ├── Content/
│   ├── Rest/
│   ├── Privacy/
│   ├── Admin/
│   └── Infrastructure/
├── blocks/
├── templates/
└── tests/
```

Namespace:
```php
namespace OxyArea;
```

## PRO structure

```text
oxyarea-pro/
├── oxyarea-pro.php
├── composer.json
├── src/
│   ├── Plugin.php
│   ├── License/
│   ├── UserArea/
│   ├── Groups/
│   ├── Files/
│   ├── Conditions/
│   ├── Notifications/
│   ├── Audit/
│   ├── WooCommerce/
│   ├── Elementor/
│   └── Agency/
└── tests/
```

Namespace:
```php
namespace OxyAreaPro;
```

## Dependency direction

Correct:

```text
OxyArea PRO
    ↓
OxyArea FREE/Core
    ↓
WordPress
```

Never:

```text
OxyArea FREE → hard dependency on PRO
```

FREE must remain fully operational if PRO is absent/deactivated.

## Core interfaces owned by FREE

Examples:

```php
interface AccessResolverInterface {
    public function can_view(int $user_id, ResourceInterface $resource): bool;
}
```

```php
interface AudienceProviderInterface {
    public function get_subjects(int $user_id): array;
}
```

```php
interface DashboardWidgetInterface {
    public function get_name(): string;
    public function render(array $context): string;
}
```

PRO extends behavior by registering additional providers/widgets.

## Central authorization

All access decisions must go through one server-side access service.

Do not duplicate authorization logic in:
- blocks;
- templates;
- REST routes;
- AJAX;
- download controllers.

UI visibility is not authorization.

## Suggested data

Native WordPress:
- dashboard/private-item CPTs where useful.

Custom tables for relational/high-volume data:
- `{$wpdb->prefix}oxyarea_assignments`
- `{$wpdb->prefix}oxyarea_redirect_rules`
- PRO:
  - `{$wpdb->prefix}oxyarea_groups`
  - `{$wpdb->prefix}oxyarea_group_members`
  - `{$wpdb->prefix}oxyarea_files`
  - `{$wpdb->prefix}oxyarea_activity`

Do not store large relationship sets in serialized options.

## Migrations

- schema version option;
- idempotent migrations;
- no heavy migration per request;
- recoverable failure;
- backups/documentation for destructive migrations.

## REST

Base:
```text
/wp-json/oxyarea/v1/
```

Every route:
- method definition;
- schema;
- validation;
- sanitization;
- `permission_callback`;
- object-level authorization.

## Cache

Never public-cache user-private output.

Authorization-aware keys and invalidation required.

## Extension hooks

Examples:
- `oxyarea_access_decision`
- `oxyarea_dashboard_resolved`
- `oxyarea_register_dashboard_widgets`
- `oxyarea_redirect_destination`
- `oxyarea_resource_audience`
- PRO may add further hooks.

## Branding abstraction

Even after name lock, keep:
- display name;
- URLs;
- vendor;
- support links;
centralized in configuration/constants rather than scattered literals.
