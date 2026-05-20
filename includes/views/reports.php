<?php
/**
 * Reports page — orphan posts table.
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
?>
<div class="wrap cil-wrap">
    <h1><?php esc_html_e('AI Internal Linker — Reports', 'champlin-internal-linker'); ?></h1>

    <div class="cil-card">
        <h2><?php esc_html_e('Orphan Pages', 'champlin-internal-linker'); ?></h2>
        <p class="description">
            <?php esc_html_e('Published posts that have zero internal links pointing to them. Orphan pages get little to no organic traffic — adding even one internal link from a relevant existing post often produces a measurable ranking lift.', 'champlin-internal-linker'); ?>
        </p>

        <p>
            <?php
            printf(
                /* translators: 1: scanned post count, 2: ISO timestamp. */
                esc_html__('Scanned %1$s posts. Last computed %2$s.', 'champlin-internal-linker'),
                '<strong>' . esc_html((string) $report['scanned']) . '</strong>',
                '<code>' . esc_html((string) $report['computed_at']) . ' UTC</code>'
            );
            ?>
            &nbsp;
            <a href="<?php echo esc_url($rescan_url); ?>" class="button button-secondary">
                <?php esc_html_e('Re-scan now', 'champlin-internal-linker'); ?>
            </a>
        </p>

        <p>
            <strong>
                <?php
                printf(
                    /* translators: 1: orphan count, 2: total eligible. */
                    esc_html__('%1$s orphan posts found out of %2$s total.', 'champlin-internal-linker'),
                    esc_html((string) $report['orphan_count']),
                    esc_html((string) $report['total_eligible'])
                );
                ?>
            </strong>
        </p>

        <?php if ($report['orphans'] === []) : ?>
            <p><?php esc_html_e('No orphans 🎉 every post on this site has at least one internal link pointing to it.', 'champlin-internal-linker'); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Title', 'champlin-internal-linker'); ?></th>
                        <th style="width: 110px;"><?php esc_html_e('Post type', 'champlin-internal-linker'); ?></th>
                        <th style="width: 180px;"><?php esc_html_e('Last modified', 'champlin-internal-linker'); ?></th>
                        <th style="width: 140px;"><?php esc_html_e('Actions', 'champlin-internal-linker'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['orphans'] as $orphan) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url($orphan['permalink']); ?>" target="_blank" rel="noreferrer noopener">
                                    <?php echo esc_html($orphan['title']); ?>
                                </a>
                            </td>
                            <td><code><?php echo esc_html($orphan['post_type']); ?></code></td>
                            <td><?php echo esc_html($orphan['modified']); ?></td>
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($orphan['post_id'])); ?>" class="button button-small">
                                    <?php esc_html_e('Edit', 'champlin-internal-linker'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <p class="cil-footer-credit">
        <?php esc_html_e('Engineered by', 'champlin-internal-linker'); ?>
        <a href="https://champlinenterprises.com" target="_blank" rel="noreferrer noopener">Champlin Enterprises</a>
    </p>
</div>
