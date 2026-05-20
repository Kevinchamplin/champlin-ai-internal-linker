# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
