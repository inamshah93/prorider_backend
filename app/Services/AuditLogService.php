<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogService
{
    public function record(
        ?User $user,
        string $action,
        ?string $entity = null,
        string|int|null $entityId = null,
        ?string $message = null,
        ?array $context = null,
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::create([
            'log_name' => 'admin',
            'description' => $message ?? $action,
            'event' => $action,
            'causer_type' => $user ? User::class : null,
            'causer_id' => $user?->id,
            'properties' => [
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'message' => $message,
                'context' => $context,
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ],
        ]);
    }

    public function recordFromRequest(Request $request): ?AuditLog
    {
        $action = $request->header('X-Audit-Action');
        if (! $action) {
            return null;
        }

        $context = $request->header('X-Audit-Context');
        $decodedContext = $context ? json_decode($context, true) : null;

        return $this->record(
            user: $request->user(),
            action: $action,
            entity: $request->header('X-Audit-Entity'),
            entityId: $request->header('X-Audit-Entity-Id'),
            message: $request->header('X-Audit-Message'),
            context: is_array($decodedContext) ? $decodedContext : null,
            request: $request,
        );
    }
}
