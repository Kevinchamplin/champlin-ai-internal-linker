# WordPress.org Submission Runbook

Step-by-step playbook for submitting the Free plugin to the WordPress.org plugin directory. Read top to bottom; do steps in order.

> **STATUS: SUBMITTED 2026-07-11 — Awaiting Review.** Automated Plugin Scanning: **Pass**.
> Slug assigned: `champlin-ai-internal-linker`. Review email goes to **kevin@815media.com** (the
> wordpress.org account email) with subject "Review in Progress: Champlin AI Internal Linker" —
> whitelist `plugins@wordpress.org`. Only one submission may be in review at a time. Public
> Contributor on the listing is `champlinenterprises` (Champlin-branded, not 815).

---

## The file you upload

**`dist/champlin-ai-internal-linker.zip`** — built locally via:

```bash
cd ~/Developer/champlin-ai-internal-linker
bash scripts/build-wp-org.sh
```

The build script:

- Strips the GitHub auto-update library (PUC) — WP.org has its own update mechanism
- Excludes hidden files (`.git`, `.DS_Store`, `.editorconfig`)
- Excludes dev configs (`phpcs.xml`, `phpunit.xml.dist`, `tailwind.config.js`)
- Excludes source bundles (`assets/editor/`, `assets/admin/`) and ships only the built `assets/dist/`
- Runs `composer install --no-dev` so `vendor/` ships without dev tooling
- Verifies no PUC references remain
- Runs `php -l` on every PHP file before zipping

**Current state of the zip:**

- Size: ~110K
- 73 files
- Plugin Check: **0 ERRORs**, 48 warnings (warnings don't block submission)
- 29 unit tests still pass

---

## Pre-flight checklist

Before you click "Submit" on WP.org:

- [ ] `bash scripts/build-wp-org.sh` ran successfully and produced `dist/champlin-ai-internal-linker.zip`
- [ ] Plugin Check passes with 0 ERRORs (verify by running it locally on a fresh WP install with `--slug=champlin-ai-internal-linker`)
- [ ] `readme.txt` "External services" section discloses OpenAI only — the LinkWeaver license server was removed in 1.3.3, so OpenAI is now the sole external service (verified 2026-07-17)
- [ ] `champlin-ai-internal-linker.php` plugin header has `Version: 1.3.4` and `Text Domain: champlin-ai-internal-linker` (slug-matching)
- [ ] No `error_log()` calls remain unguarded (warnings only — won't block, but reviewers may comment)
- [ ] You're submitting under the legal entity that will own the listing long-term (Champlin Enterprises LLC post-2026-06-01; pre-transition the listing will need to be re-papered)

---

## Submission

1. Go to <https://wordpress.org/plugins/developers/add/>
2. Sign in with your wordpress.org / make.wordpress.org account
3. Fill the form:
   - **Plugin Name:** `Champlin AI Internal Linker`
   - **Plugin URL:** `https://linkweaver.app/`
   - **Plugin Slug:** `champlin-ai-internal-linker` (auto-derived from the name; verify it matches)
   - **Plugin Description:** paste the "Description" section from `readme.txt`
   - **ZIP file:** upload `dist/champlin-ai-internal-linker.zip`
4. **Additional Information** field — paste the template at the bottom of this doc. This pre-empts the most common reviewer questions and shaves 1-2 weeks off the review cycle.
5. Click Submit
6. You'll get an automated email confirming receipt within minutes. The actual human review takes **2–4 weeks** (sometimes faster if the queue is light).

---

## After approval (the SVN part)

WP.org gives you an SVN repo at `https://plugins.svn.wordpress.org/champlin-ai-internal-linker/`. You commit releases there (not git). One-time setup:

```bash
# Check out the SVN repo to a sibling directory (NOT inside the git repo)
cd ~/Developer
svn co https://plugins.svn.wordpress.org/champlin-ai-internal-linker champlin-ai-internal-linker-svn
```

Layout once checked out:

```
champlin-ai-internal-linker-svn/
├── trunk/        ← always the latest stable
├── tags/         ← /tags/1.2.0/, /tags/1.3.0/, etc.
└── assets/       ← banner, icon, screenshots (don't ship in the zip itself)
```

**To publish 1.2.0 to WP.org:**

```bash
# 1. Build the WP.org zip (as above)
cd ~/Developer/champlin-ai-internal-linker
bash scripts/build-wp-org.sh

# 2. Copy the unzipped contents into trunk/
SVN=~/Developer/champlin-ai-internal-linker-svn
rm -rf $SVN/trunk/*
unzip -q dist/champlin-ai-internal-linker.zip -d /tmp/wporg-stage
cp -R /tmp/wporg-stage/champlin-ai-internal-linker/* $SVN/trunk/
rm -rf /tmp/wporg-stage

# 3. Tag the release
svn cp $SVN/trunk $SVN/tags/1.2.0

# 4. Commit (SVN will prompt for your wordpress.org password)
cd $SVN
svn add --force .
svn ci -m "Release 1.2.0"
```

WP.org auto-detects the `Stable tag: 1.2.0` line in `readme.txt` and serves that tag as the current release. Bump `Stable tag` BEFORE the SVN commit when you ship a new version, or users won't see the update.

---

## After approval (the assets part)

Banners + icon + screenshots live in `champlin-ai-internal-linker-svn/assets/` (the SVN repo, not the zip). They're cached aggressively by WP.org's CDN — file naming matters:

- `banner-772x250.png` and `banner-1544x500.png` — the wide banner above the plugin page
- `icon-128x128.png` and `icon-256x256.png` — the small square next to your plugin in search results
- `screenshot-1.png`, `screenshot-2.png`, … — referenced in `readme.txt` by number

We don't have these yet. Build them before the SVN commit so the listing page doesn't look sparse on day one.

---

## Auto-update flow once approved

- WordPress core polls WP.org for plugin updates twice per day
- When the `Stable tag` in `readme.txt/trunk` changes, every install gets an "Update available" notification
- No code changes needed in our plugin — WP.org owns the update side entirely

---

## When things go wrong

> **Review history (read this first).** Rejected twice, both fixed:
> **v1.3.3** removed an in-wp-admin Pro installer that downloaded + `Plugin_Upgrader->install()` +
> `activate_plugin()`'d the Premium add-on from `linker-api.champlinenterprises.com` — WP.org's
> "storefront" ban. A free WP.org plugin must NEVER download/install/activate a plugin from anywhere but
> WordPress.org; ship a link-out upsell only and keep any one-click installer in the self-hosted build.
> **v1.3.4** renamed the too-short `cil`/`CIL_` prefix to `chail`/`CHAIL_` (WP.org requires a prefix of
> **more than 4 characters**) and moved an inline `<style>` block into the enqueued admin stylesheet.
> Lesson for the NEXT CE plugin: pick a 5+ char unique prefix and `wp_enqueue_*` every asset from day one.

**Rejected for trademark:** unlikely — `champlin-ai-internal-linker` starts with our brand name. WP.org reviewers specifically warn against `wp-*` prefixes and verbatim competitor names. Our slug is clean.

**Rejected for missing readme.txt fields:** double-check `Tested up to`, `Requires PHP`, `Stable tag`, `License`. They must be in the WP.org format (not Markdown).

**Rejected for not disclosing external services:** the readme already discloses both. If a reviewer asks, point them to the "External services" section.

**Rejected for drive-by changes:** we don't modify any other plugin's options or any core WP options outside our own `chail_*` namespace.

**Re-submission during review:** if a reviewer asks for changes, fix, rebuild, and **upload the new zip via https://wordpress.org/plugins/developers/add/**. For a slug already in review this attaches the new version to the *same* ticket — it does NOT create a duplicate (confirmed 2026-07-17: re-upload of 1.3.4 → "Automated Plugin Scanning: Pass", still one ticket). Paste the updated "Additional Information" (below) during that upload, and also reply to the reviewer's email thread noting what changed. Separately: the account is limited to **one plugin in review at a time** — don't start a fresh submission for a *different* plugin until this one clears.

---

## "Additional Information" template

Paste this into the Additional Information field on the submission form:

```
Hi reviewers,

A few notes to pre-empt the usual questions:

1. EXTERNAL SERVICES
   The plugin connects to ONE external service, disclosed in
   readme.txt "External services" with what's sent, when, and how to
   disable. It is not contacted on plugin activation or during normal
   page loads:

   OpenAI Embeddings API (https://api.openai.com/v1/embeddings) —
   called only when a post is saved and when the user opens the AI
   Linker sidebar. The user provides their own API key; we do not
   proxy this traffic.

2. PREMIUM ADD-ON
   A paid Premium add-on exists (https://linkweaver.app/), distributed
   separately and NOT bundled in this zip. The Free plugin's Settings
   page shows a small, non-intrusive "Premium" section with pricing
   that links OUT to the website. The Free plugin never downloads,
   installs, or activates the add-on — users install it themselves via
   Plugins -> Add New -> Upload Plugin. No nag screens.

3. CAPABILITIES
   Every state-mutating REST route and admin action is gated on
   current_user_can('manage_options') plus a nonce. No public
   endpoints accept writes.

4. UNINSTALL
   uninstall.php drops both custom tables, sweeps all chail_* options
   + transients, and unschedules our Action Scheduler jobs. Zero
   residue.

5. NAMESPACE / PREFIX
   All PHP code lives under Champlin\InternalLinker\ (PSR-4). Every
   global identifier (constants, hooks, options, transients, the REST
   route namespace, script/style handles, and the two custom tables)
   uses the unique "chail" / "CHAIL_" prefix. No global functions are
   declared.

6. PLUGIN CHECK
   0 errors, 0 warnings against this build ("Checks complete. No
   errors found."), tested on a clean install with WP_DEBUG enabled.

7. WORDPRESS-VERSION COMPATIBILITY
   Tested on 6.4, 6.5, 6.6, 6.7, 7.0. Requires PHP 8.1+ (strict types).

Thanks for your time. Happy to answer anything via
kevin@kevinchamplin.com.

— Kevin Champlin / Champlin Enterprises
```

---

## Open items before submission

These aren't blockers but should be done first:

- [ ] Create the banner + icon + screenshot assets and commit to `assets/` in the SVN repo (after approval)
- [ ] Confirm `champlinenterprises` is the right wordpress.org Contributor handle (it's listed in readme.txt:2)
- [ ] Confirm Stable tag in readme.txt matches the Version in the plugin header (both should be `1.3.4` today)
- [ ] Decide on the legal entity timing: submitting now under 815 Media vs waiting for the 2026-06-01 transition to Champlin Enterprises LLC. The listing will need to be re-papered if submitted under the wrong entity.
