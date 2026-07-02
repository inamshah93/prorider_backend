<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogAdminAudit
{
    public function __construct(private AuditLogService $auditLog) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $request->user()
            && $request->is('api/v1/admin/*')
            && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && $response->isSuccessful()
            && $request->header('X-Audit-Action')
        ) {
            $this->auditLog->recordFromRequest($request);
        }

        return $response;
    }
}
