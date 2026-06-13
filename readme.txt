=== Champlin AI Internal Linker ===
Contributors: champlinenterprises
Tags: internal links, seo, embeddings, ai, suggestions
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Semantic internal-link suggestions powered by embeddings. One-click insert with auto-detected anchor text inside the block editor.

== Description ==

Champlin AI Internal Linker uses OpenAI embeddings to suggest semantically relevant internal links while you write — directly inside the block editor sidebar. One click inserts the link with an auto-detected anchor phrase pulled from your actual draft, not a generic heading.

Built for editorial teams who want better internal linking without per-seat fees or recurring subscriptions.

= Key features =

* Semantic similarity (cosine on OpenAI embeddings) — not keyword matching
* Auto-detected anchor phrase from the sentence in your draft closest to the target post
* Gutenberg sidebar with live filtering by similarity threshold
* Background indexing via Action Scheduler — no blocking on save
* Bulk re-index with progress UI for one-time backfills
* SHA-256 content hashing skips re-embedding unchanged posts
* Acceptance logging so accepted targets stop reappearing
* Free, GPL-licensed, no per-seat fees, no telemetry

= What it does NOT do =

* Doesn't modify your posts without explicit one-click approval
* Doesn't phone home or transmit any data outside the OpenAI request
* Doesn't require a license key, account, or signup with Champlin Enterprises

= External services =

This plugin connects to two external services. Both are opt-in — neither is contacted on plugin activation or during normal page loads.

**1. OpenAI Embeddings API** — https://api.openai.com/v1/embeddings

What it does: computes a vector representation of your post content so the plugin can find semantically related posts.

When it is called: only when you save a post (one request per indexed post) and when you open the AI Linker sidebar inside the block editor.

What it sends: the text content of the post being indexed plus the model name (e.g. text-embedding-3-small). It does NOT send drafts, private posts, or any user-account data.

How to disable: deactivate the plugin, or remove your API key in Settings.

* OpenAI Terms: https://openai.com/policies/terms-of-use
* OpenAI Privacy: https://openai.com/policies/privacy-policy

**2. LinkWeaver license server** — https://linker-api.champlinenterprises.com

What it does: validates an optional Premium license key + lets customers install the Premium add-on plugin (which is separately distributed) without leaving wp-admin.

When it is called: ONLY when a customer who has purchased Premium pastes their license key into the Premium section of the Settings page and clicks "Install Pro". The plugin never contacts this server on its own.

What it sends: the license key, the plugin slug, and the customer's site URL (so we can show the customer which sites they have activated).

How to disable: do not paste a Premium license key. The Free tier never contacts this server.

* Privacy + terms: https://linkweaver.app/legal

No other external services are contacted.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, OR install via the Plugins admin page.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **AI Linker → Settings** and enter your OpenAI API key.
4. Choose the post types to index and click "Save settings".
5. Visit **AI Linker → Re-index** and click "Start re-index" to build the initial vector store.
6. Open any post in the block editor — suggestions appear in the right-hand sidebar.

== Frequently Asked Questions ==

= Does this work with WordPress 7.0? =

Yes. Tested on WordPress 6.4 through 7.0.

= How much does the OpenAI API cost? =

Indexing 1,000 average posts with text-embedding-3-small costs roughly $0.02 at current OpenAI pricing. Suggestion fetches re-embed only the source post if its content has changed (skipped otherwise via SHA-256 hash).

= Will this modify my site automatically? =

No. v1 is manual-approval only. Suggestions are surfaced; you click "Insert" to add a link.

= Where is the embedding data stored? =

Inside your WordPress database, in two custom tables (`wp_cil_embeddings` and `wp_cil_suggestion_log`). Both tables are dropped automatically when the plugin is deleted.

= Is this really free? =

Yes. GPL-licensed, no per-seat tax, no premium upsell.

= Where can I report bugs or contribute? =

GitHub: https://github.com/Kevinchamplin/champlin-ai-internal-linker

== Screenshots ==

1. Block editor sidebar showing ranked internal-link suggestions with auto-detected anchors.
2. Settings screen — API key, model selection, threshold slider, post-type filter.
3. Bulk re-index progress UI.

== Changelog ==

= 1.3.2 =
* Change: Default indexed post types are now **posts AND pages** (`['post','page']`). Page-based, brochure, and Elementor sites keep their linkable content in pages; the old `post`-only default did nothing for them out of the box. Existing sites with a saved post-type selection are unaffected.

= 1.3.1 =
* Fix: Hosted-AI installs (Premium with no site-level OpenAI key) could not index — `is_configured()` only checked for a site API key and bailed before the `cil_provider` filter (which supplies the hosted provider) was considered. Indexing + suggestions now work when the embedding provider is injected via the filter.

= 1.3.0 =
* Add: Elementor support — pages built with Elementor (content stored in `_elementor_data`, not post_content) are now indexed for suggestions AND scanned for internal links. Previously every Elementor page looked orphaned and was invisible to the suggestion engine. Text + internal-link anchors are parsed from the saved element tree (headings, text-editor rich text, buttons), no rendering required.

= 1.2.0 =
* Add: Insights / ROI dashboard at Tools → AI Linker → Insights — links inserted, editor time saved, pages improved, OpenAI cost vs editor-time ROI multiple, 8-week activity chart, top-linked targets, most-active editors, recent activity.
* Add: Inline-wrap link insertion — the Gutenberg sidebar now finds the suggested anchor in your draft and wraps THOSE words in the link, in place. No more "Related:" paragraphs at the end of the post. Falls back gracefully if the anchor isn't in the draft.
* Add: CSV export of the full activity log from the Insights page.
* Add: `cil_settings_render_extra` action — Pro and add-ons can inject custom settings panels.
* Add: `cil_provider` filter — Pro's HostedProvider can drop in as the embedding source when a license is active.
* Add: `cil_provider_summary` filter — Pro can tell Free's Settings page that hosted AI is active.
* Add: `cil_extra_excluded_ids`, `cil_rank_results`, `cil_suggestion_row` filters for Pro to extend ranking + per-row display data.
* Fix: Default similarity threshold lowered from 0.75 → 0.55 — calibrated to OpenAI text-embedding-3-small on real WordPress content. The old default returned zero suggestions on most sites.
* Fix: AnchorExtractor trims sentences at word boundaries with no ellipsis, so anchors are always literal substrings of the source content (required by the inline-wrap path).
* Fix: Threshold slider now defensively coerces values to real numbers — eliminates blank-input + bad-API-call edge case from v1.0.
* Fix: Reports → Orphans now ships an inline "How to fix" workflow with top-3 inbound-candidate posts per orphan, computed from existing embeddings. Each candidate has a one-click "Open in editor" link with `?cil_open=1` that auto-opens the AI Linker sidebar.

= 1.1.0 =
* Add: Orphan Pages report — every published post with zero internal links pointing to it, with one-click "Edit" + manual re-scan button. Cached in a 6h transient; auto-invalidated on save_post / before_delete_post.
* Add: Ignore lists — exclude specific post IDs and/or whole category trees from suggestions via the Settings screen.
* Add: Target keyword detection from Yoast SEO, Rank Math, All in One SEO, and SEOPress. Surfaced in the REST `/suggestions` response as `target_keyword` + `target_keyword_source`.
* Add: Content extractor — runs `do_shortcode()` before normalization so shortcode-based page builders (Divi, WPBakery / Visual Composer, plus any custom shortcodes) get scanned. Falls back to raw content on errors or unexpectedly short output.
* Add: REST routes `GET /cil/v1/reports/orphans` and `POST /cil/v1/reports/rescan` (admin capability).
* Test: 11 new unit tests covering TargetKeywordReader and LinkGraphScanner. Total: 29 tests / 49 assertions.

= 1.0.1 =
* Fix: AnchorExtractor now batches all source-sentence embeddings into a single OpenAI call and uses the target post's already-stored vector for ranking, replacing ~100 sequential API calls with 1. Suggestions endpoint p95 drops from ~100s to <1s.
* Add: Source-sentence vectors cached in a 12-hour transient keyed by content hash; subsequent suggestion calls on the same draft are <50ms.
* Add: `ProviderInterface::embed_batch()` for batched embedding requests.

= 1.0.0 =
* Initial release.
* OpenAI embeddings provider (text-embedding-3-small / large).
* save_post → Action Scheduler indexing pipeline with content-hash skip.
* Block editor sidebar with one-click insert and acceptance logging.
* Bulk re-index with progress UI.
* Cosine ranking with configurable threshold + max-suggestions cap.
* Anchor-extraction by sentence-level cosine against target title+excerpt.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
