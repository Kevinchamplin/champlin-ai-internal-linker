<?php
/**
 * Enqueue the Gutenberg sidebar bundle.
 *
 * @package Champlin\InternalLinker\Admin
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Admin;

use Champlin\InternalLinker\Embeddings\ProviderFactory;

final class EditorAssets
{
    public function enqueue(): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $asset_php = CIL_DIR . 'assets/dist/editor/index.asset.php';
        $script    = CIL_DIR . 'assets/dist/editor/index.js';

        if (!file_exists($script)) {
            return;
        }

        $asset = file_exists($asset_php)
            ? (array) require $asset_php
            : ['dependencies' => [], 'version' => CIL_VERSION];

        wp_enqueue_script(
            'cil-editor-sidebar',
            CIL_URL . 'assets/dist/editor/index.js',
            (array) ($asset['dependencies'] ?? []),
            (string) ($asset['version'] ?? CIL_VERSION),
            true
        );

        $css = CIL_DIR . 'assets/dist/editor/index.css';
        if (file_exists($css)) {
            wp_enqueue_style(
                'cil-editor-sidebar',
                CIL_URL . 'assets/dist/editor/index.css',
                ['wp-edit-blocks'],
                (string) filemtime($css)
            );
        }

        $settings = ProviderFactory::settings();
        wp_localize_script('cil-editor-sidebar', 'cilEditor', [
            'nonce'          => wp_create_nonce('wp_rest'),
            'restNamespace'  => 'cil/v1',
            'threshold'      => (float) $settings['threshold'],
            'maxSuggestions' => (int) $settings['max_suggestions'],
        ]);

        wp_set_script_translations('cil-editor-sidebar', 'champlin-ai-internal-linker');
    }
}
