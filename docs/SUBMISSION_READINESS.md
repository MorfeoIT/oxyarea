# OxyArea FREE — WordPress.org submission readiness

**Version:** 0.1.0
**Assessed:** 12 August 2026
**Assessed against:** WordPress 7.0.3, PHP 8.3.33, on the test bed at
`test.44123.it/oxyarea`
**Verdict:** the code is ready to submit. **Three things are not, and none of
them is code.** They are listed under "Before submitting", and the shortest is
ten minutes.

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
| 9 | Sign out, reset a password, edit a profile | **partly** | Sign-out verified over HTTP. **Password reset and profile editing have not been exercised end to end** — see §5 |

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
| Private content in **sitemaps** | closed | verified with `blog_public` turned on for the check — see §5 |
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
| No undocumented external services | The plugin contacts nothing at all |
| No unsolicited tracking | None |
| Correct headers | Name, URI, description, version, requires, author, licence, text domain, domain path |
| Upsell restraint | One dismissible notice, once, on OxyArea's own screens and the plugins list, which disappears permanently after the wizard is used |

## 5. What has not been verified, and what that means

Written plainly, because an unverified thing is not a working thing.

1. **The password reset flow has not been run end to end.** The code is there and
   its pieces are exercised, but nobody has clicked a link in a real email on this
   test bed, because the bed has no outbound mail. Until somebody does, the claim
   "a customer can reset their own password" is untested. *This is the largest
   gap in the report.*
2. **Profile editing has not been exercised over HTTP.** Same shape of gap,
   smaller consequence.
3. **The PHPUnit `integration` and `security` suites are empty.** Their ground is
   covered by `tests/manual/smoke.php` (109 checks) and the four flow scripts (48
   checks), but those are scripts with a pass counter, not suites, and they do not
   run in CI. Nothing regressed silently during six sprints because they were run
   by hand every time; that will not survive a second contributor.
4. **The sitemap needs `blog_public` on to exist at all.** WordPress serves no
   sitemap on a site set to discourage search engines, which the test bed is. The
   flow script turns it on for two checks and off again. Worth knowing, because
   without that the channel looks fine only because it is absent.
5. **No rate limiting on sign-in.** The same as WordPress itself, and out of scope
   in the specification, but more pointed on a site whose purpose is a private
   area. A decision to take, not a defect.
6. **One person has reviewed this.** A security review by somebody who did not
   write the code has not happened.

## 6. Before submitting

Three things, none of them code:

1. **Run the password reset flow on a site that can send mail.** It is the one
   step of the definition of done that is unproven, and it is the step a customer
   uses when they are already annoyed.
2. **Complete the Name Lock checklist** in `docs/00_NAMING_CLEARANCE.md` §10. Four
   of its seven boxes are ticked by the indexed searches recorded there; the
   direct EUIPO / TMview / WIPO similarity check is a manual step nobody has done,
   and the slug is only really settled by approval itself.
3. **Add screenshots and the `Screenshots` section to `readme.txt`.** Not required
   for acceptance, and the first thing anybody reads on the directory page.

Then submit. The slug becomes real on approval, and the branding strings are
centralised in `Brand` precisely so that a pre-launch rename would still be cheap
if it came to that.

## 7. The numbers

| | |
|---|---|
| Files in the distributed package | 104 |
| Unit tests | 225, no WordPress required |
| Checks inside a real WordPress | 109 |
| Checks over HTTP | 48, across four flow scripts |
| `php -l` | clean |
| PHPCS, WordPress standards | clean |
| PHPStan level 8 | clean |
| Plugin Check | 0 errors, 1 warning |
| PHP notices during a full run | none; no `debug.log` is created |

## 8. What comes next

The master prompt says to stop here. PRO begins only once the free core's APIs
are stable, and the extension points PRO will need — `oxyarea_register_services`,
`oxyarea_audience_providers`, `oxyarea_access_decision`, the audience and widget
interfaces — exist and are exercised, but have never been used by a second
plugin. The first thing PRO will teach us is which of them is wrong.
