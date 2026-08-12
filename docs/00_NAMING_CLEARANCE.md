# OxyArea — Naming Clearance and Name-Lock Plan

**Candidate:** `OxyArea`  
**Assessment:** GO, subject to the final external lock actions below.  
**Date:** 2026-08-11

> This is a practical technical/commercial clearance, not a legal opinion. No web search can guarantee the absence of an unindexed or jurisdiction-specific trademark.

## 1. Locked technical identity

Use consistently:

- Brand: `OxyArea`
- Display name: `OxyArea – Private Client Area & User Portal`
- Expected WordPress.org slug: `oxyarea`
- Directory: `oxyarea`
- Main file: `oxyarea.php`
- Text domain: `oxyarea`
- PHP namespace: `OxyArea`
- Procedural prefix: `oxyarea_`
- Constant prefix: `OXYAREA_`
- REST namespace: `oxyarea/v1`
- Gutenberg namespace: `oxyarea/...`

Do **not** use the generic technical prefix `oxy_`.

WordPress recommends globally accessible names to use a unique prefix of at least 4 letters, preferably 5 or more.

## 2. WordPress.org checks

Official WordPress documentation establishes that:

- plugin names/slugs must respect trademarks and other project names;
- original branding is recommended;
- the slug is generated from the submitted Plugin Name;
- after approval, the slug cannot be changed like a normal display name;
- names cannot be reserved with an empty/skeleton plugin;
- a complete plugin must exist at submission time.

Exact indexed searches performed for:
- `OxyArea`
- `oxyarea`
- `Oxy Area`
- `site:wordpress.org/plugins OxyArea`
- `site:wordpress.org/plugins oxyarea`

No existing WordPress.org plugin with the exact name/slug `OxyArea` / `oxyarea` was found in the indexed results on the assessment date.

### Result
**PASS — no indexed exact WordPress plugin collision found.**

## 3. General software/product collision checks

Exact searches were performed for `OxyArea` across software/product contexts and indexed package ecosystems.

No exact commercial software product, WordPress plugin, SaaS brand or app called **OxyArea** was found in the searched indexed results.

Non-blocking textual uses discovered:

### OxyPlotCli
A PowerShell plotting module exports an alias named `oxyarea`.  
This is a command alias, not a software brand/product called OxyArea.

### Dassault Systèmes CATIA material
`OxyArea` appears as an engineering example/parameter name in training documentation.  
This is not a commercial product name.

### Retail
The phrase “Oxy Area Rug” exists as a rug/product description.  
Different market and different use.

### Result
**PASS — no discovered exact software-brand collision.**

## 4. Package/repository ecosystem screening

Exact indexed searches found no `OxyArea` package/project in:

- GitHub exact project/repository searches surfaced by the search engine;
- npm;
- Packagist;
- PyPI.

This is a collision screen, not a namespace reservation.

## 5. Trademark screening

Exact web-indexed searches were performed for:

- `OXYAREA trademark`
- `OXY AREA trademark`
- EUIPO-targeted results
- WIPO-targeted results
- UIBM-targeted results
- USPTO-targeted results

No exact indexed `OXYAREA` trademark result was found.

Relevant Nice classes for final professional clearance:

- **Class 9** — downloadable computer software.
- **Class 42** — SaaS/software development/hosted software services, if applicable.

### Important
For a product intended to become a long-term commercial brand, final name lock should include a direct manual similarity search in:
- EUIPO / TMview;
- UIBM / TMview;
- WIPO Global Brand Database;
and, if commercially justified, a trademark professional.

An exact-name search alone does not eliminate conflicts with **similar** marks.

## 6. Similarity / confusion considerations

The prefix `Oxy` is used by multiple unrelated products and by third-party products in the Oxygen Builder ecosystem.

OxyArea must therefore:

- always use the full compound brand `OxyArea`;
- never imply affiliation with Oxygen Builder;
- never use Oxygen Builder visual identity;
- avoid marketing language such as “the official Oxy/Oxygen area plugin”;
- use a distinctive OxyArea icon/logo.

The exact compound `OxyArea` is substantially more distinctive than a generic name such as “Private Client Area”.

## 7. Rejected alternatives

During screening, generic alternatives such as these were considered less attractive:

- OxyPortal
- OxyAccess
- OxyGate
- OxyHub

They are more collision-prone/generic or already have identifiable usage elsewhere.

**OxyArea remains the preferred candidate.**

## 8. Product website and domain strategy

OxyArea will **not** use a dedicated domain.

The plugin belongs to the OxyWP product family and will be published, documented, licensed and commercially distributed through:

- `oxywp.com`

Recommended product URLs:

- `https://oxywp.com/oxyarea/`
- `https://oxywp.com/plugins/oxyarea/` (alternative structure)
- documentation: `https://oxywp.com/docs/oxyarea/`
- support: `https://oxywp.com/support/`
- PRO account/licensing: under the OxyWP domain.

Do **not** register `oxyarea.com`, `oxyarea.it` or other dedicated OxyArea domains as a project requirement.

Domain ownership of `oxyarea.*` is therefore **not a Name Lock criterion**. The naming review is instead focused on:
- WordPress.org slug/name conflicts;
- software/product name conflicts;
- trademark similarity risks;
- clear OxyWP/Oxysoft brand ownership and presentation.

## 9. WordPress slug strategy — correction

Do **not** submit an empty skeleton only to reserve the slug. WordPress.org explicitly requires a complete plugin and states that names cannot be reserved for future use.

Correct strategy:

1. Build a compact but fully useful OxyArea FREE MVP.
2. Run Plugin Check and security review.
3. Submit it to WordPress.org as soon as it is complete.
4. On approval, the slug becomes the real product slug.
5. Continue PRO development afterward.

## 9b. Register check, 2026-08-12 — what was done and what was found

The Name Lock's fourth box asked for a direct check against the trademark
registers. Here is what came of attempting it, in full, because half of it is a
negative result about the method rather than about the name.

### What could be checked

**UIBM, the Italian register** — searched directly, and it answered.

| Query | Field | Result |
|---|---|---|
| `oxyarea` | title/denomination | no results |
| `oxyarea` | title and description | no results |
| `oxiarea` | title/denomination | no results |
| `oxysoft` | title/denomination | no results |
| `ferrari` (control) | title/denomination | results — the search works |
| `oxygen` (control) | title/denomination | results — the search works |

The controls matter. A register that answers "nothing found" to a malformed
query looks exactly like a register that answers "nothing found" to a clean one,
and only a term that must return something tells the two apart.

Worth noting in passing: `oxysoft` returns nothing either. The company name is
not a registered mark in Italy.

**WordPress.org** — the slug `oxyarea` is unclaimed (the plugin information API
returns "Plugin not found"). A search for `oxy` across the whole directory
returns **four** plugins whose name begins with Oxy, the largest at 500 active
installs, and one of them — `oxy-relogin-window` — has nothing to do with
Oxygen. So the directory has approved an unrelated Oxy-prefixed name before.
That is a precedent, not a permission.

**Indexed search** — no trademark called OxyArea surfaces anywhere, in any
jurisdiction, under any spelling tried.

### What could not be checked, and why it is not a matter of trying harder

TMview, EUIPO eSearch and the WIPO Global Brand Database were all unreachable
by script. Not slow, not awkward — deliberately closed:

- TMview resets the connection on every API path, and its own client code
  contains the string `Captcha could not be verified successfully`, so a
  captcha-validated session is required before a search is answered at all.
- WIPO's Global Brand Database serves a proof-of-work challenge (altcha) and
  falls through to its front-end shell for every API path tried.
- The third-party mirrors that index the same data — Justia, uspto.report —
  are behind Cloudflare.

These are the three registers the checklist names. **The box stays unticked.**
Completing it needs a person at a browser for fifteen minutes, or a
professional. The searches to run are: `oxyarea`, `oxy area`, `oxiarea`,
`oxyarea` as a phonetic/fuzzy search, in classes **9** (software) and **42**
(software as a service), across EUIPO, WIPO and TMview's participating offices.

### The finding that matters, and it is not about OxyArea

Soflyy, who make the Oxygen page builder for WordPress, publish a trademark
policy at `oxygenbuilder.com/brand/`. It says, verbatim:

> Do not use "oxygen" or "oxy" in product names.

and, of the WordPress Foundation's trademark policy:

> WE WILL ENFORCE THE EXACT SAME POLICY.

Read carefully, three things are true at once.

**It is a private policy, not a law.** No registered "Oxygen" mark belonging to
Soflyy could be found; the registered OXYGEN in class 9 (USPTO 87799894) belongs
to Oxygen, Inc., a fintech, and is unrelated to both parties.

**It is nevertheless the sharpest risk this name carries**, because of where it
would land. WordPress.org's review team acts on trademark complaints, and the
slug cannot be changed after approval. The exposure is asymmetric: a complaint
costs Soflyy an email and costs us the name.

**It applies to the whole family, not to this plugin.** OxyProfit, OxyArea,
OxyWait and `oxywp.com` all share the prefix. A decision here is a decision
about all of them.

Against that, the case for keeping the name is not weak:

- "Oxy" here is the stem of **Oxysoft**, the company's own name. That is a
  materially different position from a third party choosing "Oxy" because
  Oxygen is popular.
- OxyArea is not an Oxygen addon and does not present itself as one. The
  WordPress Foundation policy that Soflyy adopts is aimed at products that
  trade on a mark; the concern it exists to address is not present here.
- The policy's softer sentence — "use of 'oxy' is also discouraged but not
  expressly prohibited" — is about top-level domains, and `oxywp.com` already
  exists.

**This is a commercial judgement, not a technical one, and it is not the
author's to make.** What has changed is that the risk now has a name and an
address instead of being an unknown register entry.

## 10. NAME_LOCK gate

Set `NAME_LOCKED = true` only when all items are true:

- [x] Exact WordPress.org collision search clean. Slug `oxyarea` unclaimed;
      four Oxy-prefixed plugins exist, none in this product's space (§9b).
- [x] Exact general software search clean.
- [x] GitHub/package ecosystem search clean.
- [ ] Direct EUIPO/TMview/UIBM/WIPO similarity check completed. **UIBM done and
      clean; EUIPO, TMview and WIPO are closed to scripted access and remain
      outstanding — see §9b.** Read §9b before ticking this: it also records a
      separate and larger question, Soflyy's "do not use oxy in product names".
- [ ] OxyArea product page structure confirmed under `oxywp.com`.
- [ ] OxyArea FREE MVP accepted by WordPress.org with desired slug `oxyarea`.
- [ ] Optional but recommended: trademark filing decision taken before substantial marketing spend.

Until WordPress.org approval occurs, use `OxyArea` in code because the current practical screening is green, but centralize branding strings so a hypothetical pre-launch rename would remain cheap.

## 11. Sources

Official WordPress:
- https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/
- https://developer.wordpress.org/plugins/wordpress-org/planning-submitting-and-maintaining-plugins/
- https://developer.wordpress.org/plugins/plugin-basics/best-practices/
- https://developer.wordpress.org/plugins/wordpress-org/common-issues/

Trademark databases:
- https://www.wipo.int/en/web/global-brand-database
- https://euipo.europa.eu/eSearch/
- https://uibm.mise.gov.it/index.php/en/banche-dati/tmview

Domain:
- https://www.nic.it/
- https://www.verisign.com/news-insights/registration-data-access-protocol/help/

Representative non-brand exact uses:
- OxyPlotCli PowerShell module (`oxyarea` alias)
- CATIA/Design of Experiments material (`OxyArea` parameter)
