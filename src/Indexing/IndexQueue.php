<?php
/**
 * save_post → embed → store pipeline.
 *
 * The save_post hook enqueues an Action Scheduler job; the job loads the post,
 * normalizes the content, hashes it (skip if unchanged), embeds, and upserts
 * the vector. Chunking is mean-pooled to stay below the embedding model's
 * token limit while keeping a single vector per post.
 *
 * @package Champlin\InternalLinker\Indexing
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Indexing;

use Champlin\InternalLinker\Embeddings\ProviderFactory;
use Champlin\InternalLinker\Similarity\CosineCalculator;
use Champlin\InternalLinker\Storage\VectorStore;
use Throwable;
use WP_Post;

final class IndexQueue
{
    public const HOOK = 'cil_index_post';

    private CosineCalculator $cosine;
    private ContentExtractor $extractor;

    public function __construct(
        private ProviderFactory $provider_factory,
        private VectorStore $vector_store,
        private ContentNormalizer $normalizer,
        ?ContentExtractor $extractor = null
    ) {
        $this->cosine    = new CosineCalculator();
        $this->extractor = $extractor ?? new ContentExtractor();
    }

    /**
     * Fired by WordPress on save_post. Enqueue an indexing job for eligible posts.
     */
    public function on_save_post(int $post_id, WP_Post $post, bool $update): void
    {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
            return;
        }
        if ($post->post_status !== 'publish') {
            return;
        }

        $allowed = ProviderFactory::settings()['post_types'] ?? ['post'];
        if (!in_array($post->post_type, $allowed, true)) {
            return;
        }

        $this->enqueue($post_id);
    }

    public function enqueue(int $post_id): void
    {
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(self::HOOK, ['post_id' => $post_id], 'champlin-ai-internal-linker');
        } else {
            // Action Scheduler not present — run inline as a fallback.
            $this->run($post_id);
        }
    }

    /**
     * Action Scheduler entry point.
     */
    public function run(int $post_id): void
    {
        try {
            $this->index($post_id);
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-gated diagnostic only.
                error_log(sprintf('[champlin-internal-linker] index failed for post %d: %s', $post_id, $e->getMessage()));
            }
            throw $e;
        }
    }

    public function index(int $post_id): bool
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
            return false;
        }
        if (!$this->provider_factory->is_configured()) {
            return false;
        }

        $title      = (string) $post->post_title;
        $content    = $this->extractor->extract($post);
        $raw        = trim($title . "\n\n" . $content);
        $normalized = $this->normalizer->normalize($raw);
        if ($normalized === '') {
            return false;
        }

        $hash = $this->normalizer->hash($normalized);
        if ($this->vector_store->content_hash($post_id) === $hash) {
            return true; // Up to date.
        }

        $provider = $this->provider_factory->create();
        $chunks   = $this->normalizer->chunk($normalized);
        if ($chunks === []) {
            return false;
        }

        if (count($chunks) === 1) {
            $vector = $provider->embed($chunks[0]);
        } else {
            $vectors = [];
            foreach ($chunks as $chunk) {
                $vectors[] = $provider->embed($chunk);
            }
            $vector = $this->cosine->mean_pool($vectors);
        }

        if ($vector === []) {
            return false;
        }

        return $this->vector_store->upsert($post_id, $vector, $provider->model(), $hash);
    }
}
