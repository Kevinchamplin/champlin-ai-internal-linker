<?php
/**
 * Bulk indexer view.
 *
 * @var array $progress  Current indexer state.
 *
 * @package Champlin\InternalLinker\Admin
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap cil-wrap">
    <h1><?php esc_html_e('AI Internal Linker — Re-index', 'champlin-internal-linker'); ?></h1>

    <div class="cil-card">
        <p>
            <?php
            printf(
                /* translators: 1: number of indexed posts. */
                esc_html__('Currently indexed: %1$s posts.', 'champlin-internal-linker'),
                '<strong id="cil-indexed-count">' . esc_html((string) $progress['processed']) . '</strong>'
            );
            ?>
        </p>

        <button type="button" class="button button-primary" id="cil-start-reindex">
            <?php esc_html_e('Start re-index', 'champlin-internal-linker'); ?>
        </button>

        <div id="cil-progress-bar" style="margin-top:1em;display:<?php echo $progress['status'] === 'running' ? 'block' : 'none'; ?>;">
            <progress
                id="cil-progress"
                value="<?php echo esc_attr((string) $progress['processed']); ?>"
                max="<?php echo esc_attr((string) max(1, $progress['total'])); ?>"
                style="width:100%;height:1.5em"
            ></progress>
            <p>
                <span id="cil-progress-text">
                    <?php
                    printf(
                        /* translators: 1: processed count, 2: total count. */
                        esc_html__('Re-indexing %1$s / %2$s posts…', 'champlin-internal-linker'),
                        esc_html((string) $progress['processed']),
                        esc_html((string) $progress['total'])
                    );
                    ?>
                </span>
            </p>
        </div>
    </div>

    <p class="cil-footer-credit">
        <?php esc_html_e('Engineered by', 'champlin-internal-linker'); ?>
        <a href="https://champlinenterprises.com" target="_blank" rel="noreferrer noopener">Champlin Enterprises</a>
    </p>
</div>
