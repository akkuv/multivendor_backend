<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->input('module')))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->input('event')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->input('user_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');

                return $q->where(fn ($w) => $w->where('description', 'like', "%{$search}%")
                    ->orWhere('event', 'like', "%{$search}%"));
            })
            ->orderByDesc('created_at')
            ->paginate((int) ($request->input('per_page', 15)));

        return $this->paginated(__('Audit logs fetched successfully.'), $logs, fn ($items) => collect($items)->map(
            fn (AuditLog $log) => $this->shape($log),
        ));
    }

    /** @return array<string, mixed> */
    private function shape(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name, 'email' => $log->user->email] : null,
            'module' => $log->module,
            'event' => $log->event,
            'description' => $log->description,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'reference' => $log->reference_type ? [
                'type' => $log->reference_type,
                'id' => $log->reference_id,
            ] : null,
            'old_data' => $log->old_data,
            'new_data' => $log->new_data,
            'meta' => $log->meta,
            'created_at' => $log->created_at?->toISOString(),
        ];
    }
}
