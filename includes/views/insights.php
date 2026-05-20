<?php
/**
 * Insights view — ROI / activity dashboard for the AI Linker.
 *
 * @var array $insights Output of InsightsReport::generate().
 *
 * @package Champlin\InternalLinker\Admin
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$total_inserted   = (int) $insights['links_inserted_total'];
$inserted_30d     = (int) $insights['links_inserted_30d'];
$pages_improved   = (int) $insights['pages_improved'];
$minutes_saved    = (int) $insights['minutes_saved'];
$acceptance_rate  = (float) $insights['acceptance_rate'];
$delivered        = (int) $insights['suggestions_delivered'];
$indexed          = (int) $insights['indexed_posts'];
$cost             = (float) $insights['estimated_ai_cost'];
$storage_kb       = (int) $insights['storage_kb'];
$orphan_count     = (int) $insights['orphan_count'];
$orphan_ratio     = (float) $insights['orphan_ratio'];

$cil_version = defined('CIL_VERSION') ? CIL_VERSION : '';

// Format minutes-saved as "Xh Ym"
$time_saved_label = $minutes_saved >= 60
    ? sprintf('%dh %02dm', (int) floor($minutes_saved / 60), $minutes_saved % 60)
    : sprintf('%d min', $minutes_saved);

// Quick ROI: time saved (Xh × industry editor rate $45/hr) vs API cost
$editor_hourly = 45;
$time_value = round(($minutes_saved / 60) * $editor_hourly, 2);
$roi_multiple = $cost > 0 ? round($time_value / $cost, 0) : 0;
?>
<div class="wrap cil-wrap">
    <div class="cil-app">

        <header class="cil-app-header">
            <div class="cil-app-title">
                <div class="cil-app-eyebrow">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                    AI Linker · Insights
                </div>
                <h1><?php esc_html_e('What the plugin has done for you.', 'champlin-internal-linker'); ?></h1>
                <p class="cil-app-subtitle">
                    <?php esc_html_e('Editorial impact + return on the modest OpenAI bill. Updated every time you accept a suggestion.', 'champlin-internal-linker'); ?>
                </p>
            </div>
            <div class="cil-app-actions">
                <?php if ($total_inserted > 0) : ?>
                    <span class="cil-pill cil-pill-success">
                        <span class="cil-pill-dot"></span>
                        <?php
                        printf(
                            esc_html__('%d links shipped', 'champlin-internal-linker'),
                            $total_inserted
                        );
                        ?>
                    </span>
                <?php else : ?>
                    <span class="cil-pill cil-pill-idle">
                        <span class="cil-pill-dot"></span>
                        <?php esc_html_e('No accepted links yet', 'champlin-internal-linker'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </header>

        <!-- Hero ROI cards -->
        <div class="cil-grid cil-grid-3" style="margin-bottom: 1.25rem;">
            <div class="cil-stat cil-stat--accent">
                <span class="cil-stat-label"><?php esc_html_e('Links inserted', 'champlin-internal-linker'); ?></span>
                <span class="cil-stat-value tabular-nums"><?php echo esc_html((string) $total_inserted); ?></span>
                <span class="cil-stat-meta">
                    <?php
                    if ($inserted_30d > 0) {
                        printf(esc_html__('%d in the last 30 days', 'champlin-internal-linker'), $inserted_30d);
                    } else {
                        esc_html_e('Open a post and click "Insert link" in the sidebar to start', 'champlin-internal-linker');
                    }
                    ?>
                </span>
            </div>
            <div class="cil-stat cil-stat--accent">
                <span class="cil-stat-label"><?php esc_html_e('Editor time saved', 'champlin-internal-linker'); ?></span>
                <span class="cil-stat-value tabular-nums"><?php echo esc_html($time_saved_label); ?></span>
                <span class="cil-stat-meta">
                    <?php
                    /* translators: 1: minutes per link estimate */
                    printf(esc_html__('At %d min per manually-found internal link', 'champlin-internal-linker'), \Champlin\InternalLinker\Reports\InsightsReport::MINUTES_PER_LINK);
                    ?>
                </span>
            </div>
            <div class="cil-stat cil-stat--accent">
                <span class="cil-stat-label"><?php esc_html_e('Pages improved', 'champlin-internal-linker'); ?></span>
                <span class="cil-stat-value tabular-nums"><?php echo esc_html((string) $pages_improved); ?></span>
                <span class="cil-stat-meta">
                    <?php esc_html_e('Distinct source posts that received at least one inbound link', 'champlin-internal-linker'); ?>
                </span>
            </div>
        </div>

        <!-- ROI math callout -->
        <?php if ($total_inserted > 0) : ?>
        <section class="cil-card cil-card--striped" style="margin-bottom: 1.25rem;">
            <div class="cil-card-body">
                <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
                    <div style="flex:1 1 18rem;">
                        <h2 style="margin:0 0 0.45rem;font-family:'Space Grotesk',sans-serif;font-size:1.15rem;color:#0f172a;">
                            <?php esc_html_e('Your ROI math', 'champlin-internal-linker'); ?>
                        </h2>
                        <p style="margin:0;color:#475569;font-size:0.92rem;line-height:1.55;">
                            <?php
                            printf(
                                esc_html__('You\'ve spent roughly %1$s$%2$s%3$s on OpenAI embeddings and saved roughly %1$s%4$s%3$s of editorial work — about %1$s$%5$s%3$s in time value at typical editor rates. That\'s a %1$s%6$sx%3$s return on the API spend.', 'champlin-internal-linker'),
                                '<strong>',
                                esc_html(number_format($cost, 4)),
                                '</strong>',
                                esc_html($time_saved_label),
                                esc_html(number_format($time_value, 2)),
                                $cost > 0 ? esc_html(number_format($roi_multiple, 0)) : '∞'
                            );
                            ?>
                        </p>
                        <p style="margin:0.6rem 0 0;color:#94a3b8;font-size:0.78rem;line-height:1.45;">
                            <?php
                            /* translators: 1: editor hourly rate */
                            printf(
                                esc_html__('Assumes %d min/link and $%d/hr editor time. Conservative; Link Whisper\'s own marketing claims ~12 hrs/week saved.', 'champlin-internal-linker'),
                                \Champlin\InternalLinker\Reports\InsightsReport::MINUTES_PER_LINK,
                                $editor_hourly
                            );
                            ?>
                        </p>
                    </div>
                    <div style="flex-shrink:0;display:grid;grid-template-columns:repeat(3,minmax(0,auto));gap:0.85rem;font-family:'JetBrains Mono',monospace;">
                        <div style="background:rgba(94,234,212,0.08);border:1px solid rgba(94,234,212,0.3);border-radius:10px;padding:0.7rem 0.9rem;text-align:center;">
                            <div style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.08em;color:#0e7490;">OpenAI cost</div>
                            <div style="font-family:'Space Grotesk',sans-serif;font-size:1.15rem;font-weight:600;color:#0f172a;">$<?php echo esc_html(number_format($cost, 4)); ?></div>
                        </div>
                        <div style="background:rgba(94,234,212,0.08);border:1px solid rgba(94,234,212,0.3);border-radius:10px;padding:0.7rem 0.9rem;text-align:center;">
                            <div style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.08em;color:#0e7490;">Time value</div>
                            <div style="font-family:'Space Grotesk',sans-serif;font-size:1.15rem;font-weight:600;color:#0f172a;">$<?php echo esc_html(number_format($time_value, 2)); ?></div>
                        </div>
                        <div style="background:linear-gradient(135deg,rgba(94,234,212,0.18),rgba(96,165,250,0.12));border:1px solid rgba(94,234,212,0.45);border-radius:10px;padding:0.7rem 0.9rem;text-align:center;">
                            <div style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.08em;color:#0e7490;">Return</div>
                            <div style="font-family:'Space Grotesk',sans-serif;font-size:1.15rem;font-weight:600;color:#0f172a;"><?php echo $cost > 0 ? esc_html(number_format($roi_multiple, 0)) : '∞'; ?>×</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Secondary stats -->
        <div class="cil-grid cil-grid-3" style="margin-bottom: 1.25rem;">
            <div class="cil-stat">
                <span class="cil-stat-label"><?php esc_html_e('Suggestions delivered', 'champlin-internal-linker'); ?></span>
                <span class="cil-stat-value tabular-nums"><?php echo esc_html((string) $delivered); ?></span>
                <span class="cil-stat-meta">
                    <?php
                    printf(
                        esc_html__('%d%% acceptance rate', 'champlin-internal-linker'),
                        (int) round($acceptance_rate * 100)
                    );
                    ?>
                </span>
            </div>
            <div class="cil-stat">
                <span class="cil-stat-label"><?php esc_html_e('Indexed posts', 'champlin-internal-linker'); ?></span>
                <span class="cil-stat-value tabular-nums"><?php echo esc_html((string) $indexed); ?></span>
                <span class="cil-stat-meta">
                    <?php
                    printf(
                        esc_html__('%s KB embedding storage', 'champlin-internal-linker'),
                        esc_html(number_format($storage_kb))
                    );
                    ?>
                </span>
            </div>
            <div class="cil-stat">
                <span class="cil-stat-label"><?php esc_html_e('Orphan exposure', 'champlin-internal-linker'); ?></span>
                <span class="cil-stat-value tabular-nums"><?php echo esc_html((string) $orphan_count); ?></span>
                <span class="cil-stat-meta">
                    <?php
                    /* translators: 1: percentage of orphans across eligible published posts */
                    printf(esc_html__('%d%% of published posts have zero inbound links', 'champlin-internal-linker'), (int) round($orphan_ratio * 100));
                    ?>
                    &nbsp;
                    <a href="<?php echo esc_url(admin_url('admin.php?page=champlin-internal-linker-reports')); ?>" style="color: #14b8a6;"><?php esc_html_e('Fix in Reports →', 'champlin-internal-linker'); ?></a>
                </span>
            </div>
        </div>

        <!-- Top targets -->
        <section class="cil-card">
            <header class="cil-card-header">
                <div>
                    <h2><?php esc_html_e('Most-linked target posts', 'champlin-internal-linker'); ?></h2>
                    <p class="cil-help"><?php esc_html_e('Where the inbound link juice is flowing. Tells you what your topical-authority centers are becoming.', 'champlin-internal-linker'); ?></p>
                </div>
            </header>

            <?php if ($insights['top_targets'] === []) : ?>
                <div class="cil-empty">
                    <div class="cil-empty-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </div>
                    <h3><?php esc_html_e('No accepted suggestions yet', 'champlin-internal-linker'); ?></h3>
                    <p><?php esc_html_e('Open any post in the editor and accept a sidebar suggestion. This table fills in real-time as your editors approve links.', 'champlin-internal-linker'); ?></p>
                </div>
            <?php else : ?>
                <div class="cil-card-body--flush">
                    <div class="cil-table-wrap">
                        <table class="cil-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Target post', 'champlin-internal-linker'); ?></th>
                                    <th style="width: 140px;text-align:right;"><?php esc_html_e('Inbound links', 'champlin-internal-linker'); ?></th>
                                    <th style="width: 140px;text-align:right;"><?php esc_html_e('Actions', 'champlin-internal-linker'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($insights['top_targets'] as $row) : ?>
                                    <tr>
                                        <td class="cil-table-cell-link">
                                            <a href="<?php echo esc_url(get_edit_post_link($row['post_id'])); ?>"><?php echo esc_html($row['title']); ?></a>
                                            <div style="font-size:0.72rem;color:#94a3b8;margin-top:0.15rem;">
                                                <span class="cil-mono">#<?php echo esc_html((string) $row['post_id']); ?></span>
                                                ·
                                                <a href="<?php echo esc_url($row['permalink']); ?>" target="_blank" rel="noopener" style="color:#94a3b8;">
                                                    <?php echo esc_html(parse_url($row['permalink'], PHP_URL_PATH) ?: $row['permalink']); ?> ↗
                                                </a>
                                            </div>
                                        </td>
                                        <td style="text-align:right;">
                                            <span class="cil-pill cil-pill-success" style="font-weight:500;font-family:'JetBrains Mono',monospace;letter-spacing:normal;text-transform:none;">
                                                <?php echo esc_html((string) $row['inserts']); ?>
                                            </span>
                                        </td>
                                        <td class="cil-table-actions">
                                            <a href="<?php echo esc_url(get_edit_post_link($row['post_id'])); ?>" class="cil-btn cil-btn-ghost cil-btn-sm">
                                                <?php esc_html_e('Edit', 'champlin-internal-linker'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- Recent activity -->
        <?php if ($insights['recent_activity'] !== []) : ?>
        <section class="cil-card">
            <header class="cil-card-header">
                <div>
                    <h2><?php esc_html_e('Recent activity', 'champlin-internal-linker'); ?></h2>
                    <p class="cil-help"><?php esc_html_e('The last 10 accepted inline links.', 'champlin-internal-linker'); ?></p>
                </div>
            </header>
            <div class="cil-card-body--flush">
                <div class="cil-table-wrap">
                    <table class="cil-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Source post', 'champlin-internal-linker'); ?></th>
                                <th><?php esc_html_e('Linked to', 'champlin-internal-linker'); ?></th>
                                <th style="width: 110px;"><?php esc_html_e('Match', 'champlin-internal-linker'); ?></th>
                                <th style="width: 180px;"><?php esc_html_e('When', 'champlin-internal-linker'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($insights['recent_activity'] as $row) : ?>
                                <tr>
                                    <td class="cil-table-cell-link">
                                        <a href="<?php echo esc_url(get_edit_post_link($row['source_post_id'])); ?>"><?php echo esc_html($row['source_title']); ?></a>
                                    </td>
                                    <td class="cil-table-cell-link">
                                        <a href="<?php echo esc_url(get_edit_post_link($row['target_post_id'])); ?>"><?php echo esc_html($row['target_title']); ?></a>
                                    </td>
                                    <td>
                                        <span class="cil-mono" style="font-size:0.78rem;color:#0e7490;background:rgba(94,234,212,0.12);padding:0.15rem 0.5rem;border-radius:4px;">
                                            <?php echo esc_html(number_format($row['similarity'], 3)); ?>
                                        </span>
                                    </td>
                                    <td class="cil-table-modified">
                                        <?php echo esc_html(human_time_diff(strtotime($row['created_at']), time()) . ' ' . __('ago', 'champlin-internal-linker')); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <footer class="cil-app-footer">
            <span>
                <?php esc_html_e('Engineered by', 'champlin-internal-linker'); ?>
                <a href="https://champlinenterprises.com" target="_blank" rel="noopener">Champlin Enterprises</a>
            </span>
            <?php if ($cil_version) : ?>
                <span class="cil-version-chip">v<?php echo esc_html($cil_version); ?></span>
            <?php endif; ?>
        </footer>
    </div>
</div>
