<?php
/**
 * Reports page — orphan posts with inline "how to fix" workflow.
 *
 * @var array $report Output of OrphanReport::generate().
 *
 * @package Champlin\InternalLinker\Admin
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$rescan_url = wp_nonce_url(
    add_query_arg(['page' => \Champlin\InternalLinker\Admin\ReportsPage::MENU_SLUG, 'rescan' => 1]),
    'cil_rescan_orphans',
    '_cilnonce'
);

$cil_version  = defined('CIL_VERSION') ? CIL_VERSION : '';
$orphan_count = (int) ($report['orphan_count'] ?? 0);
$total        = (int) ($report['total_eligible'] ?? 0);
$scanned      = (int) ($report['scanned'] ?? 0);
$ratio        = $total > 0 ? $orphan_count / $total : 0;

$severity = $orphan_count === 0
    ? 'success'
    : ($ratio < 0.25 ? 'info' : ($ratio < 0.61 ? 'warning' : 'error'));

$banner_copy = match ($severity) {
    'success' => __('Healthy link graph — every published post has at least one inbound link.', 'champlin-ai-internal-linker'),
    /* translators: %d: number of orphan posts (posts with no inbound internal links). */
    'info'    => sprintf(_n('%d post on this site has no inbound internal links.', '%d posts on this site have no inbound internal links.', $orphan_count, 'champlin-ai-internal-linker'), $orphan_count),
    /* translators: %d: percentage of the library that is orphaned. */
    'warning' => sprintf(__('Roughly %d%% of your library is orphaned. Fixing the top 10 typically lifts traffic 4–12%%.', 'champlin-ai-internal-linker'), (int) round($ratio * 100)),
    /* translators: 1: number of orphan posts, 2: total number of posts. */
    'error'   => sprintf(__('Nearly every post (%1$d of %2$d) is orphaned. Strongly consider auto-link rules — manual fixes won’t scale.', 'champlin-ai-internal-linker'), $orphan_count, $total),
};

// Count how many orphans have embeddings (can show candidates) vs need indexing first
$with_embeddings = 0;
foreach ($report['orphans'] as $o) {
    if (!empty($o['has_embedding'])) {
        $with_embeddings++;
    }
}
$missing_embeddings = $orphan_count - $with_embeddings;
?>
<div class="wrap cil-wrap">
    <div class="cil-app">

        <header class="cil-app-header">
            <div class="cil-app-title">
                <div class="cil-app-eyebrow">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 15 4-4 4 4 6-6"/></svg>
                    AI Linker · Reports
                </div>
                <h1><?php esc_html_e('Orphan Pages', 'champlin-ai-internal-linker'); ?></h1>
                <p class="cil-app-subtitle"><?php esc_html_e('Published posts with zero inbound internal links. The single biggest SEO lift on most content sites — one good internal link from a relevant post moves these out of the gutter.', 'champlin-ai-internal-linker'); ?></p>
            </div>
            <div class="cil-app-actions">
                <a href="<?php echo esc_url($rescan_url); ?>" class="cil-btn cil-btn-ghost">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.97 0 5.62 1.44 7.3 3.65"/><polyline points="21 4 21 10 15 10"/></svg>
                    <?php esc_html_e('Re-scan', 'champlin-ai-internal-linker'); ?>
                </a>
            </div>
        </header>

        <!-- Stat strip -->
        <div class="cil-grid cil-grid-3" style="margin-bottom: 1.25rem;">
            <div class="cil-stat cil-stat--accent">
                <span class="cil-stat-label"><?php esc_html_e('Orphan posts', 'champlin-ai-internal-linker'); ?></span>
                <span class="cil-stat-value tabular-nums"><?php echo esc_html((string) $orphan_count); ?></span>
                <span class="cil-stat-meta">
                    <?php
                    if ($total > 0) {
                        /* translators: 1: percentage of orphans, 2: total number of published posts. */
                        printf(esc_html__('%1$d%% of %2$d published', 'champlin-ai-internal-linker'), (int) round($ratio * 100), (int) $total);
                    } else {
                        esc_html_e('Nothing to scan yet', 'champlin-ai-internal-linker');
                    }
                    ?>
                </span>
            </div>
            <div class="cil-stat">
                <span class="cil-stat-label"><?php esc_html_e('Scanned posts', 'champlin-ai-internal-linker'); ?></span>
                <span class="cil-stat-value tabular-nums"><?php echo esc_html((string) $scanned); ?></span>
                <span class="cil-stat-meta">
                    <?php
                    /* translators: 1: UTC timestamp */
                    printf(esc_html__('Computed %s UTC', 'champlin-ai-internal-linker'), '<span class="cil-mono">' . esc_html((string) $report['computed_at']) . '</span>');
                    ?>
                </span>
            </div>
            <div class="cil-stat">
                <span class="cil-stat-label"><?php esc_html_e('Cache freshness', 'champlin-ai-internal-linker'); ?></span>
                <span class="cil-stat-value" style="font-size:1.5rem;color:#10b981;">●</span>
                <span class="cil-stat-meta"><?php esc_html_e('Live · auto-invalidates on save_post', 'champlin-ai-internal-linker'); ?></span>
            </div>
        </div>

        <!-- Severity banner -->
        <div class="cil-banner cil-banner-<?php echo esc_attr($severity); ?>" role="status">
            <?php if ($severity === 'success') : ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <?php elseif ($severity === 'warning') : ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <?php elseif ($severity === 'error') : ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <?php else : ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <?php endif; ?>
            <p>
                <span class="cil-banner-title"><?php echo esc_html($banner_copy); ?></span>
                <?php if ($severity !== 'success') : ?>
                    <?php esc_html_e('Below each orphan, click "Show 3 candidates" to see the posts most likely to want a link to it. Open one — the AI Linker sidebar will suggest this orphan as a relevant target. One click and the orphan is fixed.', 'champlin-ai-internal-linker'); ?>
                <?php else : ?>
                    <?php esc_html_e('Keep an eye on this — new posts get added to this list automatically when they’re published without inbound links.', 'champlin-ai-internal-linker'); ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- ============================================================
             "How to fix an orphan" workflow explainer (always visible)
             ============================================================ -->
        <?php if ($orphan_count > 0) : ?>
        <section class="cil-card cil-card--striped" style="margin-bottom: 1.25rem;">
            <div class="cil-card-body" style="padding-top: 1.1rem; padding-bottom: 1.1rem;">
                <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                    <div style="flex-shrink:0;width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#14b8a6,#60a5fa);color:white;display:inline-flex;align-items:center;justify-content:center;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/></svg>
                    </div>
                    <div style="flex:1;min-width:18rem;">
                        <h3 style="margin:0 0 0.3rem;font-family:'Space Grotesk',sans-serif;font-size:1rem;color:#0f172a;font-weight:600;">
                            <?php esc_html_e('How to fix an orphan in 30 seconds', 'champlin-ai-internal-linker'); ?>
                        </h3>
                        <ol style="margin:0;padding-left:0;list-style:none;display:flex;flex-wrap:wrap;gap:0.6rem;font-size:0.85rem;color:#475569;line-height:1.45;">
                            <li style="flex:1;min-width:14rem;"><strong style="color:#14b8a6;font-family:'JetBrains Mono',monospace;font-size:0.75rem;">STEP 1</strong><br><?php esc_html_e('Expand a row below to see the 3 best candidates.', 'champlin-ai-internal-linker'); ?></li>
                            <li style="flex:1;min-width:14rem;"><strong style="color:#14b8a6;font-family:'JetBrains Mono',monospace;font-size:0.75rem;">STEP 2</strong><br><?php esc_html_e('Click "Open in editor" on the candidate you like.', 'champlin-ai-internal-linker'); ?></li>
                            <li style="flex:1;min-width:14rem;"><strong style="color:#14b8a6;font-family:'JetBrains Mono',monospace;font-size:0.75rem;">STEP 3</strong><br><?php esc_html_e('Inside the editor, open the AI Linker sidebar. The orphan should appear as a top suggestion — click "Insert link". Done.', 'champlin-ai-internal-linker'); ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($missing_embeddings > 0) : ?>
            <div class="cil-banner cil-banner-info" role="status" style="margin-top: 0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <p>
                    <span class="cil-banner-title">
                        <?php
                        printf(
                            /* translators: 1: number of orphan posts, 2: empty string or "s" for plural suffix. */
                            esc_html__('%1$d orphan%2$s waiting for indexing', 'champlin-ai-internal-linker'),
                            (int) $missing_embeddings,
                            esc_html($missing_embeddings === 1 ? '' : 's')
                        );
                        ?>
                    </span>
                    <?php
                    printf(
                        /* translators: 1: opening <a> tag linking to the re-index page, 2: closing </a> tag. */
                        esc_html__('Some orphans don’t have an embedding yet, so candidate links can’t be computed. Run a %1$sre-index%2$s and they’ll show full workflow next scan.', 'champlin-ai-internal-linker'),
                        '<a href="' . esc_url(admin_url('admin.php?page=champlin-internal-linker-indexer')) . '">', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- url is escaped above, tag is constant
                        '</a>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constant closing tag
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- ============================================================
             Orphan table
             ============================================================ -->
        <section class="cil-card">
            <header class="cil-card-header">
                <div>
                    <h2><?php esc_html_e('Orphaned posts', 'champlin-ai-internal-linker'); ?></h2>
                    <p class="cil-help">
                        <?php
                        if ($orphan_count > 0) {
                            printf(
                                /* translators: %d: number of orphan posts found in the current scan. */
                                esc_html__('%d orphan posts found · sorted by last modified, newest first. Click any row to see candidate fixes.', 'champlin-ai-internal-linker'),
                                (int) $orphan_count
                            );
                        } else {
                            esc_html_e('No orphans found in this scan — every published post has at least one inbound internal link.', 'champlin-ai-internal-linker');
                        }
                        ?>
                    </p>
                </div>
                <?php if ($orphan_count > 0) : ?>
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <input
                            type="search"
                            id="cil-orphan-filter"
                            class="cil-input"
                            placeholder="<?php esc_attr_e('Filter by title or type…', 'champlin-ai-internal-linker'); ?>"
                            style="max-width:18rem;font-size:0.85rem;"
                            oninput="(function(q){const rows=document.querySelectorAll('.cil-orphan-row');let v=0;rows.forEach(r=>{const m=q===''||r.dataset.search.includes(q);r.style.display=m?'':'none';if(m)v++;});const e=document.getElementById('cil-orphan-empty');if(e)e.style.display=v===0?'':'none';})(this.value.toLowerCase())"
                        />
                    </div>
                <?php endif; ?>
            </header>

            <?php if ($orphan_count === 0) : ?>
                <div class="cil-empty">
                    <div class="cil-empty-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <h3><?php esc_html_e('Zero orphans — beautifully linked', 'champlin-ai-internal-linker'); ?></h3>
                    <p><?php esc_html_e('Every published post on this site has at least one internal link pointing to it. Search engines can crawl every corner of your content.', 'champlin-ai-internal-linker'); ?></p>
                </div>
            <?php else : ?>
                <div class="cil-card-body--flush">
                    <ul class="cil-orphan-list">
                        <?php foreach ($report['orphans'] as $orphan) :
                            $search_str = strtolower($orphan['title'] . ' ' . $orphan['post_type']);
                        ?>
                            <li class="cil-orphan-row" data-search="<?php echo esc_attr($search_str); ?>">
                                <details>
                                    <summary>
                                        <div class="cil-orphan-summary-main">
                                            <div class="cil-orphan-title">
                                                <span class="cil-orphan-chevron" aria-hidden="true">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                                </span>
                                                <strong><?php echo esc_html($orphan['title']); ?></strong>
                                            </div>
                                            <div class="cil-orphan-meta">
                                                <span class="cil-table-pt-chip"><?php echo esc_html($orphan['post_type']); ?></span>
                                                <span class="cil-mono">#<?php echo esc_html((string) $orphan['post_id']); ?></span>
                                                <span class="cil-orphan-modified"><?php echo esc_html(mysql2date('Y-m-d H:i', $orphan['modified'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="cil-orphan-summary-actions">
                                            <?php if (!empty($orphan['candidates'])) : ?>
                                                <span class="cil-pill cil-pill-success" style="font-weight:500;">
                                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                    <?php
                                                    printf(
                                                        esc_html(
                                                            /* translators: %d: number of candidate posts that could link to this orphan. */
                                                            _n('%d candidate', '%d candidates', count($orphan['candidates']), 'champlin-ai-internal-linker')
                                                        ),
                                                        (int) count($orphan['candidates'])
                                                    );
                                                    ?>
                                                </span>
                                            <?php elseif (empty($orphan['has_embedding'])) : ?>
                                                <span class="cil-pill cil-pill-warning"><?php esc_html_e('Needs index', 'champlin-ai-internal-linker'); ?></span>
                                            <?php else : ?>
                                                <span class="cil-pill cil-pill-idle"><?php esc_html_e('No match', 'champlin-ai-internal-linker'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </summary>

                                    <div class="cil-orphan-detail">
                                        <?php if (!empty($orphan['candidates'])) : ?>
                                            <div class="cil-orphan-howto">
                                                <strong><?php esc_html_e('How to fix:', 'champlin-ai-internal-linker'); ?></strong>
                                                <?php esc_html_e('Open any of these candidates in the editor. The AI Linker sidebar will then suggest a link to this orphan — one click and it’s no longer orphaned.', 'champlin-ai-internal-linker'); ?>
                                            </div>
                                            <ul class="cil-candidate-list">
                                                <?php foreach ($orphan['candidates'] as $cand) :
                                                    $pct = (int) round($cand['similarity'] * 100);
                                                ?>
                                                    <li class="cil-candidate">
                                                        <div class="cil-candidate-main">
                                                            <a href="<?php echo esc_url(add_query_arg('cil_open', '1', $cand['edit_url'])); ?>" class="cil-candidate-title" target="_blank" rel="noopener">
                                                                <?php echo esc_html($cand['title']); ?>
                                                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                                            </a>
                                                            <div class="cil-candidate-meta">
                                                                <span class="cil-mono">#<?php echo esc_html((string) $cand['post_id']); ?></span>
                                                                ·
                                                                <span class="cil-candidate-score" title="<?php esc_attr_e('Cosine similarity to this orphan', 'champlin-ai-internal-linker'); ?>">
                                                                    <?php echo esc_html((string) $pct); ?>% match
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <a href="<?php echo esc_url(add_query_arg('cil_open', '1', $cand['edit_url'])); ?>" class="cil-btn cil-btn-primary cil-btn-sm" target="_blank" rel="noopener">
                                                            <?php esc_html_e('Open in editor', 'champlin-ai-internal-linker'); ?>
                                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php elseif (empty($orphan['has_embedding'])) : ?>
                                            <div class="cil-orphan-howto cil-orphan-howto--warn">
                                                <strong><?php esc_html_e('Index this post first', 'champlin-ai-internal-linker'); ?></strong>
                                                <?php
                                                printf(
                                                    /* translators: 1: opening <a> tag linking to the re-index page, 2: closing </a> tag. */
                                                    esc_html__('No embedding exists yet for this post — open it and save, or run a %1$sre-index%2$s to surface candidate fixes.', 'champlin-ai-internal-linker'),
                                                    '<a href="' . esc_url(admin_url('admin.php?page=champlin-internal-linker-indexer')) . '">', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- url is escaped above, tag is constant
                                                    '</a>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constant closing tag
                                                );
                                                ?>
                                            </div>
                                        <?php else : ?>
                                            <div class="cil-orphan-howto">
                                                <strong><?php esc_html_e('No strong matches.', 'champlin-ai-internal-linker'); ?></strong>
                                                <?php esc_html_e('Nothing on the site clears the 0.55 similarity bar for an inbound suggestion. This post may be a standalone topic — consider linking to it manually from a hub/category page, or lower the suggestion threshold in Settings.', 'champlin-ai-internal-linker'); ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="cil-orphan-detail-footer">
                                            <a href="<?php echo esc_url(get_edit_post_link($orphan['post_id'])); ?>" class="cil-btn cil-btn-ghost cil-btn-sm" target="_blank" rel="noopener">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                                <?php esc_html_e('Edit the orphan itself', 'champlin-ai-internal-linker'); ?>
                                            </a>
                                            <a href="<?php echo esc_url($orphan['permalink']); ?>" class="cil-btn cil-btn-ghost cil-btn-sm" target="_blank" rel="noopener">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                <?php esc_html_e('View live', 'champlin-ai-internal-linker'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </details>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div id="cil-orphan-empty" class="cil-empty" style="display:none;">
                        <p><?php esc_html_e('No orphans match your filter.', 'champlin-ai-internal-linker'); ?></p>
                    </div>
                </div>
                <div class="cil-card-footer">
                    <div class="cil-savebar-status">
                        <?php
                        printf(
                            /* translators: %d: number of orphan posts shown in the table. */
                            esc_html__('Showing %1$d orphans · cache TTL 6h · auto-invalidates on post save', 'champlin-ai-internal-linker'),
                            (int) $orphan_count
                        );
                        ?>
                    </div>
                    <a href="<?php echo esc_url($rescan_url); ?>" class="cil-btn cil-btn-ghost cil-btn-sm">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.97 0 5.62 1.44 7.3 3.65"/><polyline points="21 4 21 10 15 10"/></svg>
                        <?php esc_html_e('Re-scan now', 'champlin-ai-internal-linker'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($orphan_count > 0) : ?>
            <!-- Premium auto-fix CTA -->
            <section class="cil-banner cil-banner-premium cil-banner--spaced" role="region" aria-label="<?php esc_attr_e('Premium feature', 'champlin-ai-internal-linker'); ?>">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.39 7.36H22l-6.19 4.5L18.2 21 12 16.5 5.8 21l2.39-7.14L2 9.36h7.61z"/></svg>
                <p>
                    <span class="cil-banner-title"><?php esc_html_e('Free finds the fix. Premium inserts it.', 'champlin-ai-internal-linker'); ?></span>
                    <?php esc_html_e('Premium runs the suggestion engine across every orphan and queues each link insertion behind a single Approve-All button. No clicking through 96 posts one at a time. $39/yr · launching v1.2.', 'champlin-ai-internal-linker'); ?>
                </p>
                <div class="cil-banner-actions">
                    <a href="https://kevinchamplin.com/plugins/champlin-ai-internal-linker#tiers" target="_blank" rel="noopener" class="cil-btn cil-btn-primary cil-btn-sm">
                        <?php esc_html_e('Notify me', 'champlin-ai-internal-linker'); ?>
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                </div>
            </section>
        <?php endif; ?>

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
