# OxyArea FREE — WordPress.org submission readiness

**Version:** 0.1.0
**Assessed:** 12 August 2026
**Assessed against:** WordPress 7.0.3, PHP 8.3.33, on the test bed at
`test.44123.it/oxyarea`
**Verdict:** the code is ready to submit. **One thing is not, and it is not
code:** the manual trademark similarity check. See "Before submitting".

*Updated 12 August 2026, later the same day: the password reset flow has now been
run end to end, and so has profile editing. Doing so found a real bug — see §5.*

The master prompt asks for this report before any PRO work begins. It is written
to be read by somebody deciding whether to press send, so it says what was
checked, how, and what was not.

---

## 1. The definition of done

The master prompt sets nine steps a fresh administrator must be able to complete.
Each is marked with how it was checked, not with how confident anybody feels.

| # | Step | State | How it was checked |
|---|------|-------|--------------------|
| 1 | Install OxyArea | done | Activated on WordPress 7.0.3 with `WP_DEBUG` on; no notice, no warning |
| 2 | Create or select Customer and Agent roles | done | Roles screen; 34 checks in `tests/manual/smoke.php` |
| 3 | Add frontend login | done | 15 checks over HTTP in `scripts/testbed-login-flow.sh` |
| 4 | Create Customer and Agent dashboards | done | 10 checks over HTTP in `scripts/testbed-dashboard-flow.sh` |
| 5 | Protect content by role | done | 17 checks over HTTP in `scripts/testbed-restriction-flow.sh` |
| 6 | Redirect each role after login | done | 6 checks over HTTP in `scripts/testbed-redirect-flow.sh` |
| 7 | Sign in as each test user | done | Alice and Bob, over HTTP, with real session cookies |
| 8 | See only the correct dashboard and content | done | The same three flow scripts, from three points of view |
| 9 | Sign out, reset a password, edit a profile | done | All three over HTTP. The reset runs from "I have forgotten it" to signing in with the new password, via a captured email: 22 checks in `scripts/testbed-reset-flow.sh` |

## 2. Plugin Check

```
Success: Checks complete. No errors found.
```

**Zero errors. One warning**, and it is a fair one:
`WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in` on the
sitemap query. `post__not_in` does not scale to very large exclusion sets. It
stays, because the alternatives are worse: a `WHERE` clause duplicating the
access resolver in SQL, where it cannot be tested, or leaving restricted pages in
the sitemap, which is one of the release blockers. Worth revisiting when PRO's
file vault makes large sets ordinary.

Two things this run taught the project, both recorded in the changelog:

- **Plugin Check ignores `phpcs.xml.dist` entirely.** Every exclusion in the
  project ruleset counted for nothing; the code looked clean locally and would
  have failed review. All relaxations now live in the code as `phpcs:ignore`
  comments, which both tools read.
- Hidden files are rejected outright, so the `.gitkeep` files that held empty
  directories open are `export-ignore`d.

## 3. The release gate

`docs/07_TEST_PLAN.md` refuses a release with any of the following. Each was
looked for rather than assumed absent.

| Gate | State | Evidence |
|------|-------|----------|
| IDOR — one customer reaching another's page | closed | Alice and Bob, over HTTP: page, search, REST |
| Private content in **search** | closed | `restriction-flow`, as a stranger and as Bob |
| Private content in **feeds** | closed | same |
| Private content in **REST** | closed | 404 on the single item, absent from the collection |
| Private content in **sitemaps** | closed | verified with `blog_public` turned on for the check — see §6 |
| Open redirect | closed | 33 unit tests on the guard; `redirect_to=https://evil.example` refused over HTTP |
| Privilege escalation | closed | a subscriber cannot grant `install_plugins`, `edit_users`, `manage_options`, or even `upload_files` |
| SQL injection | closed by construction | every value prepared; the only interpolations are our own table names and integers from our own table, each annotated |
| Stored XSS | closed | a `<script>` written straight into the users table arrives on the page as text |
| Destructive uninstall | closed | deactivation removes nothing; uninstall removes nothing unless the setting is on |
| REST authorisation bypass | closed | single-item routes guarded; collections filtered |

## 4. Guideline compliance

| Guideline | State |
|---|---|
| No trialware; free plugin complete in itself | The plugin builds a working role-based private area with no licence key and no locked controls. Nothing here is PRO code waiting to be unlocked |
| GPL-compatible | GPL-2.0-or-later, declared in the header, `readme.txt` and `composer.json` |
| Human-readable source | No build step. The JavaScript shipped is the JavaScript written; there is no bundle, no minification and no `vendor/` |
| Translation-ready | 274 strings, one text domain, `languages/oxyarea.pot` generated with `wp i18n make-pot` |
| Screenshots | Seven, in `.wordpress-org/`, with captions in `readme.txt`. Taken from a real installation by `scripts/screenshots.mjs` rather than mocked up, so they cannot drift from what the plugin does |
| No undocumented external services | The plugin contacts nothing at all |
| No unsolicited tracking | None |
| Correct headers | Name, URI, description, version, requires, author, licence, text domain, domain path |
| Upsell restraint | One dismissible notice, once, on OxyArea's own screens and the plugins list, which disappears permanently after the wizard is used |

## 5. What running the reset flow found

Closing the gap was worth it on its own. `Form::current_url()` read
`WP::$request`, which is **empty where it was called**: forms are handled on
`init`, and WordPress does not work out which page was asked for until
`parse_request`, several hooks later.

So asking for a password reset bounced the person to the site's front page,
carrying a confirmation about a form they could no longer see. The same applied
to setting a new password and to saving a profile. Every unit test passed, every
in-WordPress check passed, and Plugin Check was clean throughout: the bug lived
in the one place none of them looks, which is what happens after a redirect.

Mail is captured rather than sent on the test bed, by a mu-plugin in `scripts/`.
That is not a shortcut around the test: the fixtures have `@example.test`
addresses, which resolve to nothing by design, so no SMTP configuration could
ever deliver to them. What needed proving is the link WordPress puts in the
message and what happens when somebody follows it, and neither is a question
about a mail server.

## 6. What has not been verified, and what that means

Written plainly, because an unverified thing is not a working thing.

1. **The sitemap needs `blog_public` on to exist at all.** WordPress serves no
   sitemap on a site set to discourage search engines, which the test bed is. The
   flow script turns it on for two checks and off again. Worth knowing, because
   without that the channel looks fine only because it is absent.
2. **No rate limiting on sign-in.** The same as WordPress itself, and out of scope
   in the specification, but more pointed on a site whose purpose is a private
   area. A decision to take, not a defect.
3. **One person has reviewed this.** A security review by somebody who did not
   write the code has not happened. The suites in §6b narrow what that review has
   to take on trust; they do not replace it.

## 6b. What the test suites now cover

The `integration` and `security` suites were empty through all seven sprints,
and the release gate was held up by scripts with a pass counter. It is now held
up by 49 tests against a real WordPress and a real database, running on every
push.

| Suite | Tests | What it settles |
|---|---|---|
| `integration` — `HarnessTest` | 8 | That the harness works at all: WordPress up, plugin loaded through `plugins_loaded`, tables created, capabilities granted, each test rolled back. Without it a green suite could mean WordPress never started |
| `integration` — `RoleManagerTest` | 17 | The refusals that need real roles: the administrator role untouchable, unmanaged roles undeletable, the escalation guard against four site-ending capabilities, cloning not a way around it, other plugins' capabilities surviving a save, deletion reassigning rather than stranding, the last administrator not demotable |
| `security` — `ContentRestrictionTest` | 18 | The release blockers, asked all five ways WordPress lists content — search, feed, archive, sitemap, REST — as each of a stranger, a customer and a non-customer. REST answers 404 rather than 403, because 403 confirms the page is there |
| `security` — `AccountPrivacyTest` | 6 | That a stranger cannot learn which email addresses are customers: a wrong password and an unknown account are refused in identical words, and destinations on other sites are refused |

Three things are worth recording about how they were built.

The suites are pinned to **PHPUnit 9.6**, not 10. WordPress's own test library
calls `PHPUnit\Util\Test::parseTestMethodAnnotations()`, which 10 removed, so 10
cannot run them at all.

The bootstrap keys off `WP_PHPUNIT__TESTS_CONFIG` rather than the
obvious-looking `WP_PHPUNIT__DIR`, because `wp-phpunit` sets the latter itself
from a Composer autoload file the moment the autoloader runs. Keyed off it, the
unit suite tried to boot WordPress.

One test failed for a reason that was not the plugin's: it asserted that a fresh
install has one administrator, and the WordPress test installer creates one of
its own, so there were two and the guard had nothing to refuse. The test now
builds the condition instead of assuming it. A premise, not a defect — but a
green suite that never exercised the guard would have been worse than a red one.

## 6c. What CI adds

Four jobs on every push, on GitHub Actions:

| Job | What it does |
|---|---|
| Coding standards | PHPCS against the WordPress standards, then PHPStan at level 8 |
| Unit tests | The 225-test suite on PHP 8.1, 8.2, 8.3 and 8.4 — the plugin claims 8.1+, so it is checked on 8.1+ |
| Integration and security | The 49 WordPress tests on PHP 8.1 and 8.3, against MySQL 8 |
| Distributable package | Builds the package with `git archive`, then asserts that nothing from development is in it, that everything users need is, that no hidden file is, and that every shipped PHP file parses |

The last job earned its place immediately: on its first run it failed, because
the plugin declared "GPLv2 or later" in two places and shipped no copy of the
licence. Saying which licence applies is not the same as granting it. `LICENSE`
is now in the package.

`scripts/wordpress-test-env.sh` builds the WordPress test environment — core,
database config and all — and is the same script CI runs and a person runs, so a
green pipeline means something reproducible rather than something only GitHub
knows how to do.

## 7. Before submitting

One thing, and it is not code:

1. **Complete the Name Lock checklist** in `docs/00_NAMING_CLEARANCE.md` §10.
   Attempted on 2026-08-12, and §9b records the outcome in full. In short: the
   Italian register (UIBM) was searched directly and is clean for `oxyarea`,
   `oxiarea` and `oxysoft`, against working controls. EUIPO, TMview and WIPO are
   closed to scripted access by design — TMview requires a captcha-validated
   session, WIPO a proof-of-work challenge — so that part needs a person at a
   browser or a professional, and the box stays unticked.

   §9b also records something the checklist did not anticipate and which matters
   more than any register entry found so far: Soflyy's published trademark
   policy for the Oxygen page builder says, verbatim, **"Do not use 'oxygen' or
   'oxy' in product names."** No registered Soflyy mark could be found, so this
   is a private policy rather than a right — but WordPress.org acts on trademark
   complaints and the slug cannot be changed after approval, which makes the
   exposure asymmetric. It applies to the whole OxyWP family, not to this plugin
   alone. That is a commercial decision to take before submitting, not a defect
   to fix.
Then submit. The slug becomes real on approval, and the branding strings are
centralised in `Brand` precisely so that a pre-launch rename would still be cheap
if it came to that.

## 8. The numbers

| | |
|---|---|
| Files in the distributed package | 106 |
| Unit tests | 225, no WordPress required |
| WordPress tests | 49, in the `integration` and `security` suites |
| PHP versions tested in CI | 8.1, 8.2, 8.3, 8.4 |
| Checks inside a real WordPress | 109 |
| Checks over HTTP | 70, across five flow scripts |
| `php -l` | clean |
| PHPCS, WordPress standards | clean |
| PHPStan level 8 | clean |
| Plugin Check | 0 errors, 1 warning |
| PHP notices during a full run | none; no `debug.log` is created |

## 9. Decision, 2026-08-12: submission waits for the family

OxyArea will **not** be submitted to WordPress.org on its own. It waits until
the rest of the OxyWP family is finished, and they go in together.

The reason is §9b of the naming clearance. The first plugin approved is the one
that settles the Oxy- prefix in practice, and a slug cannot be changed after
approval. Submitting one to see what happens would be deciding for all of them
without knowing it — and deciding it in the order that gives the least
information for the most commitment. One naming decision, taken once, applied
to the whole family, is the same work with none of that risk.

What this costs: a slug cannot be reserved. WordPress.org is explicit that an
empty plugin may not be submitted to hold a name, so `oxyarea` stays claimable
by anybody until the day it is submitted. Nothing suggests anyone wants it —
four Oxy-prefixed plugins exist in the whole directory — but the risk is real
and it is the price of waiting.

What this does **not** block: everything in this document is finished and
verified. The plugin is releasable; it is simply not being released yet.

## 10. What comes next

The master prompt says to stop here. PRO begins only once the free core's APIs
are stable, and the extension points PRO will need — `oxyarea_register_services`,
`oxyarea_audience_providers`, `oxyarea_access_decision`, the audience and widget
interfaces — exist and are exercised, but have never been used by a second
plugin. The first thing PRO will teach us is which of them is wrong.
