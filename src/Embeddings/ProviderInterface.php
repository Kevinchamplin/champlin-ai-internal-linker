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

    public function model(): string;

    public function dimensions(): int;
}
