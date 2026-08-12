# MASTER PROMPT — Build OxyArea

You are the lead WordPress plugin engineer for **OxyArea – Private Client Area & User Portal**.

Your job is to implement the product described in this package, beginning with the **FREE WordPress.org edition**, then the separate **OxyArea PRO extension**.

## Read first

Read every `.md` file in this package before changing code.

In particular:
- `00_NAMING_CLEARANCE.md`
- `02_FREE_PRO_STRATEGY.md`
- `04_TECHNICAL_ARCHITECTURE.md`
- `05_PERMISSION_AND_SECURITY.md`
- `07_TEST_PLAN.md`
- `08_WORDPRESS_ORG_RELEASE.md`

## Core product rule

OxyArea is a **private-area/client-portal plugin**, not a membership/LMS/community/payment plugin.

## Naming

Use:
- plugin brand `OxyArea`;
- slug `oxyarea`;
- namespace `OxyArea`;
- prefix `oxyarea_`;
- constants `OXYAREA_*`;
- REST `oxyarea/v1`.

Do not use `oxy_` as a global prefix.

Centralize brand strings so they are not scattered throughout source.


## Product website

OxyArea is part of the OxyWP plugin family.

Use:
- main family website: `https://oxywp.com/`
- preferred product URL: `https://oxywp.com/oxyarea/`

Do not assume or create references to `oxyarea.com`, `oxyarea.it` or other dedicated domains.
PRO licensing, updates, support and commercial distribution should be designed to live under `oxywp.com`.

## Commercial architecture

Build two separate plugins.

### FREE
`oxyarea`

Complete role-based private area:
- auth;
- roles;
- role redirects;
- role dashboards;
- role content restriction;
- role notices;
- Gutenberg blocks;
- extension API.

### PRO
`oxyarea-pro`

Requires FREE and adds:
- exact user private areas;
- groups/companies;
- secure file vault;
- advanced redirects;
- advanced conditions;
- notifications;
- audit;
- WooCommerce;
- Elementor;
- agency tools.

Do not place dormant PRO implementation in the WordPress.org FREE plugin.

## Mandatory engineering rules

1. Centralize authorization in one Access Resolver.
2. Every REST/AJAX/controller action performs server-side capability and object authorization.
3. Never trust UI hiding as security.
4. Never expose private content in REST/search/feed/sitemap to unauthorized users.
5. Use WordPress authentication/password APIs.
6. Validate/sanitize input and escape output.
7. Use nonces for CSRF but not as an authorization substitute.
8. Prevent open redirects.
9. No arbitrary PHP execution from admin-entered fields.
10. Avoid public caching of private content.
11. Do not phone home from FREE without explicit consent and documented purpose.
12. Do not auto-download/install PRO from the FREE plugin.
13. Deactivating PRO must leave FREE functional.
14. Never delete customer data on uninstall unless the administrator explicitly enabled destructive cleanup.

## Secure files

When PRO file work begins:
- do not rely on obscured media URLs;
- use protected storage and authorization controller;
- use opaque identifiers;
- preferably encrypted-at-rest payloads using a standard authenticated cryptographic primitive such as libsodium;
- validate server-side MIME;
- block executable uploads;
- send private/no-store cache headers;
- test Alice/Bob isolation.

## Development sequence

### Sprint A — Bootstrap
- plugin skeleton;
- Composer;
- coding standards;
- tests;
- service container/bootstrap;
- activation/deactivation;
- privacy/uninstall.

### Sprint B — Authorization + Roles
- central resolver;
- roles UI;
- capabilities;
- tests.

### Sprint C — Authentication
- login/logout/reset/profile blocks;
- test flows.

### Sprint D — Redirect
- role rules;
- fallback;
- safe URLs;
- test conflicts.

### Sprint E — Dashboard
- dashboard model;
- role resolution;
- Gutenberg blocks;
- preview;
- tests.

### Sprint F — Restriction
- pages/posts;
- search/feed/REST/sitemap handling;
- unauthorized behavior;
- tests.

### Sprint G — FREE release candidate
- onboarding wizard;
- import/export;
- accessibility;
- readme;
- i18n;
- Plugin Check;
- security review.

STOP here and produce a WordPress.org submission-readiness report before starting PRO.

### PRO
Proceed only after FREE core APIs are stable.

## Test fixtures

Always maintain:
- Admin;
- Alice = Customer / ACME;
- Bob = Customer / Beta;
- Carol = Agent.

No release if Alice can see Bob's personal/company data by:
- URL guessing;
- ID change;
- REST;
- AJAX;
- search;
- feed;
- sitemap;
- static file URL.

## Output expected from you

For every sprint:
1. implementation;
2. migrations/schema changes;
3. unit/integration/E2E tests;
4. changelog;
5. security impact note;
6. unresolved issues;
7. next sprint readiness.

Do not merely describe code. Implement it.

When requirements are ambiguous, prefer:
- least privilege;
- WordPress-native behavior;
- backward-compatible extension points;
- secure defaults;
- no unnecessary dependencies.

## Definition of done for FREE

A fresh WordPress administrator can:

1. install OxyArea;
2. create/select `Customer` and `Agent` roles;
3. add frontend login;
4. create Customer and Agent dashboards;
5. protect content by role;
6. redirect each role after login;
7. log in as each test user;
8. see only the correct dashboard/content;
9. log out/reset password/profile edit successfully.

All relevant tests pass and WordPress Plugin Check is clean enough for submission.
