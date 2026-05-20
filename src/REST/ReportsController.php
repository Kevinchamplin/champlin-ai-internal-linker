<?php
/**
 * GET  /cil/v1/reports/orphans
 * POST /cil/v1/reports/rescan
 *
 * @package Champlin\InternalLinker\REST
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\REST;

use Champlin\InternalLinker\Reports\LinkGraphScanner;
use Champlin\InternalLinker\Reports\OrphanReport;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class ReportsController extends BaseController
{
    public function __construct(
        private OrphanReport $orphan_report,
        private LinkGraphScanner $scanner
    ) {
    }

    public function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/reports/orphans',
            [
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => [$this, 'require_admin'],
                'callback'            => [$this, 'orphans'],
            ]
        );
        register_rest_route(
            self::NAMESPACE,
            '/reports/rescan',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => [$this, 'require_admin'],
                'callback'            => [$this, 'rescan'],
            ]
        );
    }

    public function orphans(WP_REST_Request $request): WP_REST_Response
    {
        return rest_ensure_response($this->orphan_report->generate(false));
    }

    public function rescan(WP_REST_Request $request): WP_REST_Response
    {
        $this->scanner->invalidate();
        return rest_ensure_response($this->orphan_report->generate(true));
    }
}
