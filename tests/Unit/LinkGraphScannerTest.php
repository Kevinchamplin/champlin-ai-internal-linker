<?php
/**
 * @package Champlin\InternalLinker\Tests\Unit
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Tests\Unit;

use Champlin\InternalLinker\Reports\LinkGraphScanner;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class LinkGraphScannerTest extends TestCase
{
    private LinkGraphScanner $scanner;
    private \ReflectionMethod $extract_internal_targets;

    protected function setUp(): void
    {
        $this->scanner = new LinkGraphScanner();
        $rc = new ReflectionClass(LinkGraphScanner::class);
        $this->extract_internal_targets = $rc->getMethod('extract_internal_targets');
        $this->extract_internal_targets->setAccessible(true);
        // wp_parse_url + url_to_postid stubs live in tests/bootstrap.php.
    }

    public function test_extracts_internal_links_with_post_resolution(): void
    {
        $html = <<<'HTML'
        <p>Some text with <a href="https://example.test/post-42">an internal link</a>
        and <a href="https://example.test/post-99/">another</a>.</p>
        HTML;

        $targets = $this->extract_internal_targets->invoke($this->scanner, $html, 'example.test');
        sort($targets);
        self::assertSame([42, 99], $targets);
    }

    public function test_skips_external_links(): void
    {
        $html = '<a href="https://other-site.test/post-42">external</a>';
        $targets = $this->extract_internal_targets->invoke($this->scanner, $html, 'example.test');
        self::assertSame([], $targets);
    }

    public function test_skips_mailto_tel_and_anchors(): void
    {
        $html = '<a href="mailto:x@y.com">a</a> <a href="tel:+1234">b</a> <a href="#section">c</a>';
        $targets = $this->extract_internal_targets->invoke($this->scanner, $html, 'example.test');
        self::assertSame([], $targets);
    }

    public function test_resolves_relative_internal_links(): void
    {
        // Relative URL — host is empty, which our scanner accepts as same-site.
        $html = '<a href="/post-7/">relative</a>';
        $targets = $this->extract_internal_targets->invoke($this->scanner, $html, 'example.test');
        self::assertSame([7], $targets);
    }

    public function test_deduplicates_repeated_links(): void
    {
        $html = '<a href="/post-3">a</a><a href="/post-3">b</a><a href="/post-3">c</a>';
        $targets = $this->extract_internal_targets->invoke($this->scanner, $html, 'example.test');
        self::assertSame([3], $targets);
    }
}
