<?php
/**
 * Construct the configured embedding provider.
 *
 * Reads settings from the cil_settings option. Today only OpenAI is wired up;
 * the interface is in place so other providers can be added without touching
 * call sites.
 *
 * @package Champlin\InternalLinker\Embeddings
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Embeddings;

use RuntimeException;

final class ProviderFactory
{
    public const OPTION_KEY = 'cil_settings';

    /**
     * @return array{provider: string, model: string, api_key: string, threshold: float, post_types: string[], max_suggestions: int, ignored_post_ids: int[], ignored_term_ids: int[]}
     */
    public static function defaults(): array
    {
        return [
            'provider'         => 'openai',
            'model'            => 'text-embedding-3-small',
            'api_key'          => '',
            // 0.55 is the empirical sweet spot for OpenAI text-embedding-3-small
            // on real WordPress content: identical→1.0, very related→0.65–0.80,
            // related→0.50–0.65, tangential→0.30–0.50, unrelated→<0.30.
            // 0.75 — our v1.0 default — was too strict; real content rarely crosses it.
            'threshold'        => 0.55,
            'post_types'       => ['post'],
            'max_suggestions'  => 5,
            'ignored_post_ids' => [],
            'ignored_term_ids' => [],
        ];
    }

    /**
     * @return array{provider: string, model: string, api_key: string, threshold: float, post_types: string[], max_suggestions: int, ignored_post_ids: int[], ignored_term_ids: int[]}
     */
    public static function settings(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        $stored = is_array($stored) ? $stored : [];
        return array_merge(self::defaults(), $stored);
    }

    public function create(): ProviderInterface
    {
        $settings = self::settings();
        $provider = (string) ($settings['provider'] ?? 'openai');

        /**
         * Filter the resolved embedding provider before instantiation.
         *
         * Pro add-ons hook here to supply a HostedProvider when a valid
         * license key is present, swapping in our hosted /api/embed proxy
         * without the user having to manage an OpenAI key.
         *
         * Return `null` to use the default Free-tier resolver below.
         *
         * @param ProviderInterface|null $override Pre-built provider, or null.
         * @param string                 $provider The configured provider slug.
         * @param array                  $settings Current cil_settings.
         */
        $override = apply_filters('cil_provider', null, $provider, $settings);
        if ($override instanceof ProviderInterface) {
            return $override;
        }

        return match ($provider) {
            'openai' => new OpenAIProvider(
                (string) $settings['api_key'],
                (string) $settings['model']
            ),
            default  => throw new RuntimeException(esc_html(sprintf('Unknown embedding provider: %s', $provider))),
        };
    }

    public function is_configured(): bool
    {
        $settings = self::settings();
        return trim((string) ($settings['api_key'] ?? '')) !== '';
    }
}
