<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    public function log(
        ?User $actor,
        string $action,
        ?string $description = null,
        ?User $targetUser = null,
        array $metadata = [],
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null,
        ?string $event = null,
    ): ActivityLog {
        $resolvedRequest = $request ?? request();

        return ActivityLog::create([
            'company_id' => $actor?->company_id,
            'branch_id' => $actor?->branch_id,
            'user_id' => $actor?->id,
            'target_user_id' => $targetUser?->id,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'action' => $action,
            'event' => $event ?? $action,
            'description' => $description,
            'metadata' => $metadata,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $resolvedRequest->ip(),
            'user_agent' => $resolvedRequest->userAgent(),
            'created_at' => now(),
        ]);
    }
}
