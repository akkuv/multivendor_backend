<?php

namespace App\Services\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    public function log(
        ?int $userId,
        string $event,
        ?string $description = null,
        ?Request $request = null,
        array $meta = [],
        ?string $module = null,
        ?Model $reference = null,
        array $oldData = [],
        array $newData = [],
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId,
            'module' => $module,
            'event' => $event,
            'description' => $description,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'meta' => $meta ?: null,
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id' => $reference?->getKey(),
            'old_data' => $oldData ?: null,
            'new_data' => $newData ?: null,
        ]);
    }

    /**
     * Record an action performed by a resolved user (or null) using the current request.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $oldData
     * @param  array<string, mixed>  $newData
     */
    public function action(
        ?User $user,
        string $module,
        string $event,
        ?string $description = null,
        array $meta = [],
        ?Model $reference = null,
        array $oldData = [],
        array $newData = [],
    ): AuditLog {
        return $this->log(
            $user?->id,
            $event,
            $description,
            request(),
            $meta,
            $module,
            $reference,
            $oldData,
            $newData,
        );
    }
}
