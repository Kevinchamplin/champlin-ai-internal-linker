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
            'threshold'        => 0.75,
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

        return match ($provider) {
            'openai' => new OpenAIProvider(
                (string) $settings['api_key'],
                (string) $settings['model']
            ),
            default  => throw new RuntimeException(sprintf('Unknown embedding provider: %s', $provider)),
        };
    }

    public function is_configured(): bool
    {
        $settings = self::settings();
        return trim((string) ($settings['api_key'] ?? '')) !== '';
    }
}
