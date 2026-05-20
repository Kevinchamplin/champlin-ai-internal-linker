<?php
/**
 * Scan every published post's content for outbound internal links, resolve
 * each to a target post ID, and produce the inverted index: target → count of
 * inbound links.
 *
 * Result is cached in a transient (6h TTL) since the work is O(N posts × M
 * anchors). For sites <2k posts, scan runs inline and finishes in seconds.
 *
 * @package Champlin\InternalLinker\Reports
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Reports;

use Champlin\InternalLinker\Embeddings\ProviderFactory;

final class LinkGraphScanner
{
    public const TRANSIENT_KEY = 'cil_link_graph_v1';
    public const TRANSIENT_TTL = HOUR_IN_SECONDS * 6;

    /**
     * @return array{computed_at: string, inbound_counts: array<int, int>, scanned: int}
     */
    public function snapshot(bool $force_rescan = false): array
    {
        if (!$force_rescan) {
            $cached = get_transient(self::TRANSIENT_KEY);
            if (is_array($cached) && isset($cached['inbound_counts'])) {
                return $cached;
            }
        }

        $snapshot = $this->compute();
        set_transient(self::TRANSIENT_KEY, $snapshot, self::TRANSIENT_TTL);
        return $snapshot;
    }

    public function invalidate(): void
    {
        delete_transient(self::TRANSIENT_KEY);
    }

    /**
     * @return array{computed_at: string, inbound_counts: array<int, int>, scanned: int}
     */
    private function compute(): array
    {
        $post_types = ProviderFactory::settings()['post_types'] ?? ['post'];
        $home       = (string) home_url('/');
        $host       = (string) wp_parse_url($home, PHP_URL_HOST);

        $inbound = [];
        $scanned = 0;
        $paged   = 1;

        do {
            $query = new \WP_Query([
                'post_type'      => $post_types,
                'post_status'    => 'publish',
                'posts_per_page' => 200,
                'paged'          => $paged,
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'no_found_rows'  => true,
                'fields'         => 'ids',
            ]);

            if ($query->posts === []) {
                break;
            }

            foreach ($query->posts as $post_id) {
                $post = get_post((int) $post_id);
                if (!$post) {
                    continue;
                }
                $content = (string) $post->post_content;
                if ($content === '') {
                    continue;
                }

                $scanned++;
                $targets = $this->extract_internal_targets($content, $host);
                foreach ($targets as $target_id) {
                    if ($target_id === (int) $post->ID) {
                        continue; // Self-links don't count.
                    }
                    $inbound[$target_id] = ($inbound[$target_id] ?? 0) + 1;
                }
            }

            $paged++;
        } while (count($query->posts) === 200);

        return [
            'computed_at'    => gmdate('Y-m-d H:i:s'),
            'inbound_counts' => $inbound,
            'scanned'        => $scanned,
        ];
    }

    /**
     * Parse `<a href="...">` from raw post HTML; return the set of post IDs
     * those links resolve to on this site.
     *
     * @return int[]
     */
    private function extract_internal_targets(string $html, string $site_host): array
    {
        if (!preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\']/i', $html, $matches)) {
            return [];
        }

        $targets = [];
        foreach ($matches[1] as $href) {
            $href = trim(html_entity_decode($href, ENT_QUOTES));
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }

            // Skip clearly external URLs.
            $host = (string) wp_parse_url($href, PHP_URL_HOST);
            if ($host !== '' && $host !== $site_host) {
                continue;
            }

            $post_id = (int) url_to_postid($href);
            if ($post_id > 0) {
                $targets[$post_id] = true;
            }
        }

        return array_keys($targets);
    }
}
