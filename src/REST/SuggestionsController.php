<?php
/**
 * GET /cil/v1/suggestions/{post_id}
 * POST /cil/v1/suggestions/{post_id}/accept
 *
 * @package Champlin\InternalLinker\REST
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\REST;

use Champlin\InternalLinker\Engine\SuggestionEngine;
use Champlin\InternalLinker\Storage\SuggestionLog;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class SuggestionsController extends BaseController
{
    public function __construct(
        private SuggestionEngine $engine,
        private SuggestionLog $log
    ) {
    }

    public function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/suggestions/(?P<post_id>\d+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => [$this, 'require_editor'],
                'callback'            => [$this, 'get_suggestions'],
                'args'                => [
                    'post_id'   => [
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn($v) => is_numeric($v) && (int) $v > 0,
                    ],
                    'limit'     => [
                        'default'           => 5,
                        'sanitize_callback' => 'absint',
                    ],
                    'threshold' => [
                        'default'           => 0.75,
                        'sanitize_callback' => static fn($v) => (float) $v,
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/suggestions/(?P<post_id>\d+)/accept',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => [$this, 'require_editor'],
                'callback'            => [$this, 'accept_suggestion'],
                'args'                => [
                    'post_id'        => [
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'target_post_id' => [
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );
    }

    public function get_suggestions(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $post_id   = (int) $request['post_id'];
        $limit     = max(1, min(50, (int) $request['limit']));
        $threshold = max(0.0, min(1.0, (float) $request['threshold']));

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error(
                'cil_forbidden',
                __('You cannot edit this post.', 'champlin-ai-internal-linker'),
                ['status' => 403]
            );
        }

        $excluded = $this->log->accepted_targets_for($post_id);
        $results  = $this->engine->suggestions_for($post_id, $limit, $threshold, $excluded);

        foreach ($results as $row) {
            $this->log->record_suggestion($post_id, $row['post_id'], $row['similarity']);
        }

        return rest_ensure_response($results);
    }

    public function accept_suggestion(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $post_id        = (int) $request['post_id'];
        $target_post_id = (int) $request['target_post_id'];

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error(
                'cil_forbidden',
                __('You cannot edit this post.', 'champlin-ai-internal-linker'),
                ['status' => 403]
            );
        }

        $this->log->mark_accepted($post_id, $target_post_id);
        return rest_ensure_response(['accepted' => true]);
    }
}
