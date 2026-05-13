<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;

class AuditLogService
{
    public function log(
        ?User $actor,
        string $action,
        ?string $description = null,
        ?User $targetUser = null,
        array $metadata = []
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $actor?->id,
            'target_user_id' => $targetUser?->id,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
