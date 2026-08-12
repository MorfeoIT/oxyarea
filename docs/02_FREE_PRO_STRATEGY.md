# OxyArea — FREE / PRO Strategy

## Strategic decision

Use **feature-based differentiation**, not artificial quotas.

Avoid:
- “only 10 users in FREE”;
- “only 1 dashboard”;
- “feature works for 30 days”;
- “upload 3 files then pay”;
- PRO code embedded in FREE but disabled by a license flag.

The WordPress.org plugin should be a complete and useful product in itself.

## Product split in one sentence

### FREE
**Build complete role-based private areas.**

### PRO
**Build personalized business portals for individual users and companies.**

---

# OxyArea FREE

The FREE edition must solve a real end-to-end use case.

## FREE-01 Frontend authentication

Included:
- frontend login;
- logout;
- forgot password;
- reset password;
- basic frontend profile form;
- Gutenberg blocks;
- shortcodes.

## FREE-02 Role Manager

Included:
- create role;
- clone role;
- edit safe capabilities;
- delete plugin-created role;
- assign roles to users;
- safeguards against locking out the final administrator.

No arbitrary limit on number of roles.

## FREE-03 Role-based redirects

Included:
- after login by role;
- after logout;
- after registration;
- global fallback;
- deterministic priority;
- internal destinations.

This competes directly with installing a separate redirect plugin.

## FREE-04 Role dashboards

Included:
- create role-based dashboards;
- assign one dashboard resolution rule per role/default;
- unlimited role dashboard templates;
- Gutenberg-based layout;
- responsive frontend.

Basic widgets/blocks:
- welcome/current user;
- text/rich text;
- profile summary;
- links;
- navigation;
- logout;
- shortcode;
- restricted content list.

## FREE-05 Basic content restriction

Protect:
- pages;
- posts;
- selected custom post types where compatible;
- OxyArea dashboard routes.

Audience:
- anonymous vs logged-in;
- role(s).

Unauthorized behavior:
- login redirect;
- custom internal redirect;
- 403/404 option;
- message.

Must also prevent leaks from:
- search;
- feeds;
- REST where applicable;
- sitemaps where applicable.

## FREE-06 Basic private notices by role

Admin can publish notices/information targeted to role(s).

This gives the FREE edition actual portal content without requiring PRO.

## FREE-07 Basic dashboard preview

Admin can preview a dashboard for a selected role.

No true user impersonation.

## FREE-08 Developer hooks

Expose stable hooks/APIs needed for add-ons.

The extension API must exist in FREE so OxyArea PRO can extend it cleanly.

## FREE-09 Import/export of settings

Basic:
- export/import dashboard templates;
- redirect rules;
- plugin settings.

## FREE-10 Privacy/security baseline

Included in both tiers:
- nonces;
- capability checks;
- central authorization service;
- privacy exporter/eraser integration where needed;
- no telemetry without explicit opt-in;
- safe uninstall.

Security must never be a paid feature.

---

# OxyArea PRO

PRO should monetize advanced granularity, workflow and integrations.

## PRO-01 Individual user private area

Assign content directly to a specific user.

Admin user screen:
- private notices;
- private content;
- links;
- private metadata;
- personal dashboard override;
- expiration.

This is one of the main conversion features.

## PRO-02 Companies / Groups

Create organization/account entities:

```text
ACME SRL
├── Mario
├── Anna
└── Luca
```

Assign content to:
- company;
- group;
- company admin;
- selected members.

Users can have:
- role content;
- company content;
- personal content simultaneously.

## PRO-03 Secure File Vault

True protected files:
- user assignment;
- company assignment;
- role assignment;
- protected download controller;
- encrypted/opaque storage design;
- expiry;
- download logging;
- versioning;
- read acknowledgement;
- optional email notification.

**File confidentiality itself must be robust; never sell “security fixes” as an upgrade.**
The PRO feature is the **file vault workflow**, not a weaker security model in FREE.

## PRO-04 Advanced redirect engine

Additional targets/conditions:
- exact user;
- company/group;
- capability;
- user meta;
- first login;
- previous URL;
- WooCommerce status/order condition;
- logical AND/OR conditions.

Add:
- rule tester;
- conflict inspector;
- priority visualizer.

## PRO-05 Advanced visibility conditions

Conditional dashboard widgets/blocks based on:
- user;
- group/company;
- capability;
- user meta;
- dates;
- WooCommerce data;
- custom conditions via API.

## PRO-06 Advanced dashboard widgets

Examples:
- personal documents;
- company documents;
- recent private activity;
- file acknowledgement;
- WooCommerce orders;
- WooCommerce downloads;
- customer addresses;
- custom data table;
- external REST data via developer-configured connector.

## PRO-07 Email notifications

Triggers:
- new private item;
- new file;
- updated document;
- expiration;
- acknowledgement required.

Editable templates.

## PRO-08 Audit & activity

Detailed admin log:
- logins relevant to OxyArea;
- access denied;
- downloads;
- acknowledgements;
- admin assignment changes;
- role/group changes.

Configurable retention.

## PRO-09 “Why can't this user see it?” inspector

Admin selects:
- resource;
- user.

System explains each authorization decision and final result.

This is a support-saving premium feature.

## PRO-10 WooCommerce integration

- enhanced My Account dashboard;
- recent orders;
- downloads;
- addresses;
- order/customer-based conditions;
- menu integration.

## PRO-11 Elementor integration

- OxyArea widgets;
- visibility conditions;
- dynamic tags.

Core never requires Elementor.

## PRO-12 Advanced import/export/migration

- company/group structure;
- assignments;
- redirect rules;
- dashboards;
- mappings;
- migration validation report.

Files are excluded unless explicitly requested and transferred securely.

## PRO-13 White label / Agency tools

Possible Agency tier:
- remove OxyArea branding from frontend/admin client-facing views;
- saved agency presets;
- exportable site blueprint;
- onboarding wizard presets.

Never inject mandatory “Powered by” links.

---

# Recommended conversion logic

FREE must make the user say:

> “I can build a real role-based private area with this.”

PRO should become necessary when they say:

> “Now each customer/company needs different data and documents.”

That is a natural business upgrade, not an arbitrary product limitation.

---

# Comparison table

| Capability | FREE | PRO |
|---|---:|---:|
| Frontend login/reset/profile | ✓ | ✓ |
| Role manager | ✓ | ✓ |
| Login redirects by role | ✓ | ✓ |
| Role dashboards | ✓ | ✓ |
| Unlimited role-based private areas | ✓ | ✓ |
| Content restriction by login/role | ✓ | ✓ |
| Role notices/content | ✓ | ✓ |
| Gutenberg blocks | ✓ | ✓ |
| User-specific private content | — | ✓ |
| Per-user dashboard override | — | ✓ |
| Companies / groups | — | ✓ |
| Secure File Vault workflow | — | ✓ |
| File versions / acknowledgement | — | ✓ |
| Redirect by exact user/company/meta | — | ✓ |
| Advanced conditions | — | ✓ |
| Email notifications | — | ✓ |
| Detailed audit log | — | ✓ |
| Permission inspector | — | ✓ |
| WooCommerce advanced integration | — | ✓ |
| Elementor integration | — | ✓ |
| Agency/white-label tools | — | ✓ |

---

# Technical packaging

## FREE plugin
Repository/distribution:
- WordPress.org
- public source repository recommended.

Contains:
- core services;
- extension interfaces;
- FREE features only.

## PRO plugin
Distributed by Oxysoft:
- requires FREE plugin;
- separate codebase/package;
- registers extra services through FREE hooks/interfaces.

Do not make the FREE plugin download/install executable PRO code from Oxysoft servers.

## Licensing

License handling belongs in OxyArea PRO only.

FREE:
- no license key;
- no remote license checks;
- no unsolicited telemetry.

PRO:
- license activation may contact the OxyWP licensing service under `oxywp.com` only after explicit user action;
- document endpoint, privacy and transmitted fields;
- failure of licensing server should fail gracefully;
- no customer private content/files should be sent to licensing service.

## Updates

WordPress.org updates FREE.

PRO update mechanism belongs to the externally distributed PRO plugin and should use OxyWP infrastructure under `oxywp.com`.

FREE must not hijack WordPress updates to install PRO.

---

# Suggested commercial tiers

Initial hypothesis, to validate after launch:

### OxyArea FREE
€0

### OxyArea PRO Personal
1 site.

### OxyArea PRO Business
5 sites.

### OxyArea PRO Agency
Higher/unlimited site allowance plus agency presets/white label.

Do not decide final price before measuring:
- FREE installs;
- activation rate;
- feature usage;
- support burden;
- conversion requests.

## Most important rule

Never paywall a security patch.

All vulnerability fixes must ship to every affected edition.
