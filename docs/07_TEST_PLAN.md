# OxyArea — Test Plan and Release Gates

## Automated layers

1. Unit tests.
2. WordPress integration tests.
3. REST tests.
4. Browser/E2E tests.
5. Static analysis.
6. WordPress Coding Standards.
7. Plugin Check.
8. Security test suite.

## Critical user fixtures

Create:
- Admin
- Alice — role Customer, company ACME
- Bob — role Customer, company Beta
- Carol — role Agent
- Dave — anonymous test context

## FREE acceptance tests

### Authentication
- valid login works;
- invalid login does not disclose sensitive detail;
- reset flow uses WordPress securely;
- logout works.

### Roles
- create role;
- clone role;
- assign user;
- cannot accidentally destroy last administrator access.

### Redirect
- Alice role redirects to Customer Dashboard;
- Carol redirects to Agent Dashboard;
- fallback works;
- malicious external destination is rejected unless explicitly supported/allowlisted;
- redirect loops prevented.

### Dashboard
- correct role dashboard renders;
- wrong role denied;
- anonymous denied;
- REST does not leak dashboard body.

### Restriction
- protected page absent from unauthorized search results;
- direct request denied;
- feed/sitemap/REST tested.

## PRO isolation tests

### Per-user
- Alice can see Alice item;
- Bob cannot see Alice item even with same role;
- changing resource ID does not bypass.

### Company
- ACME members can view ACME item;
- Beta member cannot;
- removed member loses access immediately.

### Secure files
- copied static URL does not yield plaintext;
- direct guessed ID denied;
- expired file denied;
- unauthorized REST metadata denied;
- MIME spoof rejected;
- path traversal input ignored/sanitized;
- download logged only after authorized transfer.

### Permission inspector
- explanation matches actual resolver decision;
- inspector itself requires admin capability.

## Compatibility matrix

Test at minimum:
- supported minimum WordPress;
- latest WordPress;
- PHP supported minimum;
- current stable PHP;
- common default theme;
- block theme;
- WooCommerce current supported release for PRO;
- Elementor supported release for PRO.

## Performance

Measure:
- added queries on normal public page;
- added queries on private dashboard;
- 1k, 10k and 100k assignment dataset simulations where feasible;
- role/group resolution caching;
- no full-table scan for common resource checks.

## Release gate

No release with:
- high/critical security finding;
- IDOR;
- file disclosure;
- privilege escalation;
- SQL injection;
- stored XSS;
- open redirect;
- REST authorization bypass;
- destructive uninstall bug.
