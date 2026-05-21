<?php
/**
 * Bulk indexer view.
 *
 * @var array $progress Current indexer state.
 *
 * @package Champlin\InternalLinker\Admin
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$status   = (string) ($progress['status'] ?? 'idle');
$processed = (int) ($progress['processed'] ?? 0);
$total    = (int) ($progress['total'] ?? 0);
$pct      = $total > 0 ? min(100, (int) round($processed / $total * 100)) : 0;
$cil_version = defined('CIL_VERSION') ? CIL_VERSION : '';

$status_pill = match ($status) {
    'running'  => ['class' => 'cil-pill-running', 'label' => __('Indexing…', 'champlin-ai-internal-linker')],
    'complete' => ['class' => 'cil-pill-success', 'label' => __('Up to date', 'champlin-ai-internal-linker')],
    default    => ['class' => 'cil-pill-idle',    'label' => __('Idle', 'champlin-ai-internal-linker')],
};
?>
<div class="wrap cil-wrap">
    <div class="cil-app">

        <header class="cil-app-header">
            <div class="cil-app-title">
                <div class="cil-app-eyebrow">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.97 0 5.62 1.44 7.3 3.65"/><polyline points="21 4 21 10 15 10"/></svg>
                    AI Linker · Re-index
                </div>
                <h1><?php esc_html_e('Embed your library. Once.', 'champlin-ai-internal-linker'); ?></h1>
                <p class="cil-app-subtitle"><?php esc_html_e('Generates a semantic vector for every published post so the editor can rank candidates instantly. After the first run, new and edited posts re-embed automatically on save — only changed content costs anything.', 'champlin-ai-internal-linker'); ?></p>
            </div>
            <div class="cil-app-actions">
                <span class="cil-pill <?php echo esc_attr($status_pill['class']); ?>">
                    <span class="cil-pill-dot"></span>
                    <?php echo esc_html($status_pill['label']); ?>
                </span>
            </div>
        </header>

        <!-- Stat strip -->
        <div class="cil-grid cil-grid-3" style="margin-bottom: 1.25rem;">
            <div class="cil-stat cil-stat--accent">
                <span class="cil-stat-label"><?php esc_html_e('Indexed posts', 'champlin-ai-internal-linker'); ?></span>
                <span class="cil-stat-value" id="cil-indexed-count"><?php echo esc_html((string) $processed); ?></span>
                <span class="cil-stat-meta">
                    <?php
                    if ($total > 0) {
                        /* translators: 1: percentage of eligible posts indexed, 2: total number of eligible posts. */
                        printf(esc_html__('%1$d%% of %2$d eligible', 'champlin-ai-internal-linker'), (int) $pct, (int) $total);
                    } else {
                        esc_html_e('Run an index to populate', 'champlin-ai-internal-linker');
                    }
                    ?>
                </span>
            </div>
            <div class="cil-stat">
                <span class="cil-stat-label"><?php esc_html_e('Vector storage', 'champlin-ai-internal-linker'); ?></span>
                <span class="cil-stat-value"><?php echo esc_html(number_format($processed * 6)); ?><span style="font-size:1rem;color:#94a3b8;"> KB</span></span>
                <span class="cil-stat-meta"><?php esc_html_e('~6 KB per post (float32 BLOB)', 'champlin-ai-internal-linker'); ?></span>
            </div>
            <div class="cil-stat">
                <span class="cil-stat-label"><?php esc_html_e('Estimated AI cost', 'champlin-ai-internal-linker'); ?></span>
                <span class="cil-stat-value">$<?php echo esc_html(number_format($processed * 0.00002, 4)); ?></span>
                <span class="cil-stat-meta"><?php esc_html_e('Spent so far · text-embedding-3-small', 'champlin-ai-internal-linker'); ?></span>
            </div>
        </div>

        <!-- ============================================================
             Status / Action card
             ============================================================ -->
        <?php if ($status === 'running') : ?>
            <section class="cil-card cil-card--striped" id="cil-progress-wrapper">
                <header class="cil-card-header">
                    <div>
                        <h2><?php esc_html_e('Indexing in progress', 'champlin-ai-internal-linker'); ?></h2>
                        <p class="cil-help"><?php esc_html_e('Posts are being embedded in small batches via Action Scheduler — you can leave this page and come back; it’ll keep running in the background.', 'champlin-ai-internal-linker'); ?></p>
                    </div>
                </header>
                <div class="cil-card-body">
                    <div class="cil-progress" aria-label="<?php esc_attr_e('Indexing progress', 'champlin-ai-internal-linker'); ?>" role="progressbar" aria-valuenow="<?php echo esc_attr((string) $pct); ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="cil-progress-bar" id="cil-progress" style="width: <?php echo esc_attr((string) $pct); ?>%"></div>
                    </div>
                    <p class="cil-field-hint" style="margin-top:0.85rem;">
                        <span id="cil-progress-text">
                            <?php
                            printf(
                                /* translators: 1: number of posts processed so far (wrapped in <strong>), 2: total number of eligible posts (wrapped in <strong>), 3: progress percentage. */
                                esc_html__('Re-indexing %1$s / %2$s posts (%3$s%%)…', 'champlin-ai-internal-linker'),
                                '<strong class="cil-mono">' . esc_html((string) $processed) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner value escaped, tag constant
                                '<strong class="cil-mono">' . esc_html((string) $total) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner value escaped, tag constant
                                esc_html((string) $pct)
                            );
                            ?>
                        </span>
                    </p>
                </div>
            </section>
        <?php else : ?>
            <section class="cil-card cil-card--accent" id="cil-progress-wrapper">
                <div class="cil-card-body">
                    <div style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;">
                        <div style="flex:1;min-width:18rem;">
                            <h2 style="margin:0 0 0.4rem;font-family:'Space Grotesk',sans-serif;font-size:1.15rem;color:#0f172a;">
                                <?php
                                if ($processed > 0) {
                                    esc_html_e('Re-index when your content has shifted', 'champlin-ai-internal-linker');
                                } else {
                                    esc_html_e('Run your first index — typically under 10 min', 'champlin-ai-internal-linker');
                                }
                                ?>
                            </h2>
                            <p style="margin:0;color:#475569;font-size:0.875rem;line-height:1.55;max-width:46rem;">
                                <?php
                                if ($processed > 0) {
                                    esc_html_e('Already indexed. Use this if you’ve bulk-imported, migrated, changed the embedding model, or want to rebuild from scratch.', 'champlin-ai-internal-linker');
                                } else {
                                    esc_html_e('We’ll embed every published post via Action Scheduler. Skips unchanged content on re-runs (SHA-256 hash).', 'champlin-ai-internal-linker');
                                }
                                ?>
                            </p>
                        </div>

                        <button type="button" class="cil-btn cil-btn-primary cil-btn-lg" id="cil-start-reindex">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            <?php
                            echo $processed > 0
                                ? esc_html__('Re-index everything', 'champlin-ai-internal-linker')
                                : esc_html__('Start first index', 'champlin-ai-internal-linker');
                            ?>
                        </button>
                    </div>

                    <!-- Progress bar (hidden until JS shows it on start) -->
                    <div id="cil-progress-bar" style="display:none;margin-top:1.25rem;">
                        <div class="cil-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                            <div class="cil-progress-bar cil-progress-bar--indeterminate" id="cil-progress" style="width:0%;"></div>
                        </div>
                        <p class="cil-field-hint" style="margin-top:0.85rem;">
                            <span id="cil-progress-text"><?php esc_html_e('Queueing posts…', 'champlin-ai-internal-linker'); ?></span>
                        </p>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- ============================================================
             How it works (always shown)
             ============================================================ -->
        <section class="cil-card">
            <header class="cil-card-header">
                <div>
                    <h2><?php esc_html_e('How indexing works', 'champlin-ai-internal-linker'); ?></h2>
                    <p class="cil-help"><?php esc_html_e('You only ever need to manually trigger this once. After that, everything’s automatic.', 'champlin-ai-internal-linker'); ?></p>
                </div>
            </header>
            <div class="cil-card-body">
                <ol style="counter-reset:step;list-style:none;padding:0;margin:0;display:grid;gap:0.6rem;">
                    <?php
                    $steps = [
                        ['title' => __('Normalize', 'champlin-ai-internal-linker'), 'body' => __('Strip block markup, shortcodes, HTML. Expand Divi/WPBakery via do_shortcode. Compute a SHA-256 of the result.', 'champlin-ai-internal-linker')],
                        ['title' => __('Skip if unchanged', 'champlin-ai-internal-linker'), 'body' => __('If the hash matches what we already stored, do nothing — embeddings are free on unmodified posts.', 'champlin-ai-internal-linker')],
                        ['title' => __('Embed (one batched API call)', 'champlin-ai-internal-linker'), 'body' => __('Long posts are chunked at sentence boundaries and mean-pooled into a single 1536d (or 3072d) vector.', 'champlin-ai-internal-linker')],
                        ['title' => __('Store as float32 BLOB', 'champlin-ai-internal-linker'), 'body' => __('~6 KB per post in wp_cil_embeddings. Dropped automatically on uninstall.', 'champlin-ai-internal-linker')],
                    ];
                    foreach ($steps as $i => $step) : ?>
                        <li style="display:flex;gap:0.85rem;align-items:flex-start;padding:0.7rem 0.85rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.5rem;">
                            <span style="flex-shrink:0;width:1.6rem;height:1.6rem;border-radius:50%;background:linear-gradient(135deg,#14b8a6,#60a5fa);color:white;display:inline-flex;align-items:center;justify-content:center;font-family:'Space Grotesk',sans-serif;font-weight:600;font-size:0.8rem;"><?php echo esc_html( (string) ( (int) $i + 1 ) ); ?></span>
                            <div>
                                <strong style="display:block;font-size:0.875rem;color:#0f172a;margin-bottom:0.15rem;"><?php echo esc_html($step['title']); ?></strong>
                                <span style="font-size:0.83rem;color:#64748b;line-height:1.55;"><?php echo esc_html($step['body']); ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>

                <div class="cil-tip" style="margin-top:1rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span><?php esc_html_e('On managed hosts (WP Engine, Kinsta, Pantheon, etc.), Action Scheduler runs via WP-Cron so the throughput is throttled. The first run is slow on quiet sites; high-traffic sites finish faster.', 'champlin-ai-internal-linker'); ?></span>
                </div>
            </div>
        </section>

        <!-- Premium: bulk auto-link -->
        <section class="cil-banner cil-banner-premium" role="region" aria-label="<?php esc_attr_e('Premium feature', 'champlin-ai-internal-linker'); ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.39 7.36H22l-6.19 4.5L18.2 21 12 16.5 5.8 21l2.39-7.14L2 9.36h7.61z"/></svg>
            <p>
                <span class="cil-banner-title"><?php esc_html_e('Premium: bulk auto-link rules', 'champlin-ai-internal-linker'); ?></span>
                <?php esc_html_e('Define keyword → URL rules and Premium will insert links across every matching post — no manual sidebar clicks. Plus Money Pages prioritization, broken-link checker, and hosted AI. $39/yr · coming v1.2.', 'champlin-ai-internal-linker'); ?>
            </p>
            <div class="cil-banner-actions">
                <a href="https://kevinchamplin.com/plugins/champlin-ai-internal-linker#tiers" target="_blank" rel="noopener" class="cil-btn cil-btn-ghost cil-btn-sm">
                    <?php esc_html_e('Notify me', 'champlin-ai-internal-linker'); ?>
                </a>
            </div>
        </section>

        <footer class="cil-app-footer">
            <span>
                <?php esc_html_e('Engineered by', 'champlin-ai-internal-linker'); ?>
                <a href="https://champlinenterprises.com" target="_blank" rel="noopener">Champlin Enterprises</a>
            </span>
            <?php if ($cil_version) : ?>
                <span class="cil-version-chip">v<?php echo esc_html($cil_version); ?></span>
            <?php endif; ?>
        </footer>
    </div>
</div>
