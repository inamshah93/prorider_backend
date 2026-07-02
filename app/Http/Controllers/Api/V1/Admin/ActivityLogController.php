<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()
            ->where('log_name', 'admin')
            ->with('causer')
            ->latest();

        if ($action = $request->get('action')) {
            $query->where('event', 'like', "%{$action}%");
        }

        if ($from = $request->get('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($userId = $request->get('user_id')) {
            $query->where('causer_type', User::class)->where('causer_id', $userId);
        }

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('event', 'like', "%{$search}%")
                    ->orWhere('properties->message', 'like', "%{$search}%")
                    ->orWhere('properties->entity', 'like', "%{$search}%")
                    ->orWhereHasMorph('causer', [User::class], function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $logs = $query->paginate(20);

        return response()->json([
            'data' => collect($logs->items())->map(fn (AuditLog $log) => $this->format($log))->values(),
            'meta' => [
                'page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    private function format(AuditLog $log): array
    {
        $props = $log->properties ?? [];
        $user = $log->causer;

        return [
            'id' => $log->id,
            'action' => $props['action'] ?? $log->event ?? $log->description,
            'entity' => $props['entity'] ?? null,
            'entity_id' => $props['entity_id'] ?? null,
            'message' => $props['message'] ?? $log->description,
            'ip' => $props['ip'] ?? null,
            'user_agent' => $props['user_agent'] ?? null,
            'created_at' => $log->created_at?->toIso8601String(),
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
            'context' => $props['context'] ?? null,
        ];
    }
}
