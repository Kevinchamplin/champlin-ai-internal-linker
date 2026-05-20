<?php
/**
 * Read a post's "focus keyword" from whichever SEO plugin is installed.
 *
 * Supported (in priority order):
 *   - Yoast SEO          → post_meta `_yoast_wpseo_focuskw`
 *   - Rank Math          → post_meta `rank_math_focus_keyword`  (comma-separated, first wins)
 *   - All in One SEO     → post_meta `_aioseo_keyphrases` (JSON) or `_aioseo_keywords` (legacy)
 *   - SEOPress           → post_meta `_seopress_analysis_target_kw`
 *
 * Returns the first non-empty keyword found, or '' if none.
 *
 * @package Champlin\InternalLinker\Integrations
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Integrations;

final class TargetKeywordReader
{
    /**
     * Ordered list of `[meta_key, parser]` pairs. The parser receives the raw
     * meta value and returns a trimmed string (or '').
     *
     * @var array<int, array{0: string, 1: callable(string): string}>
     */
    private array $sources;

    public function __construct()
    {
        $this->sources = [
            ['_yoast_wpseo_focuskw',          [self::class, 'parse_plain']],
            ['rank_math_focus_keyword',       [self::class, 'parse_csv_first']],
            ['_aioseo_keyphrases',            [self::class, 'parse_aioseo_json']],
            ['_aioseo_keywords',              [self::class, 'parse_plain']],
            ['_seopress_analysis_target_kw',  [self::class, 'parse_plain']],
        ];
    }

    public function keyword_for(int $post_id): string
    {
        foreach ($this->sources as [$meta_key, $parser]) {
            $raw = get_post_meta($post_id, $meta_key, true);
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            $parsed = $parser($raw);
            if ($parsed !== '') {
                return $parsed;
            }
        }
        return '';
    }

    /**
     * Which SEO plugin's keyword applied (or empty string if none). Useful for
     * surfacing in the suggestions UI ("Focus keyword from Yoast SEO").
     */
    public function source_for(int $post_id): string
    {
        foreach ($this->sources as [$meta_key, $parser]) {
            $raw = get_post_meta($post_id, $meta_key, true);
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            if ($parser($raw) !== '') {
                return match ($meta_key) {
                    '_yoast_wpseo_focuskw'         => 'Yoast SEO',
                    'rank_math_focus_keyword'      => 'Rank Math',
                    '_aioseo_keyphrases'           => 'All in One SEO',
                    '_aioseo_keywords'             => 'All in One SEO',
                    '_seopress_analysis_target_kw' => 'SEOPress',
                    default                        => '',
                };
            }
        }
        return '';
    }

    public static function parse_plain(string $raw): string
    {
        return trim($raw);
    }

    public static function parse_csv_first(string $raw): string
    {
        $parts = explode(',', $raw);
        return trim($parts[0] ?? '');
    }

    /**
     * AIOSEO stores keyphrases as JSON like:
     *   {"focus":{"keyphrase":"primary focus","score":0,"analysis":{...}},"additional":[...]}
     */
    public static function parse_aioseo_json(string $raw): string
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return '';
        }
        $kw = $decoded['focus']['keyphrase'] ?? '';
        return is_string($kw) ? trim($kw) : '';
    }
}
