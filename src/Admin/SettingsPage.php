<?php
/**
 * Settings screen (Tools → AI Internal Linker → Settings).
 *
 * Uses the WordPress Settings API with a custom-rendered Tailwind UI.
 *
 * @package Champlin\InternalLinker\Admin
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Admin;

use Champlin\InternalLinker\Embeddings\ProviderFactory;

final class SettingsPage
{
    public const MENU_SLUG  = 'champlin-ai-internal-linker';
    public const NONCE_NAME = 'cil_settings_nonce';

    public function register(): void
    {
        add_menu_page(
            __('AI Internal Linker', 'champlin-ai-internal-linker'),
            __('AI Linker', 'champlin-ai-internal-linker'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render'],
            'dashicons-admin-links',
            81
        );
        add_submenu_page(
            self::MENU_SLUG,
            __('Settings', 'champlin-ai-internal-linker'),
            __('Settings', 'champlin-ai-internal-linker'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render']
        );
    }

    public function register_settings(): void
    {
        register_setting(
            'cil_settings_group',
            ProviderFactory::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
                'default'           => ProviderFactory::defaults(),
                'show_in_rest'      => false,
            ]
        );
    }

    /**
     * @param mixed $input
     * @return array{provider: string, model: string, api_key: string, threshold: float, post_types: string[], max_suggestions: int}
     */
    public function sanitize($input): array
    {
        $defaults = ProviderFactory::defaults();
        $input    = is_array($input) ? $input : [];

        $provider = sanitize_key((string) ($input['provider'] ?? $defaults['provider']));
        $model    = sanitize_text_field((string) ($input['model'] ?? $defaults['model']));
        $api_key  = trim((string) ($input['api_key'] ?? ''));

        $threshold = isset($input['threshold']) ? (float) $input['threshold'] : $defaults['threshold'];
        $threshold = max(0.0, min(1.0, $threshold));

        $post_types = $input['post_types'] ?? $defaults['post_types'];
        if (!is_array($post_types)) {
            $post_types = [];
        }
        $post_types = array_values(array_filter(array_map('sanitize_key', $post_types)));
        if ($post_types === []) {
            $post_types = ['post'];
        }

        $max = isset($input['max_suggestions']) ? (int) $input['max_suggestions'] : $defaults['max_suggestions'];
        $max = max(1, min(50, $max));

        $ignored_post_ids = $input['ignored_post_ids'] ?? [];
        if (is_string($ignored_post_ids)) {
            $ignored_post_ids = preg_split('/[\s,]+/', $ignored_post_ids) ?: [];
        }
        $ignored_post_ids = is_array($ignored_post_ids) ? $ignored_post_ids : [];
        $ignored_post_ids = array_values(array_unique(array_filter(
            array_map('intval', $ignored_post_ids),
            static fn(int $id): bool => $id > 0
        )));

        $ignored_term_ids = $input['ignored_term_ids'] ?? [];
        if (is_string($ignored_term_ids)) {
            $ignored_term_ids = preg_split('/[\s,]+/', $ignored_term_ids) ?: [];
        }
        $ignored_term_ids = is_array($ignored_term_ids) ? $ignored_term_ids : [];
        $ignored_term_ids = array_values(array_unique(array_filter(
            array_map('intval', $ignored_term_ids),
            static fn(int $id): bool => $id > 0
        )));

        $sanitized = [
            'provider'         => $provider !== '' ? $provider : 'openai',
            'model'            => $model !== '' ? $model : 'text-embedding-3-small',
            'api_key'          => $api_key,
            'threshold'        => $threshold,
            'post_types'       => $post_types,
            'max_suggestions'  => $max,
            'ignored_post_ids' => $ignored_post_ids,
            'ignored_term_ids' => $ignored_term_ids,
        ];

        /**
         * Filter the sanitized settings array before save.
         *
         * Pro add-ons hook here to sanitize their own added fields (license
         * key, Money Page IDs, auto-link rules, etc.) without having to
         * intercept the WP option_update_* hooks.
         *
         * @param array $sanitized Sanitized settings ready to persist.
         * @param array $input     Raw (un-sanitized) input from the settings form.
         */
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public extension hook; "cil_" is this plugin's established public-API prefix (LinkWeaver Pro depends on it).
        return (array) apply_filters('cil_settings_sanitized', $sanitized, is_array($input) ? $input : []);
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Administrator capability required.', 'champlin-ai-internal-linker'));
        }

        $settings   = ProviderFactory::settings();
        $post_types = get_post_types(['public' => true], 'objects');

        $this->enqueue_admin_assets();

        require CIL_DIR . 'includes/views/settings.php';
    }

    private function enqueue_admin_assets(): void
    {
        $css = CIL_DIR . 'assets/dist/admin/admin.css';
        if (file_exists($css)) {
            wp_enqueue_style(
                'cil-admin',
                CIL_URL . 'assets/dist/admin/admin.css',
                [],
                (string) filemtime($css)
            );
        }
    }
}
