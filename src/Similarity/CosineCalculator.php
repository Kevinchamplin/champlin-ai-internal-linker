<?php
/**
 * Pure-PHP cosine similarity for batch ranking.
 *
 * No vector DB; embeddings are loaded into PHP and ranked in-memory.
 * Cosine similarity assumes both vectors have the same dimensionality.
 *
 * @package Champlin\InternalLinker\Similarity
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Similarity;

final class CosineCalculator
{
    /**
     * @param float[] $a
     * @param float[] $b
     */
    public function similarity(array $a, array $b): float
    {
        $len = count($a);
        if ($len === 0 || $len !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $na  = 0.0;
        $nb  = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $av   = (float) $a[$i];
            $bv   = (float) $b[$i];
            $dot += $av * $bv;
            $na  += $av * $av;
            $nb  += $bv * $bv;
        }

        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }

    /**
     * Rank candidates against a source vector. Returns a sorted (descending)
     * list of [post_id, similarity] pairs.
     *
     * @param float[]                                              $source
     * @param iterable<array{post_id: int, vector: float[]}>       $candidates
     * @param float                                                $min_threshold Filter scores below this.
     * @param int                                                  $limit         Cap on returned items (0 = no cap).
     * @return array<int, array{post_id: int, similarity: float}>
     */
    public function rank(array $source, iterable $candidates, float $min_threshold = 0.0, int $limit = 0): array
    {
        if ($source === []) {
            return [];
        }

        $scored = [];
        foreach ($candidates as $candidate) {
            $score = $this->similarity($source, $candidate['vector']);
            if ($score < $min_threshold) {
                continue;
            }
            $scored[] = ['post_id' => (int) $candidate['post_id'], 'similarity' => $score];
        }

        usort($scored, static fn(array $x, array $y): int => $y['similarity'] <=> $x['similarity']);

        if ($limit > 0 && count($scored) > $limit) {
            $scored = array_slice($scored, 0, $limit);
        }

        return $scored;
    }

    /**
     * Mean-pool a set of vectors. Used when input content was chunked across
     * multiple embedding calls.
     *
     * @param float[][] $vectors
     * @return float[]
     */
    public function mean_pool(array $vectors): array
    {
        $count = count($vectors);
        if ($count === 0) {
            return [];
        }
        $dim = count($vectors[0]);
        if ($dim === 0) {
            return [];
        }

        $sum = array_fill(0, $dim, 0.0);
        foreach ($vectors as $vector) {
            for ($i = 0; $i < $dim; $i++) {
                $sum[$i] += (float) ($vector[$i] ?? 0.0);
            }
        }
        for ($i = 0; $i < $dim; $i++) {
            $sum[$i] /= $count;
        }

        return $sum;
    }
}
