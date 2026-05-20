=== Champlin AI Internal Linker ===
Contributors: champlinenterprises
Tags: internal links, seo, embeddings, ai, suggestions
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Semantic internal-link suggestions powered by embeddings. One-click insert with auto-detected anchor text inside the block editor.

== Description ==

Champlin AI Internal Linker uses OpenAI embeddings to suggest semantically relevant internal links while you write — directly inside the block editor sidebar. One click inserts the link with an auto-detected anchor phrase pulled from your actual draft, not a generic heading.

Built for editorial teams who want better internal linking without the per-seat tax of legacy tools like Link Whisper.

= Key features =

* Semantic similarity (cosine on OpenAI embeddings) — not keyword matching
* Auto-detected anchor phrase from the sentence in your draft closest to the target post
* Gutenberg sidebar with live filtering by similarity threshold
* Background indexing via Action Scheduler — no blocking on save
* Bulk re-index with progress UI for one-time backfills
* SHA-256 content hashing skips re-embedding unchanged posts
* Acceptance logging so accepted targets stop reappearing
* Free, MIT, no per-seat fees, no telemetry

= What it does NOT do =

* Doesn't modify your posts without explicit one-click approval
* Doesn't phone home or transmit any data outside the OpenAI request
* Doesn't require a license key, account, or signup with Champlin Enterprises

= External services =

This plugin connects to OpenAI's Embeddings API at https://api.openai.com/v1/embeddings to compute vector representations of your post content. You provide your own OpenAI API key in the Settings screen — no traffic is routed through Champlin Enterprises servers.

* OpenAI Terms of Service: https://openai.com/policies/terms-of-use
* OpenAI Privacy Policy: https://openai.com/policies/privacy-policy

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

Yes. MIT-licensed, no per-seat tax, no premium upsell.

= Where can I report bugs or contribute? =

GitHub: https://github.com/Kevinchamplin/champlin-ai-internal-linker

== Screenshots ==

1. Block editor sidebar showing ranked internal-link suggestions with auto-detected anchors.
2. Settings screen — API key, model selection, threshold slider, post-type filter.
3. Bulk re-index progress UI.

== Changelog ==

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
