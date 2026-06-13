# Champlin AI Internal Linker

Semantic internal-link suggestions for WordPress, powered by OpenAI embeddings. Free and GPL-licensed, with no per-seat fees.

[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue.svg)](https://wordpress.org/)

## What it does

- Indexes your posts into vector embeddings (default: `text-embedding-3-small`, 1536d, ~6KB/post)
- Surfaces semantically relevant internal-link suggestions inside the block editor sidebar
- Auto-picks an anchor phrase from the sentence in your draft closest to the target post
- One click to insert; acceptances are logged so the same target stops reappearing

## Architecture

- **PHP 8.1+** with strict types, PSR-4 (`Champlin\InternalLinker\`), Composer autoloaded
- **WordPress 6.4+** Gutenberg-first; sidebar is React via `@wordpress/scripts`
- **MySQL** — two custom tables (`cil_embeddings`, `cil_suggestion_log`); embeddings stored as packed float32 BLOBs
- **Action Scheduler** for background indexing
- **Tailwind** for admin pages (built, not CDN)
- No vector DB; cosine ranking happens in PHP against batched embeddings

```
champlin-ai-internal-linker/
├── champlin-ai-internal-linker.php   # Bootstrap, plugin header
├── composer.json
├── package.json
├── tailwind.config.js
├── phpcs.xml
├── phpunit.xml.dist
├── uninstall.php
├── readme.txt                         # WP.org-format readme
├── src/
│   ├── Plugin.php                     # Container + hook registration (single place)
│   ├── Embeddings/                    # ProviderInterface, OpenAIProvider, ProviderFactory
│   ├── Storage/                       # VectorStore, SuggestionLog, Schema
│   ├── Similarity/                    # CosineCalculator
│   ├── Engine/                        # SuggestionEngine, AnchorExtractor
│   ├── Indexing/                      # IndexQueue, BulkIndexer, ContentNormalizer
│   ├── REST/                          # SuggestionsController, IndexController, BaseController
│   └── Admin/                         # SettingsPage, IndexerPage, EditorAssets
├── includes/views/                    # Server-rendered admin views (settings, indexer)
├── assets/
│   ├── editor/index.js                # Gutenberg sidebar (React)
│   ├── admin/                         # Tailwind source + indexer.js
│   └── dist/                          # Built output (gitignored)
├── languages/                         # .pot files
└── tests/                             # PHPUnit + Brain Monkey
```

## Development

```bash
composer install
npm install

# Watch the admin Tailwind CSS:
npm run watch:admin

# Build editor + admin bundles:
npm run build

# Run unit tests:
composer test

# Lint:
composer lint
```

### Local dev install

Symlink into a WordPress dev install:

```bash
ln -s "$(pwd)" /path/to/wordpress/wp-content/plugins/champlin-ai-internal-linker
```

Activate via wp-admin → Plugins, then enter an OpenAI key in **AI Linker → Settings**.

## Distribution

- **GitHub:** tagged releases with the full source tree; users get auto-updates via [yahnis-elsts/plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker).
- **WordPress.org:** `./scripts/build-wp-org.sh` produces a stripped variant (PUC removed, dotfiles excluded) ready for SVN check-in.

## Performance targets

- Single-post index: <2s end-to-end (dominated by API latency)
- `/cil/v1/suggestions/{post_id}` p95 on a 1K-post site: <300ms
- Full re-index of 1K posts: <15 min at default Action Scheduler concurrency

## Hard rules (Plugin Check + WP.org review)

- All file ops via `WP_Filesystem` (none currently needed — but if added, no direct `mkdir`/`unlink`)
- Every `$_POST`/`$_GET`/`$_SERVER` access goes through `wp_unslash()` BEFORE sanitization
- No short echo tags (`<?= … ?>`) — use `<?php echo esc_html(...); ?>`
- Build script excludes `.git`, `.github`, `.gitignore`, `.DS_Store`, `.editorconfig`
- Every REST route checks capability + nonce
- All `$wpdb` calls use `prepare()` — no string interpolation

## License

GPLv2 or later © Champlin Enterprises. See [LICENSE](./LICENSE).
