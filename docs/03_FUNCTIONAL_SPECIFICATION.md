# OxyArea — Functional Specification

## FR-001 Authentication

Frontend:
- login;
- logout;
- forgot password;
- password reset;
- edit profile.

Use WordPress authentication APIs. Do not reinvent password storage.

## FR-002 Roles

- list roles;
- create/clone;
- safe capability editor;
- assign users;
- protect administrator access;
- handle multi-role users correctly.

## FR-003 Redirect engine

Events:
- login;
- logout;
- registration;
- reset completion;
- optional wp-admin access.

FREE subjects:
- role;
- global fallback.

PRO subjects:
- exact user;
- company/group;
- capability;
- metadata;
- compound rules.

Required:
- deterministic priority;
- safe internal URL resolution;
- prevention of open redirect;
- test mode.

## FR-004 Dashboard builder

Reusable dashboard templates.

FREE:
- role/default audience;
- Gutenberg-based structure;
- basic widgets.

PRO:
- user/group audience;
- advanced conditional widgets;
- personal override.

Dynamic safe tokens:
- user ID;
- username;
- display name;
- first/last name;
- email;
- allowlisted user meta.

Never support arbitrary PHP execution in dashboard UI.

## FR-005 Content restriction

Protect:
- posts;
- pages;
- compatible CPTs;
- OxyArea resources.

FREE audience:
- logged in;
- roles.

PRO audience:
- users;
- groups;
- capabilities;
- compound rules.

Unauthorized handling:
- login;
- 403;
- 404;
- safe internal redirect;
- message.

## FR-006 Private items

Types:
- notice;
- document/note;
- link;
- status;
- generic private item.

Fields:
- title;
- body;
- audience;
- status;
- publish/expire dates;
- sort order.

## FR-007 User-specific area — PRO

Admin user screen manages:
- private items;
- dashboard override;
- links;
- metadata;
- files;
- access expiry.

No manual WordPress page per user.

## FR-008 Groups/Companies — PRO

Entity fields:
- name;
- slug;
- status;
- description;
- members;
- company admins;
- metadata.

A user may belong to one or more groups if enabled.

## FR-009 Secure files — PRO

Assign to:
- role;
- user;
- group/company.

Features:
- protected download;
- opaque identifiers;
- expiry;
- logging;
- versions;
- acknowledgement;
- notifications.

See security specification.

## FR-010 Activity

Basic system events can be available to diagnostics.

PRO detailed activity:
- access;
- download;
- acknowledgement;
- admin assignment changes.

Never log secrets.

## FR-011 WooCommerce — PRO

When active:
- orders widget;
- downloads;
- addresses;
- My Account integration;
- conditions using customer/order context.

OxyArea must work without WooCommerce.

## FR-012 Gutenberg

First-class from day one.

Blocks:
- login;
- dashboard;
- role/private items;
- profile;
- logout/navigation.

## FR-013 Elementor — PRO

- widgets;
- dynamic tags;
- visibility conditions.

## FR-014 Import/export

FREE:
- settings;
- role dashboard templates;
- role redirects.

PRO:
- groups;
- assignments;
- advanced rules;
- migration reports.

## FR-015 Developer API

Documented:
- actions;
- filters;
- PHP interfaces;
- REST controllers;
- widget registration API;
- audience resolver API.

Third-party add-ons must never edit OxyArea core files.

## FR-016 Accessibility

Target WCAG 2.2 AA patterns:
- keyboard support;
- labels;
- focus;
- semantic HTML;
- meaningful errors;
- no color-only state.
