<?php
/**
 * @package Champlin\InternalLinker\Tests\Unit
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Champlin\InternalLinker\Integrations\TargetKeywordReader;
use PHPUnit\Framework\TestCase;

final class TargetKeywordReaderTest extends TestCase
{
    protected function setUp(): void
    {
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
    }

    public function test_reads_yoast_focus_keyword(): void
    {
        Functions\when('get_post_meta')->alias(function (int $id, string $key, bool $single) {
            return $key === '_yoast_wpseo_focuskw' ? 'headless wordpress' : '';
        });

        $reader = new TargetKeywordReader();
        self::assertSame('headless wordpress', $reader->keyword_for(1));
        self::assertSame('Yoast SEO', $reader->source_for(1));
    }

    public function test_falls_through_to_rank_math_when_yoast_empty(): void
    {
        Functions\when('get_post_meta')->alias(function (int $id, string $key, bool $single) {
            return match ($key) {
                '_yoast_wpseo_focuskw'    => '',
                'rank_math_focus_keyword' => 'AI internal linking, semantic search',
                default                   => '',
            };
        });

        $reader = new TargetKeywordReader();
        // Comma-separated: first wins, trimmed.
        self::assertSame('AI internal linking', $reader->keyword_for(2));
        self::assertSame('Rank Math', $reader->source_for(2));
    }

    public function test_parses_aioseo_json_keyphrase(): void
    {
        $payload = wp_json_encode_helper([
            'focus' => ['keyphrase' => 'orphan page report', 'score' => 80],
        ]);

        Functions\when('get_post_meta')->alias(function (int $id, string $key, bool $single) use ($payload) {
            return match ($key) {
                '_aioseo_keyphrases' => $payload,
                default              => '',
            };
        });

        $reader = new TargetKeywordReader();
        self::assertSame('orphan page report', $reader->keyword_for(3));
        self::assertSame('All in One SEO', $reader->source_for(3));
    }

    public function test_returns_empty_when_no_seo_plugin_data(): void
    {
        Functions\when('get_post_meta')->justReturn('');
        $reader = new TargetKeywordReader();
        self::assertSame('', $reader->keyword_for(4));
        self::assertSame('', $reader->source_for(4));
    }

    public function test_parse_csv_first_handles_empty(): void
    {
        self::assertSame('', TargetKeywordReader::parse_csv_first(''));
        self::assertSame('one', TargetKeywordReader::parse_csv_first('one'));
        self::assertSame('one', TargetKeywordReader::parse_csv_first('  one  ,  two  '));
    }

    public function test_parse_aioseo_json_handles_malformed(): void
    {
        self::assertSame('', TargetKeywordReader::parse_aioseo_json(''));
        self::assertSame('', TargetKeywordReader::parse_aioseo_json('{invalid json'));
        self::assertSame('', TargetKeywordReader::parse_aioseo_json('{"focus":{}}'));
    }
}

/**
 * Tiny helper because tests run outside WordPress; wp_json_encode isn't stubbed
 * by Brain Monkey by default.
 */
function wp_json_encode_helper(array $data): string
{
    return json_encode($data, JSON_THROW_ON_ERROR);
}
