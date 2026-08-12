# OxyArea — Permission and Security Model

## Authorization subjects

FREE:
- anonymous;
- authenticated;
- role.

PRO:
- capability;
- company/group;
- exact user;
- advanced conditions.

## Authorization principle

Private resources default to **deny**.

Explicit deny overrides allow when advanced rules are supported.

## Multi-role users

Never assume one role.

Recommended:
- if any role matches an allow, permit;
- explicit PRO deny overrides;
- document deterministic precedence.

## Groups vs roles

Role answers:
> What kind of user is this?

Group/company answers:
> Which organization/account does the user belong to?

Do not create one WordPress role per customer company.

## Object-level security invariant

If Alice and Bob are both customers:
- Alice must not access Bob's private items by changing an ID;
- Alice must not access Bob's data via REST;
- Alice must not access Bob's files by guessed URL;
- search/feed/sitemap must not disclose Bob's content metadata.

## Capabilities

Suggested:
- `manage_oxyarea`
- `manage_oxyarea_dashboards`
- `manage_oxyarea_roles`
- `manage_oxyarea_redirects`
- PRO:
  - `manage_oxyarea_groups`
  - `manage_oxyarea_files`
  - `view_oxyarea_activity`

Do not rely on menu hiding.

## Nonces

Use nonces for CSRF protection, but never treat nonce validation as authorization.

Always pair with capability/object checks.

## Input/output

- sanitize input;
- validate domain values;
- escape output at rendering boundary;
- use `$wpdb->prepare`;
- no arbitrary code evaluation;
- no arbitrary PHP UI field.

## Redirect security

- internal URLs by default;
- use safe redirect APIs;
- external domains disabled unless explicitly allowlisted by admin;
- prevent protocol-relative URLs and javascript/data schemes;
- validate fallback loops.

## REST/API

Every endpoint must have:
- auth context;
- permission callback;
- resource-specific check.

No endpoint may trust a supplied `user_id` simply because requester is logged in.

## Privacy

- collect only needed data;
- privacy policy helper text;
- exporter/eraser integration where appropriate;
- no telemetry by default;
- external service calls only with explicit action/consent and documentation.

## Secure File Vault — PRO

Private file must not rely only on hiding a Media Library URL.

Recommended:
- dedicated storage;
- opaque stored filename;
- protected controller;
- preferably encrypted-at-rest payload so accidental static serving does not reveal plaintext;
- server-side MIME validation;
- allowlist;
- no executable uploads;
- authorization on every download;
- `Cache-Control: private, no-store`;
- audit event.

Suggested encryption:
- libsodium authenticated encryption;
- versioned crypto metadata;
- no custom cryptographic algorithm.

## Security is not monetized

If a vulnerability affects FREE and PRO:
- patch every affected edition promptly.
- never make the safe implementation a PRO-only patch.
