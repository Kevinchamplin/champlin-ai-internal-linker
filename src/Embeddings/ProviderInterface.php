<?php
/**
 * Embedding provider contract.
 *
 * Providers must be stateless and reusable. They receive credentials via the
 * constructor (injected from saved settings) and expose a single embed()
 * method for one-shot calls. Batching, retry, and chunking live in the
 * indexing pipeline, not here.
 *
 * @package Champlin\InternalLinker\Embeddings
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Embeddings;

interface ProviderInterface
{
    /**
     * Compute an embedding for a single text input.
     *
     * @return float[] Vector. Implementations must throw on failure rather than return [].
     *
     * @throws \RuntimeException When the upstream API returns a non-2xx response or malformed payload.
     */
    public function embed(string $text): array;

    /**
     * Compute embeddings for a batch of inputs in a single API call.
     *
     * The returned array is index-aligned with the input array. Implementations
     * that don't support native batching may fall back to sequential `embed()`
     * calls, but the contract is one logical operation per call.
     *
     * @param string[] $texts
     * @return float[][]
     *
     * @throws \RuntimeException When any input fails to embed.
     */
    public function embed_batch(array $texts): array;

    public function model(): string;

    public function dimensions(): int;
}
