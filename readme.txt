=== OxyArea – Private Client Area & User Portal ===
Contributors: oxysoft
Tags: client portal, private area, user dashboard, login redirect, content restriction
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Private client areas for WordPress: frontend login, roles, role dashboards, login redirects and content restriction.

== Description ==

Building a private area in WordPress usually means installing a role editor, a
frontend login plugin, a redirect plugin, a restriction plugin, a portal plugin,
and then writing the glue by hand. OxyArea is that stack as one product.

Private areas, not memberships. There is no subscription billing here, no course
engine, no social wall. What there is: people sign in, they land where they
should, and they see what is theirs.

= What it does =

* Frontend login, logout, password reset and profile, as blocks and shortcodes.
* Create and edit roles, and assign them, without a second plugin.
* Send each role where it belongs after login, after logout, after registration.
* Build a dashboard once and let a thousand users of that role open it.
* Restrict pages and posts to signed-in users or to particular roles, including
  keeping them out of search, feeds and the REST API.
* Publish notices to a role.
* A documented extension API, so add-ons never have to edit the plugin.

= Security =

Access is decided in one place, server-side, per resource, and denied unless
something explicitly allows it. Hiding a menu item is not access control and is
not treated as such here.

Security fixes ship to every edition. A vulnerability is never an upgrade prompt.

= OxyArea PRO =

The free plugin builds private areas by role. When a site needs each individual
customer or each company to see different documents and different data, that is
what OxyArea PRO adds: per-user areas, companies and groups, a secure file vault,
advanced redirect and visibility conditions, notifications, an audit log, and
WooCommerce and Elementor integration.

PRO is a separate plugin, distributed at https://oxywp.com/oxyarea/. Nothing in
the free plugin is disabled code waiting for a licence key.

== Installation ==

1. Install and activate the plugin.
2. Open OxyArea and pick the kind of private area you are building.
3. Choose or create the role it is for.
4. Put the login block on a page.
5. Build the dashboard that role will land on.
6. Restrict the content that belongs to it.

== Frequently Asked Questions ==

= Is this a membership plugin? =

No. There is no billing, no subscription and no paywall engine. OxyArea decides
who may see what once they have an account; how they got the account is somebody
else's job.

= Does it need WooCommerce, Elementor or a page builder? =

No. Dashboards are built with the block editor. WooCommerce and Elementor
integration exists in PRO and is optional there too.

= Does it phone home? =

No. The free plugin contacts no external service and sends no telemetry.

= What happens to my data if I delete the plugin? =

Nothing is removed unless you first turn on "delete data on uninstall". Switching
the plugin off removes nothing at all.

== Changelog ==

= 0.1.0 =
* Not yet released. Plugin foundation: bootstrap, service container, schema
  migrations, capabilities, settings and the access contracts.
