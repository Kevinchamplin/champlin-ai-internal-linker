# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
