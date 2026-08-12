# Changelog

All notable changes to OxyArea are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project follows
[semantic versioning](https://semver.org/).

## [Unreleased]

### Fixed

- **Forms redirected to the site's front page instead of back to themselves.**
  `Form::current_url()` read `WP::$request`, which is empty where it was called:
  forms are handled on `init`, and WordPress does not work out which page was
  asked for until `parse_request`, several hooks later. So asking for a password
  reset bounced the person to the home page carrying a confirmation about a form
  they could no longer see, and the same applied to setting a new password and to
  saving a profile. Every unit test passed, every in-WordPress check passed and
  Plugin Check was clean throughout: the bug lived in the one place none of them
  looks, which is what happens after a redirect.

### Added

- `scripts/testbed-reset-flow.sh`: the forgotten-password flow over HTTP, from
  "I have forgotten it" to signing in with the new password. 22 checks, including
  that a stranger and a real account get word-for-word the same answer, that the
  link comes back to the site rather than wp-login.php, that changing one
  character of the key invalidates it, and that a spent link cannot be used twice.
  It also exercises profile editing, and that changing an email address needs the
  current password while changing a name does not.
- `scripts/testbed-mail-capture.php`, a test-bed mu-plugin that writes outgoing
  mail to a file. The fixtures have `@example.test` addresses, which resolve to
  nothing by design, so capturing is not a way around the test — it is the only
  way to run it.

### Added — Sprint F, content restriction

- Protect a page or post by role, or by "anybody signed in", from a box on the
  editor.
- **The five release blockers, closed and checked.** The specification refuses a
  release if private content leaks through search, feeds, sitemaps or REST, or if
  one customer can reach another's page. Those are five different ways of asking
  WordPress a question, and a plugin that closes four has a hole in the fifth.
  `scripts/testbed-restriction-flow.sh` asks all five over real HTTP, as a
  stranger and then as each of two customers.
- Two guards. The one at `template_redirect` stops a private page being *read* —
  the one that matters, since a site that filtered every listing perfectly and
  left the URL working would be a site where the page is one guess away. The
  other keeps it out of listings, the REST API, the sitemap and neighbour links.
- Filtering happens after the query rather than inside it. Expressing "posts this
  person may see" as a WHERE clause means reimplementing the resolver in a second
  language, where it cannot be tested and where every subject type PRO adds has to
  be written twice. The cost is that a page of ten may show eight; the benefit is
  that what it shows is decided by the same code that decides everything else.
- Restriction is stored as assignments — the rows the resolver already reads. No
  second table and no "is private" flag to fall out of step: a page with no rows
  is public, a page with rows is private to whoever they name. The one new
  question is "is this restricted at all", which has to be asked first, because a
  resolver that refuses whatever nobody granted would refuse every post on a blog.
- Four behaviours for a refusal, and the differences matter. 404 is the only one
  that does not confirm the page exists, which on a site whose private area is the
  product is often the point. Sending somebody to sign in when they are already
  signed in is a loop, so it becomes a message; so does sending them to a sign-in
  page the site has not configured.
- "Restricted, but nobody named" is stored as exactly that. Quietly turning it
  back into a public page would be the opposite of what was clicked.

### Fixed

- The dashboard post type declared `edit_post`, `read_post` and `delete_post`
  alongside `map_meta_cap`, which turns a meta capability into a primitive one.
  WP_DEBUG on the test bed logged twenty "map_meta_cap was called incorrectly"
  notices. Only the primitives are declared now.

### Known

- Plugin Check reports one warning, and it is a fair one: `post__not_in` on the
  sitemap query does not scale to very large sets. The alternatives are worse — a
  WHERE clause duplicating the resolver, or a leak — so it stays, and it is worth
  revisiting when PRO's file vault makes large sets ordinary.

### Added — Sprint E, dashboards

- **One template serves everybody who holds a role.** A site with four hundred
  customers has one customer dashboard, and the four hundred and first customer
  is not a content task. This is the product in one sentence.
- Dashboards are a post type, so the block editor is the layout editor. Every
  hour spent building one would have built something worse than the editor
  already sitting in the same admin. Not public and not publicly queryable: the
  block and the shortcode take no identifier from anywhere, so what appears is
  what the resolver decided, and the only way to change it is to change somebody's
  roles.
- Resolution uses the same ladder as the redirect rules — a role beats "anybody
  signed in", which beats the site default — because two screens answering the
  same question differently would teach a site owner one rule and then break it.
  There is deliberately no priority field: two dashboards for one role is a
  mistake being made, not a preference being expressed, and a tie-break would make
  it configurable instead of visible.
- Placeholders — `{{display_name}}` and five siblings — are substitution and
  nothing else, filled from a list the caller supplies. The specification forbids
  evaluating PHP in an admin field, and rightly: it would make every account that
  can edit a dashboard the equal of an administrator, and the role editor two
  sprints ago decoration. Values are escaped before substitution, and unknown
  placeholders are removed rather than left on a customer's screen.
- Three blocks, not thirteen: the container that resolves which dashboard, a
  greeting that knows who is reading it, and a read-only account summary.
  WordPress already has a paragraph, a heading, a list and columns, and they are
  better than anything written here to replace them.
- The preview answers "which template would this role get, and how does it read"
  without signing in as anybody, borrowing a capability or touching a session.
  True impersonation stays out of scope, as it should: it is a serious capability
  with a serious audit trail, not the price of previewing a layout.
- A dashboard whose audience cannot be read is dropped rather than widened.
  Reading it as the site default would take one role's page and show it to
  everybody signed in.
- `scripts/testbed-dashboard-flow.sh`: 10 checks over HTTP, including that a
  signed-out visitor gets the sign-in form and none of the private content.

### Fixed

- The escaping test for dashboard placeholders planted its hostile value through
  `wp_update_user`, which strips tags on the way in — so it would have passed
  whether or not this plugin escaped anything. It now writes straight into the
  table, past every sanitiser WordPress provides, and checks that our own layer
  holds on its own. It does.

### Added — Sprint D, the redirect engine

- Rules by role for four moments: signing in, signing out, registering and
  setting a new password. Each moment also takes a fallback rule, stored as a row
  like any other so that turning it off and reordering it work the same way.
- **A total ordering**, applied in this sequence:
  1. *Specificity.* A rule about a role beats one about everybody signed in,
     which beats the event's fallback. First, because it is what a site owner
     means: writing "agents go to the agent dashboard" alongside "everybody goes
     to the shop" should not require discovering a priority field.
  2. *Priority*, lower first, as with a WordPress hook. This settles two rules of
     equal specificity, which is the ordinary case of somebody holding two roles
     and the reason the field exists.
  3. *Age*, oldest first. Not because the older rule deserves it, but because
     something has to decide, and a tie broken by whatever order the database
     felt like returning is a bug that appears fortnightly and never reproduces.
- The engine is pure and WordPress-free, like the access resolver, with 37 tests
  over the conflicts.
- Every destination leaves through the guard written in Sprint C, whether it came
  from a rule, a request or a filter. A rule pointing at another site is refused
  where it is written, rather than silently ignored where it is used.
- Both sets of hooks: WordPress's `login_redirect`, `logout_redirect` and
  `registration_redirect`, so somebody who reached wp-login.php directly is
  governed too, and OxyArea's own filters, so the frontend forms use the same
  engine rather than a second copy of the idea.
- Registration matches against the role the site gives new accounts, which is the
  only thing actually known at that moment.
- An optional setting keeps people who cannot write posts out of wp-admin,
  sending them wherever their sign-in rule points.
- The Redirects screen shows where every role currently lands, worked out with
  the same engine that will decide it for real, and says when more than one rule
  matched. The ordering being documented does not help somebody staring at four
  rules wondering why a customer keeps arriving at the shop.
- `scripts/testbed-redirect-flow.sh`: the rule, the sign-in, and the Location
  header the browser actually receives.

### Fixed

- The frontend sign-in form overrode an explicit `redirect_to` with the matching
  rule, while WordPress's own `login_redirect` deferred to it: two answers to the
  same question, and which one applied depended on which form was used. Found by
  the HTTP flow test and by nothing else, being an interaction between two layers
  that only meet in a real request.

### Added — Sprint C, frontend authentication

- Sign in, sign out, forgotten password, set a new password and edit your own
  details. Five blocks and five shortcodes, with the same object behind both so
  the two cannot drift apart.
- Authentication is WordPress's throughout — `wp_signon`, `retrieve_password`,
  `check_password_reset_key`, `reset_password`. Nothing here touches a hash.
- `SafeRedirect`: a pure open-redirect guard with 33 tests of its own. A
  whitelist rather than a blacklist — a root-relative path, or an absolute URL on
  this host, and everything else becomes the fallback. It exists for a dull and
  effective attack: a link to the genuine login form with a destination attached,
  which signs somebody in and then lands them somewhere else with their guard
  down. Sprint D's redirect engine will use the same guard.
- **No account enumeration.** WordPress separates "unknown username" from "wrong
  password", and its lost-password form says whether an address is known. On a
  site whose purpose is telling customers apart, both answer the question of
  whether a particular person is a customer of yours. Every failure now reads the
  same, and the lost-password form gives the same answer and the same redirect
  either way.
- The reset link comes back to a page on the site instead of `wp-login.php`, when
  a page has been chosen in the settings. With no page chosen WordPress behaves
  exactly as it always has, which is the right default for a plugin that was
  activated a minute ago and knows nothing about the site's pages.
- Changing an email address or a password asks for the current password. Not
  because the session is doubted, but because an unattended laptop is how
  accounts are taken over in practice.
- Templates are overridable from a theme, the way WooCommerce taught everybody to
  expect: drop `oxyarea/auth/login.php` into a theme and own that form.
- Accessibility: labelled fields, `role="alert"` on failures and `role="status"`
  on confirmations, visible focus that a theme cannot remove, `autocomplete`
  hints so password managers work, and no state signalled by colour alone.
- **No JavaScript build step.** Blocks are registered from `block.json` on the
  server, rendered in PHP, and the editor gets one hand-written ES5 file that
  draws a labelled placeholder. The package ships the source that was written,
  which is what the review guidelines ask for, reached by not creating the
  problem. A live preview is deliberately absent: these are forms, and one that
  half works in the editor invites somebody to type a password into it.
- `scripts/testbed-login-flow.sh`: 15 checks over real HTTP — the page, the form,
  the nonce, a refused password, a successful sign-in, the session cookie, and a
  `redirect_to` pointing off-site that is not followed.

### Verified on a real WordPress

First run on WordPress 7.0.3, on the test bed at `test.44123.it/oxyarea`. The
plugin activates without a single PHP notice, creates its table, writes its
settings and grants its capabilities. What the run found, and what changed:

- **Plugin Check ignores the project's `phpcs.xml.dist`** and applies its own
  ruleset. Every exclusion in the project ruleset counted for nothing: the code
  looked clean locally and would have failed review. The exclusions moved into
  the code as `phpcs:ignore` comments, which both tools read, and the ruleset now
  carries no escaping exclusions at all.
- Exception messages were escaped where thrown *and* where printed, so an
  administrator would have read `The role &quot;editor&quot;`. Now escaped once,
  following the WordPress convention: at the throw, printed as-is by the single
  place that prints them.
- `.gitkeep` files shipped inside the package, and the plugin directory rejects
  hidden files outright. Now `export-ignore`d.
- The readme's short description was over the 150-character limit.
- Plugin Check has DirectDB sniffs of its own that the existing `phpcs:disable`
  block did not name.

Plugin Check now reports no errors.

- `tests/manual/smoke.php`: 34 checks against a real installation, covering the
  role manager's refusals, the escalation guard, the assignment repository and
  the resolver wired to real WordPress roles. Cleans up after itself. It also
  confirmed the free/PRO boundary: a rule naming an individual user is stored and
  read back correctly but matches nobody, because presenting a user as a subject
  is what PRO's audience providers add.
- `scripts/testbed-setup.sh` and `scripts/testbed-deploy.sh`, so the test bed is
  reproducible rather than a thing that happened once.

### Added — Sprint B, authorisation and roles

- **The access resolver.** One class decides who may see what, and it contains no
  WordPress call at all: the rules, the subjects, whether the user administers
  the plugin and what time it is all arrive through the constructor. That is what
  makes it possible to test every branch of the authorisation logic rather than
  assert that it looks right.
- Its rules, in order: somebody who administers OxyArea may read what OxyArea
  protects; a rule outside its validity period does not count; an explicit deny
  beats every allow; one matching subject out of several is enough; anything else
  is a refusal, including a resource with no rules at all.
- `Assignment`, the stored rule: a subject, an effect, a priority and an optional
  window. A window that ends before it starts never applies, because the safe
  reading of corrupt data in an access rule is that it grants nothing.
- `AudienceResolver`, which asks every registered provider what a user counts as
  and merges the answers, collapsing duplicates and caching per request.
- `RoleAudienceProvider`: signed in or not, plus every role held. A signed-out
  visitor presents "anonymous" and nothing else — it is a distinct audience, not
  a synonym for everybody.
- `HookedAccessResolver`, a thin wrapper carrying the `oxyarea_access_decision`
  filter, so the resolver itself stays free of WordPress. A filter returning
  anything that is not a Decision is ignored rather than trusted.
- `AssignmentRepository`, reading and writing the assignments table with prepared
  values and a per-request cache, skipping rows it cannot make sense of and
  refusing to trust the shape of what a shared object cache hands back.
- **The role manager**, with the refusals that matter: nobody edits the
  administrator role; nobody deletes a role OxyArea did not create; nobody grants
  a capability they do not hold themselves; nobody edits themselves out of the
  role screen; and capabilities outside the catalogue are left untouched, so a
  role carrying WooCommerce's capabilities still carries them afterwards.
- `CapabilityCatalogue`, which separates everyday capabilities from the ones that
  hand over the site, and treats any capability it has never heard of as
  dangerous.
- The Roles admin screen: list, create, clone the capabilities of an existing
  role, edit capabilities by group, assign a user, and delete with reassignment.
  Forms post to admin-post.php, so nothing mutates on a GET.
- `Container::get_typed()`, so the object graph is checked by static analysis
  rather than hoped about.
- 54 further unit tests, including the case that found a real hole: the resolver
  trusted its manager check for signed-out visitors, which would have handed
  every protected resource to the open internet had an add-on shipped a careless
  implementation. The guard now lives in the resolver.

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
- Fixed before anything else ran: the migration list used `fn (): void`, which
  cannot compile, and would have white-screened the plugin on activation.

### Notes

- Nothing is released yet. Version 0.1.0 is the working version, not a tag.

[Unreleased]: https://github.com/MorfeoIT/oxyarea
