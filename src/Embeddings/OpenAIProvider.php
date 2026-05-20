<?php
/**
 * OpenAI Embeddings v1 provider.
 *
 * @package Champlin\InternalLinker\Embeddings
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Embeddings;

use RuntimeException;

final class OpenAIProvider implements ProviderInterface
{
    private const ENDPOINT = 'https://api.openai.com/v1/embeddings';

    /**
     * Known model → dimension mapping. Keeps us from having to round-trip the API
     * just to learn dimensionality for storage planning.
     */
    private const MODEL_DIMENSIONS = [
        'text-embedding-3-small' => 1536,
        'text-embedding-3-large' => 3072,
        'text-embedding-ada-002' => 1536,
    ];

    public function __construct(
        private string $api_key,
        private string $model = 'text-embedding-3-small',
        private int $timeout_seconds = 20,
        private int $max_retries = 3
    ) {
        if (trim($this->api_key) === '') {
            throw new RuntimeException('OpenAI API key is missing.');
        }
    }

    public function model(): string
    {
        return $this->model;
    }

    public function dimensions(): int
    {
        return self::MODEL_DIMENSIONS[$this->model] ?? 1536;
    }

    public function embed(string $text): array
    {
        if (trim($text) === '') {
            throw new RuntimeException('Cannot embed empty text.');
        }

        $body = wp_json_encode([
            'model' => $this->model,
            'input' => $text,
        ]);

        if ($body === false) {
            throw new RuntimeException('Failed to encode embedding request payload.');
        }

        $attempt    = 0;
        $last_error = '';
        while ($attempt < $this->max_retries) {
            $response = wp_remote_request(self::ENDPOINT, [
                'method'  => 'POST',
                'timeout' => $this->timeout_seconds,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => $body,
            ]);

            if (!is_wp_error($response)) {
                $code = (int) wp_remote_retrieve_response_code($response);
                $raw  = (string) wp_remote_retrieve_body($response);

                if ($code >= 200 && $code < 300) {
                    return $this->parse_vector($raw);
                }

                // Retry on transient failures only.
                if ($code !== 429 && ($code < 500 || $code > 599)) {
                    throw new RuntimeException(sprintf('OpenAI returned HTTP %d: %s', $code, $raw));
                }
                $last_error = sprintf('HTTP %d: %s', $code, $raw);
            } else {
                $last_error = $response->get_error_message();
            }

            $attempt++;
            if ($attempt < $this->max_retries) {
                // Exponential backoff: 1s, 2s, 4s.
                usleep((int) (1_000_000 * (2 ** ($attempt - 1))));
            }
        }

        throw new RuntimeException('OpenAI embedding failed after retries: ' . $last_error);
    }

    /**
     * @return float[]
     */
    private function parse_vector(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['data'][0]['embedding']) || !is_array($decoded['data'][0]['embedding'])) {
            throw new RuntimeException('OpenAI response missing embedding payload.');
        }
        return array_map(static fn($v): float => (float) $v, $decoded['data'][0]['embedding']);
    }
}
