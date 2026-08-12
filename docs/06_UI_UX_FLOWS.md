# OxyArea — UI/UX Flows

## Admin menu

FREE:
```text
OxyArea
├── Overview
├── Dashboards
├── Content
├── Redirects
├── Roles
├── Users
├── Settings
└── Tools
```

With PRO:
```text
OxyArea
├── Overview
├── Dashboards
├── Content
├── Files
├── Companies
├── Redirects
├── Roles
├── Users
├── Activity
├── Settings
└── Tools
```

## First-run wizard

1. Choose use case:
   - Client portal
   - Staff area
   - Agent/reseller area
   - Generic role-based area
   - WooCommerce area (PRO if integration selected)
2. Select/create role.
3. Create/select login page.
4. Select starter dashboard.
5. Configure post-login redirect.
6. Preview role.
7. Finish with test checklist.

## Dashboard builder

Use Gutenberg principles.

Example:
```text
┌─────────────────────────────────────────┐
│ Welcome, {{display_name}}               │
├────────────────────┬────────────────────┤
│ Notices            │ Profile            │
├────────────────────┼────────────────────┤
│ Documents (PRO)    │ Orders (PRO)       │
└────────────────────┴────────────────────┘
```

FREE basic blocks:
- Welcome
- Text
- Profile
- Links
- Navigation
- Logout
- Role notices
- Shortcode

PRO:
- Personal content
- Company content
- Secure files
- Acknowledgements
- Activity
- WooCommerce

## Upgrade UX

Rules:
- no global nag spam;
- no constant full-width admin banners;
- upgrade prompts only where relevant;
- FREE screens remain fully usable;
- PRO feature explanation can appear as a small contextual card;
- never show a fake enabled control that fails only after clicking.

Example:
On the FREE role dashboard screen:
> Need different documents for each customer? OxyArea PRO adds individual user areas and company portals.

## User screen

FREE:
- role information;
- redirect-related status.

PRO:
- personal dashboard override;
- groups/companies;
- private items;
- secure files;
- access expiration;
- activity summary.

## Permission inspector — PRO

Admin selects user and resource.

Example:
```text
DENIED

User: Mario Rossi
Resource: Contract 2026

✓ Authenticated
✓ Role: Customer
✗ Required company: ACME SRL
  User company: Beta SRL

Final decision: DENY
```

This is diagnostic only and never shown to normal frontend users.
