<?php
/**
 * @package Champlin\InternalLinker\Tests\Unit
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Champlin\InternalLinker\Indexing\ElementorExtractor;
use PHPUnit\Framework\TestCase;

final class ElementorExtractorTest extends TestCase
{
    /** A realistic Elementor element tree (heading + text-editor + button + video). */
    private const TREE = [
        [
            'elements' => [
                ['widgetType' => 'heading', 'settings' => ['title' => 'Trail Rides at Cedar Creek']],
                ['widgetType' => 'text-editor', 'settings' => ['editor' => '<p>Book a <a href="https://ccrstables.com/trail-rides">trail ride</a> today.</p>']],
                ['widgetType' => 'button', 'settings' => ['text' => 'Contact', 'link' => ['url' => 'https://ccrstables.com/contact', 'is_external' => '']]],
                ['widgetType' => 'video', 'settings' => ['youtube_url' => 'https://youtube.com/watch?v=abc']],
            ],
        ],
    ];

    protected function setUp(): void
    {
        Monkey\setUp();
        Functions\when('esc_url')->returnArg();
        Functions\when('wp_strip_all_tags')->alias(static fn ($s): string => trim(strip_tags((string) $s)));
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
    }

    /** @param array<string,mixed> $meta */
    private function withMeta(array $meta): void
    {
        Functions\when('get_post_meta')->alias(static fn (int $id, string $key, bool $single = false) => $meta[$key] ?? '');
    }

    public function test_is_elementor_requires_builder_mode_and_data(): void
    {
        $this->withMeta(['_elementor_data' => json_encode(self::TREE)]); // no edit_mode
        self::assertFalse((new ElementorExtractor())->is_elementor(1));

        $this->withMeta(['_elementor_edit_mode' => 'builder', '_elementor_data' => json_encode(self::TREE)]);
        self::assertTrue((new ElementorExtractor())->is_elementor(1));
    }

    public function test_extracts_heading_and_button_text(): void
    {
        $this->withMeta(['_elementor_data' => json_encode(self::TREE)]);
        $out = (new ElementorExtractor())->extract(1);
        self::assertStringContainsString('Trail Rides at Cedar Creek', $out);
        self::assertStringContainsString('Contact', $out);
    }

    public function test_keeps_inline_editor_link_for_the_scanner(): void
    {
        $this->withMeta(['_elementor_data' => json_encode(self::TREE)]);
        $out = (new ElementorExtractor())->extract(1);
        self::assertStringContainsString('<a ', $out);
        self::assertStringContainsString('https://ccrstables.com/trail-rides', $out);
    }

    public function test_emits_button_link_as_anchor(): void
    {
        $this->withMeta(['_elementor_data' => json_encode(self::TREE)]);
        self::assertStringContainsString('<a href="https://ccrstables.com/contact">', (new ElementorExtractor())->extract(1));
    }

    public function test_ignores_video_embed_urls(): void
    {
        // youtube_url is neither a text key nor a link control → not surfaced
        // (and would be filtered as external anyway).
        $this->withMeta(['_elementor_data' => json_encode(self::TREE)]);
        self::assertStringNotContainsString('youtube.com', (new ElementorExtractor())->extract(1));
    }

    public function test_blank_when_no_data_or_unparseable(): void
    {
        $this->withMeta([]);
        self::assertSame('', (new ElementorExtractor())->extract(1));

        $this->withMeta(['_elementor_data' => 'not-json{']);
        self::assertSame('', (new ElementorExtractor())->extract(1));
    }
}
