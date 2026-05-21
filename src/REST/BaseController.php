<?php
/**
 * Shared auth + nonce + capability checks for REST controllers.
 *
 * @package Champlin\InternalLinker\REST
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\REST;

use WP_Error;
use WP_REST_Request;

abstract class BaseController
{
    public const NAMESPACE = 'cil/v1';

    abstract public function register_routes(): void;

    /**
     * Permission callback for editor-level routes.
     *
     * @return true|WP_Error
     */
    public function require_editor(WP_REST_Request $request): bool|WP_Error
    {
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'cil_forbidden',
                __('You do not have permission to use the internal linker.', 'champlin-ai-internal-linker'),
                ['status' => 403]
            );
        }
        $nonce_check = $this->verify_rest_nonce($request);
        if ($nonce_check instanceof WP_Error) {
            return $nonce_check;
        }
        return true;
    }

    /**
     * Permission callback for admin-level routes (e.g. bulk re-index).
     *
     * @return true|WP_Error
     */
    public function require_admin(WP_REST_Request $request): bool|WP_Error
    {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'cil_forbidden',
                __('Administrator capability required.', 'champlin-ai-internal-linker'),
                ['status' => 403]
            );
        }
        $nonce_check = $this->verify_rest_nonce($request);
        if ($nonce_check instanceof WP_Error) {
            return $nonce_check;
        }
        return true;
    }

    /**
     * @return true|WP_Error
     */
    protected function verify_rest_nonce(WP_REST_Request $request): bool|WP_Error
    {
        $nonce = $request->get_header('x_wp_nonce');
        if ($nonce === null || !wp_verify_nonce((string) $nonce, 'wp_rest')) {
            return new WP_Error(
                'cil_bad_nonce',
                __('Invalid or expired security token.', 'champlin-ai-internal-linker'),
                ['status' => 403]
            );
        }
        return true;
    }
}
