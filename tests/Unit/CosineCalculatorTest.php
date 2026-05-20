<?php
/**
 * @package Champlin\InternalLinker\Tests\Unit
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Tests\Unit;

use Champlin\InternalLinker\Similarity\CosineCalculator;
use PHPUnit\Framework\TestCase;

final class CosineCalculatorTest extends TestCase
{
    private CosineCalculator $cosine;

    protected function setUp(): void
    {
        $this->cosine = new CosineCalculator();
    }

    public function test_identical_vectors_score_one(): void
    {
        $v = [1.0, 2.0, 3.0];
        self::assertEqualsWithDelta(1.0, $this->cosine->similarity($v, $v), 1e-6);
    }

    public function test_orthogonal_vectors_score_zero(): void
    {
        $a = [1.0, 0.0];
        $b = [0.0, 1.0];
        self::assertEqualsWithDelta(0.0, $this->cosine->similarity($a, $b), 1e-6);
    }

    public function test_opposite_vectors_score_negative_one(): void
    {
        $a = [1.0, 0.0];
        $b = [-1.0, 0.0];
        self::assertEqualsWithDelta(-1.0, $this->cosine->similarity($a, $b), 1e-6);
    }

    public function test_dimension_mismatch_returns_zero(): void
    {
        self::assertSame(0.0, $this->cosine->similarity([1.0], [1.0, 2.0]));
    }

    public function test_zero_vector_returns_zero(): void
    {
        self::assertSame(0.0, $this->cosine->similarity([0.0, 0.0], [1.0, 1.0]));
    }

    public function test_rank_orders_descending_and_applies_threshold(): void
    {
        $source     = [1.0, 0.0];
        $candidates = [
            ['post_id' => 1, 'vector' => [0.9, 0.1]],   // ~0.994
            ['post_id' => 2, 'vector' => [0.1, 0.9]],   // ~0.110
            ['post_id' => 3, 'vector' => [0.5, 0.5]],   // ~0.707
            ['post_id' => 4, 'vector' => [-1.0, 0.0]],  // -1.0
        ];

        $ranked = $this->cosine->rank($source, $candidates, 0.5);

        self::assertCount(2, $ranked);
        self::assertSame(1, $ranked[0]['post_id']);
        self::assertSame(3, $ranked[1]['post_id']);
    }

    public function test_rank_respects_limit(): void
    {
        $source     = [1.0, 0.0];
        $candidates = [
            ['post_id' => 1, 'vector' => [0.9, 0.1]],
            ['post_id' => 2, 'vector' => [0.8, 0.2]],
            ['post_id' => 3, 'vector' => [0.7, 0.3]],
        ];

        $ranked = $this->cosine->rank($source, $candidates, 0.0, 2);
        self::assertCount(2, $ranked);
    }

    public function test_mean_pool_averages_componentwise(): void
    {
        $pooled = $this->cosine->mean_pool([
            [1.0, 2.0, 3.0],
            [3.0, 4.0, 5.0],
        ]);
        self::assertSame([2.0, 3.0, 4.0], $pooled);
    }

    public function test_mean_pool_empty_returns_empty(): void
    {
        self::assertSame([], $this->cosine->mean_pool([]));
    }
}
