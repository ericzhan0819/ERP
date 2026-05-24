<?php

namespace App\Services;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;

class LoginLogService
{
    public function recordSuccess(Request $request, ?User $user, ?string $identifier = null): LoginLog
    {
        return $this->createLog($request, 'auth.login.success', $user, $identifier);
    }

    public function recordFailed(Request $request, string $identifier, ?string $reason = null): LoginLog
    {
        return $this->createLog($request, 'auth.login.failed', null, $identifier, [
            'reason' => $reason,
        ]);
    }

    public function recordLogout(Request $request, ?User $user): LoginLog
    {
        return $this->createLog($request, 'auth.logout', $user, $user?->email);
    }

    public function recordInactive(Request $request, ?User $user, ?string $identifier = null): LoginLog
    {
        return $this->createLog($request, 'auth.login.inactive', $user, $identifier, [
            'reason' => 'inactive_account',
        ]);
    }

    private function createLog(Request $request, string $event, ?User $user, ?string $identifier = null, array $metadata = []): LoginLog
    {
        // 技術註解：僅記錄識別字與設備資訊，避免寫入 password/token 造成認證敏感資訊洩漏風險。
        return LoginLog::create([
            'company_id' => $user?->company_id,
            'branch_id' => $user?->branch_id,
            'user_id' => $user?->id,
            'login_identifier' => $identifier,
            'event' => $event,
            'guard' => 'web',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}

