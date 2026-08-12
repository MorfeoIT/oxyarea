# OxyArea — Development Package for Coding Agent

**Product:** OxyArea  
**Display name:** OxyArea – Private Client Area & User Portal  
**Expected WordPress.org slug:** `oxyarea`  
**Vendor:** Oxysoft Soluzioni Informatiche  
**Product family/site:** OxyWP — `oxywp.com`  
**Prepared:** 2026-08-11  
**Status:** Product/technical specification ready for development.

## Product promise

> Create a complete private client area in WordPress without assembling multiple plugins.

OxyArea is a private-area / client-portal framework. It is **not** primarily a membership, LMS, community, payment or subscription plugin.

## Core use cases

- Frontend login, logout, password reset and profile.
- WordPress role management.
- Post-login redirects by role.
- Private dashboard(s) by role.
- Content restriction by authentication/role.
- User-specific private areas.
- Companies/groups.
- Secure private files.
- Conditional widgets/blocks.
- WooCommerce integration.
- Developer extension API.

## Architecture of the commercial product

Two separate plugins:

### OxyArea FREE
WordPress.org plugin:
- folder: `oxyarea`
- main file: `oxyarea.php`
- text domain: `oxyarea`
- namespace: `OxyArea`
- REST namespace: `oxyarea/v1`

### OxyArea PRO
Commercial extension installed separately:
- folder: `oxyarea-pro`
- main file: `oxyarea-pro.php`
- text domain: `oxyarea-pro`
- namespace: `OxyAreaPro`
- requires OxyArea FREE.

The FREE plugin must contain **no dormant PRO code that is merely unlocked by payment**.

## Mandatory order

1. Complete the Name Lock checklist in `00_NAMING_CLEARANCE.md`.
2. Build a useful, complete FREE MVP.
3. Run security/unit/integration tests.
4. Run WordPress Plugin Check and coding standards.
5. Submit the FREE plugin to WordPress.org early.
6. Only after the core is stable, implement OxyArea PRO as an external add-on.

## Release blockers

Do not release if:

- User A can access User B's private data.
- Protected files can be read by copying a static URL.
- REST/AJAX endpoints lack object-level authorization.
- Private content leaks through search, feeds, sitemaps or REST.
- Redirect rules allow unintended external/open redirects.
- Deactivating PRO breaks the FREE site's basic private area.
- Uninstall silently deletes customer data.
- The final WordPress.org slug has not been approved.
