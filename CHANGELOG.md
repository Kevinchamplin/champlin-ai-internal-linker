# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed (2026-07-11, wp-org-submission-plugin-uri) [0.25h]
- **Dead `Plugin URI` fixed** in champlin-ai-internal-linker.php: `champlinenterprises.com/ai-internal-linker` (404) → `https://linkweaver.app/` (live product home, already the canonical URL in readme.txt + the submission runbook). Rebuilt the WP.org zip.
- **Reconciled stale refs** in docs/WP_ORG_SUBMISSION.md (still said v1.2.0 / 68 files / ~100K → now v1.3.2 / 73 files / ~110K). Submission unblocked now that the first plugin (`champlin-pre-flight-audit`) is live on WP.org — WP.org allows only one submission in review at a time.

### Changed (2026-06-13, default-post-types-posts-and-pages) [0.25h]
- **Default `post_types` is now `['post','page']`** (was `['post']`). Page-based / brochure / Elementor sites keep their linkable content in pages, so `post`-only indexed nothing useful out of the box. Existing installs with a saved selection are unaffected. v1.3.2.

### Fixed (2026-06-13, hosted-ai-indexing) [0.5h]
- **Hosted-AI installs couldn't index.** `ProviderFactory::is_configured()` only checked for a site-level OpenAI key and returned false on hosted-AI installs (Premium, no site key), so `IndexQueue` bailed before embedding → 0 vectors stored, suggestions dead. Now also returns true when the `cil_provider` filter supplies a provider (the hosted-AI override). Bumped to 1.3.1. Bit the ccrstables pilot (jobs "completed" but stored nothing).

### Added (2026-06-13, elementor-support) [2h]
- **Elementor extraction (v1.3).** New `ElementorExtractor` (src/Indexing) parses a page's `_elementor_data` element tree for text + internal-link anchors (headings, text-editor rich text with inline `<a href>`, button link controls), skipping video/embed URLs. JSON-tree parse rather than render — fast and reliable inside background Action Scheduler jobs.
  - `ContentExtractor` now appends Elementor content (builder pages usually have empty `post_content`), so Elementor text is embedded for suggestions.
  - `LinkGraphScanner` now appends Elementor links and no longer skips builder pages with empty `post_content` — so Elementor pages stop being counted as orphans and the internal-link "structure" score reflects reality. Propagates through `OrphanReport` and the Pro audit's `structure_score` / `orphan_count` / `top_orphans`.
  - 6 unit tests (`ElementorExtractorTest`). Bumped to 1.3.0.

### Changed (2026-06-13, wp-org-submission-packaging-compliance) [1.5h]
- **Relicensed MIT → GPLv2-or-later** (WP.org norm; safest for review). Updated plugin header `License:`/`License URI:` in champlin-ai-internal-linker.php, the readme.txt header + body, composer.json, package.json, README.md, and replaced LICENSE with the full GPLv2 text.
- **Fixed two build-script packaging bugs** in scripts/build-wp-org.sh that would have shipped a broken/bloated zip:
  - The unanchored `--exclude 'dist/'` was also matching `assets/dist/`, silently stripping the built Gutenberg sidebar JS + admin CSS from the package (the plugin then enqueues nothing — sidebar fails to load). Anchored it to `/dist/` and anchored the other root-only excludes.
  - The zip was also bundling stray root-level `*.mjs` Casefile/test scripts, `docs/`, and `CHANGELOG.md`. Added excludes for `*.mjs`, `*.log`, `/docs/`, `CHANGELOG.md`, `.idea/`, `.vscode/`.
  - Added two regression guards: fail the build if `assets/dist/editor/index.js` / `index.asset.php` / `assets/dist/admin/admin.css` are missing, or if any `*.mjs` / `phpcs.xml` / `phpunit.xml.dist` leaked in.
- **readme.txt: removed the competitor trademark** "Link Whisper" from the Description (reworded to "without per-seat fees or recurring subscriptions") — competitor brand names in readme.txt are a review risk.
- **Plugin Check hardening (warnings → clean), no functional change:** sanitized `$_GET['export']` with `sanitize_key(wp_unslash())` in InsightsPage; added documented `phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared` directives to the 6 constant-table-name queries in InsightsReport; debug-gated the two `error_log()` calls (IndexQueue, AnchorExtractor) behind `WP_DEBUG`.
- Verified: 29/29 unit tests green; `php -l` clean on all touched files; final zip = 72 files / 107K, GPL header, PUC fully stripped, no node_modules/.git/dev files, built assets present.

### Added (2026-05-20, free-to-pro-upgrade-panel) [1h]
- **One-click Pro install inside Free's Settings page.** New `UpgradeToProPanel` (src/Admin/UpgradeToProPanel.php) renders a Premium / Agency pricing cards block + an "Already bought Pro?" expandable form. Submitting the form POSTs the customer's license key to `admin-post.php` → Free validates it against `linker-api.champlinenterprises.com/api/license/validate`, fetches the license-gated download URL from `/api/updates/metadata`, runs WordPress's stock `Plugin_Upgrader` to install + activate Pro, then persists the license key into `cilp_license_key` so Pro picks it up on first run.
- Security: gated on `current_user_can('install_plugins')` + nonce; download URL is locked to the `linker-api.champlinenterprises.com` host so we can't be tricked into pulling arbitrary zips; rendering hides the panel entirely once Pro is active.
- Panel uses Free's existing CSS tokens (cil-card / cil-pill / cil-btn) + a small inline `<style>` for the pricing card grid.
- **readme.txt "External services" section rewritten** to disclose both endpoints per WP.org guidelines: OpenAI Embeddings (Free, when you save a post) and LinkWeaver license server (only when the customer pastes a license key). Both opt-in, both documented with "what it sends, when it's called, how to disable".
- Plugin Check still 0 ERRORs after these additions. Tests still 29/29 green. Submission-ready zip rebuilt at `dist/champlin-ai-internal-linker.zip` (100K, SHA-256 `68a6d1d2d5be274f9552aa33a72bf3223ea4273fd26059d5a6e50633b7e12f65`).

### Fixed (2026-05-20, wp-org-plugin-check-pass) [1h]
- **Cleared all 84 Plugin Check ERRORs so the plugin can be submitted to WordPress.org.** Final state: 0 ERRORs / 48 WARNINGs (warnings don't block submission). 29 unit tests still green.
- Renamed text domain from `champlin-internal-linker` → `champlin-ai-internal-linker` to match the WP.org slug exactly (eliminates 206 false-positive `TextDomainMismatch` errors).
- Added `/* translators: */` comments above every printf-style i18n call with placeholders (20 sites).
- Switched multi-placeholder i18n strings from positional `%s` / `%d` to ordered `%1$s` / `%2$d` (9 sites in reports.php, insights.php, indexer.php).
- Wrapped 12 `OutputNotEscaped` echo sites with `esc_html()` / `esc_attr()` / cast-to-int, and 5 `ExceptionNotEscaped` `throw new Exception($message)` calls with `esc_html()` (OpenAIProvider + ProviderFactory).
- Wrapped 5 `wpdb->prepare(...)` query sites with `phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,InterpolatedNotPrepared,DirectDatabaseQuery.DirectQuery,NoCaching` blocks across VectorStore, SuggestionLog, InsightsReport. All suppressed with a justification comment — the interpolated values are always constants returned by `Schema::table_*()` helpers, never user input. The actual bind values use `%d` / `%s` placeholders correctly.
- `parse_url()` → `wp_parse_url()` in `includes/views/insights.php`.
- CSV-export `fopen('php://output')` + `fputcsv` + `fclose` block in `src/Reports/InsightsReport.php:277` suppressed for the three relevant filesystem rules — `php://output` is a PHP stream for streamed downloads, not a real file write; `WP_Filesystem` does not have a streamed equivalent.
- Added `if (!defined('ABSPATH')) { exit; }` direct-access guard at the top of `src/Plugin.php` (the lone remaining file that lacked one).
- `scripts/build-wp-org.sh` now excludes `.plugin-check-*` helper files so they can't accidentally end up in the WP.org zip.
- **The submission-ready zip is `dist/champlin-ai-internal-linker.zip` (93K, SHA-256 `98a9568d77ff7300ce559bdc9cdd7bfd949b913223dbf4602c1db455df8e727f`).**

## [1.2.0] - 2026-05-20

### Added (2026-05-20, insights-roi-dashboard) [2h]
- **Insights / ROI dashboard** at Tools → AI Linker → Insights — links inserted, editor time saved (5 min/link × accepts), pages improved, OpenAI cost vs editor-time-value ROI multiple, 8-week SVG bar chart of accepted-insert activity, top-10 linked target posts, most-active editors table with avatars + time-saved-per-author, recent activity feed.
- **CSV export** of the full activity log from the Insights page (`?export=csv` with nonce; streams via fputcsv to php://output).
- **Inline-wrap link insertion** in the Gutenberg sidebar — finds the suggested anchor in the existing draft and wraps THOSE words in `<a href>`. Block walker recurses through paragraphs/headings/lists/quotes/columns/groups + their innerBlocks. Skips matches inside existing anchors or HTML attributes. Highlights the modified block + shows a Gutenberg snackbar confirming the link. Fallback to "Related:" paragraph + info notice when the anchor isn't found anywhere.
- New extension hook `cil_settings_render_extra` (action) for Pro and add-ons to inject settings panels.
- New extension hook `cil_provider_summary` (filter) so Pro can tell Free's Settings page that hosted AI is active.

### Fixed (2026-05-20, calibration-+-inline-wrap-anchor-cleanup) [0.5h]
- Default similarity threshold 0.75 → **0.55** — calibrated to OpenAI text-embedding-3-small on real WordPress content (genuinely related posts cluster 0.55–0.75; the 0.75 default returned zero suggestions in practice).
- `AnchorExtractor::trim_to_phrase()` drops the ellipsis when truncating long sentences and trims at the nearest word boundary, so the returned anchor is always a literal substring of the source content (required for the inline-wrap path to find it).
- Sidebar `RangeControl` defensively coerces `cilEditor.threshold` to a real `Number` — fixes a v1.0 edge case where `wp_localize_script` would hand back a stringified float that rendered the slider blank and made the API call send `threshold=undefined`.
- Reports → Orphans now ships an inline "How to fix" workflow per orphan: top-3 inbound-candidate posts computed from existing embeddings, each with a one-click "Open in editor" link that auto-opens the AI Linker sidebar via `?cil_open=1`.

### Added (2026-05-20, sprint-2-day-1-hook-surface) [0.75h]
- **Extension hooks** for the Pro plugin and community add-ons. Six new hooks comprising the public Pro API contract:
  - `cil_plugin_loaded` (action) — Pro registers its services after Free is wired up.
  - `cil_extra_excluded_ids` (filter) — further narrow which candidates can appear.
  - `cil_rank_results` (filter) — boost / re-rank / filter cosine-ranked candidates (Money Pages, focus-keyword boost).
  - `cil_suggestion_row` (filter) — attach extra per-row display data (badges, click predictions).
  - `cil_provider` (filter) — swap in `HostedProvider` when a Premium license is present.
  - `cil_settings_sanitized` (filter) — Pro extends the settings array.
- New `docs/HOOKS.md` documents the contract as the public Pro API surface. Hook names + signatures versioned at MAJOR; removals/renames bump 1.x → 2.0.
- All 29 unit tests still green. No behavior change for the Free plugin (every hook is a pure `apply_filters` / `do_action` callout with default pass-through semantics).

### Added (2026-05-20, sprint-1-free-tier-parity) [2.5h]
- **Orphan Pages report** — new Reports admin sub-page lists every published post with zero internal links pointing to it. Backed by `LinkGraphScanner` (regex parse + `url_to_postid` resolution) and `OrphanReport`. 6h transient cache, auto-invalidated on `save_post` / `before_delete_post`. Manual "Re-scan now" button + nonce-protected.
- **Ignore lists** — `ignored_post_ids` (CSV of post IDs) and `ignored_term_ids` (CSV of category term IDs, includes children) added to the Settings screen. Merged into `SuggestionEngine` exclusion set at suggestion time.
- **Target keyword reader** — `TargetKeywordReader` reads focus keyword from Yoast SEO (`_yoast_wpseo_focuskw`), Rank Math (`rank_math_focus_keyword`), All in One SEO (`_aioseo_keyphrases` JSON or `_aioseo_keywords` legacy), and SEOPress (`_seopress_analysis_target_kw`). First non-empty wins. Exposed in REST `/cil/v1/suggestions` as `target_keyword` + `target_keyword_source`.
- **Page-builder content extraction** — `ContentExtractor` runs `do_shortcode()` before normalization so Divi (`[et_pb_*]`), WPBakery (`[vc_*]`), and any other shortcode-based content is included in the embedding. Sets up `$post` global / `setup_postdata()` then restores; falls back to raw content on Throwable or if expansion shrinks content >70%.
- **REST routes** — `GET /cil/v1/reports/orphans` and `POST /cil/v1/reports/rescan`, both gated on `require_admin` (`manage_options` + nonce).
- **Tests** — `TargetKeywordReaderTest` (6 methods) + `LinkGraphScannerTest` (5 methods). New WP function stubs (`wp_parse_url`, `url_to_postid`) declared once in `tests/bootstrap.php`. **Total: 29 tests / 49 assertions, all green.**
- **Version**: 1.0.1 → 1.1.0 (MINOR — new features, no breaking changes).

### Fixed (2026-05-20, anchor-extractor-batching) [1h]
- AnchorExtractor was making ~100 sequential OpenAI calls per suggestion fetch (one `embed()` per source sentence per target). Dogfood on crm.champlinenterprises.com with 82 posts measured 104,970 ms for one call.
- Refactored: now batches all source sentences into a single `embed_batch()` request, and uses the target post's already-stored full-content vector from VectorStore for ranking instead of re-embedding its title+excerpt.
- Added 12-hour transient cache keyed on `content_hash` for source-sentence vectors — subsequent suggestion calls on the same draft are <50 ms.
- Added `ProviderInterface::embed_batch(array $texts): array` and `OpenAIProvider::embed_batch()`.
- `OpenAIProvider::embed()` now delegates to `embed_batch([$text])` for a single code path.
- Sane upper bound: sentences capped at 80 per source post to keep batched API calls fast on very long drafts.

### Added (2026-05-20, initial-scaffold) [3h]
- Scaffold complete plugin per `/wp-plugin` skill workflow + senior-grade engineering scope.
- Stack: PHP 8.1+ (strict types, PSR-4 under `Champlin\InternalLinker\`), WP 6.4+, MySQL, React (`@wordpress/scripts`), Tailwind, Action Scheduler.
- 26 PHP files across `src/{Admin,Embeddings,Engine,Indexing,REST,Similarity,Storage}` with constructor injection and hooks registered exclusively in `Plugin::register()`.
- Embeddings: `ProviderInterface` + `OpenAIProvider` (wp_remote_request, 20s timeout, 3 retries, exponential backoff) + `ProviderFactory`.
- Storage: `Schema` with `dbDelta` migrations for `cil_embeddings` (packed float32 BLOB) + `cil_suggestion_log`. `VectorStore` with generator-based candidate paging. `SuggestionLog` for acceptance tracking.
- Indexing: `ContentNormalizer` (block grammar + shortcodes + HTML stripped, SHA-256 hash, sentence-boundary chunking). `IndexQueue` (save_post → Action Scheduler with content-hash skip). `BulkIndexer` (paginated batches, progress in `cil_bulk_progress` option).
- Similarity: `CosineCalculator` (pure PHP `similarity`, `rank`, `mean_pool`).
- Engine: `SuggestionEngine` + `AnchorExtractor` (sentence-level cosine against target title+excerpt, falls back to title phrase).
- REST: `BaseController` with capability + nonce enforcement. `SuggestionsController` (GET + accept). `IndexController` (start + progress).
- Admin: `SettingsPage` (WP Settings API + Tailwind view), `IndexerPage` (live progress polling), `EditorAssets` (enqueue Gutenberg sidebar).
- React sidebar at `assets/editor/index.js` — threshold slider, one-click insert with `rawHandler` block injection, acceptance POST.
- Tailwind 3 admin CSS with `preflight: false` (don't clobber wp-admin).
- Tests: PHPUnit 10 + Brain Monkey — `CosineCalculatorTest` + `ContentNormalizerTest`, **18 tests / 30 assertions / all green**.
- Linting: `phpcs.xml` with WPCS 3 + PHPCompatibilityWP + forbidden-functions rule (eval/extract/create_function).
- `uninstall.php` drops both tables, sweeps `cil_*` options + transients, unschedules AS jobs.
- WP.org submission ready: `readme.txt` (WP.org format), `LICENSE` (MIT), `scripts/build-wp-org.sh` strips PUC + dotfiles + dev configs and runs `composer install --no-dev` + `npm run build`.
- Auto-update from GitHub releases via PUC v5 — gated to admin/cron/WP-CLI requests.
- First build: editor bundle 3.19KB minified, admin CSS 3.27KB minified.

## [1.0.0] - TBD
- Initial public release.
