<?php
/**
 * @package Champlin\InternalLinker\Tests\Unit
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Champlin\InternalLinker\Indexing\ContentNormalizer;
use PHPUnit\Framework\TestCase;

final class ContentNormalizerTest extends TestCase
{
    private ContentNormalizer $normalizer;

    protected function setUp(): void
    {
        Monkey\setUp();

        // Stub WordPress helpers used by ContentNormalizer.
        Functions\when('strip_shortcodes')->alias(static fn(string $s): string => preg_replace('/\[[^\]]+\]/', '', $s) ?? $s);
        Functions\when('wp_strip_all_tags')->alias(static fn(string $s, bool $remove_breaks = false): string =>
            trim(strip_tags($s))
        );

        $this->normalizer = new ContentNormalizer();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
    }

    public function test_strips_block_grammar_comments(): void
    {
        $input  = "<!-- wp:paragraph --><p>Hello world.</p><!-- /wp:paragraph -->";
        $output = $this->normalizer->normalize($input);
        self::assertSame('Hello world.', $output);
    }

    public function test_strips_shortcodes(): void
    {
        $output = $this->normalizer->normalize('Read [cta link="x"] this article.');
        self::assertSame('Read this article.', $output);
    }

    public function test_decodes_entities(): void
    {
        $output = $this->normalizer->normalize('5 &amp; 7 &mdash; ok.');
        self::assertSame('5 & 7 — ok.', $output);
    }

    public function test_collapses_whitespace(): void
    {
        $output = $this->normalizer->normalize("Line  one.\n\n\tLine\ttwo.");
        self::assertSame('Line one. Line two.', $output);
    }

    public function test_hash_is_stable(): void
    {
        $h1 = $this->normalizer->hash('Hello world.');
        $h2 = $this->normalizer->hash('Hello world.');
        self::assertSame($h1, $h2);
        self::assertSame(64, strlen($h1));
    }

    public function test_chunk_returns_single_chunk_for_short_input(): void
    {
        $chunks = $this->normalizer->chunk('Short content.');
        self::assertCount(1, $chunks);
        self::assertSame('Short content.', $chunks[0]);
    }

    public function test_chunk_splits_long_input_at_sentence_boundaries(): void
    {
        $sentence = 'This is sentence number ' . str_repeat('X ', 200) . '.';
        $body     = str_repeat($sentence . ' ', 4);
        $chunks   = $this->normalizer->chunk($body, 500);
        self::assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(1200, mb_strlen($chunk), 'Chunks should not be wildly over the cap.');
        }
    }

    public function test_sentences_splits_on_terminal_punctuation(): void
    {
        $sentences = $this->normalizer->sentences('First. Second! Third? Fourth.');
        self::assertSame(['First.', 'Second!', 'Third?', 'Fourth.'], $sentences);
    }

    public function test_sentences_empty(): void
    {
        self::assertSame([], $this->normalizer->sentences(''));
    }
}
