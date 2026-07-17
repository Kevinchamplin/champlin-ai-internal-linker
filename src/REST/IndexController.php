<?php
/**
 * POST /chail/v1/index/start    — Begin a bulk re-index.
 * GET  /chail/v1/index/progress — Poll progress.
 *
 * @package Champlin\InternalLinker\REST
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\REST;

use Champlin\InternalLinker\Indexing\BulkIndexer;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class IndexController extends BaseController
{
    public function __construct(private BulkIndexer $bulk_indexer)
    {
    }

    public function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/index/start',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => [$this, 'require_admin'],
                'callback'            => [$this, 'start'],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/index/progress',
            [
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => [$this, 'require_admin'],
                'callback'            => [$this, 'progress'],
            ]
        );
    }

    public function start(WP_REST_Request $request): WP_REST_Response
    {
        return rest_ensure_response($this->bulk_indexer->start());
    }

    public function progress(WP_REST_Request $request): WP_REST_Response
    {
        return rest_ensure_response($this->bulk_indexer->progress());
    }
}
