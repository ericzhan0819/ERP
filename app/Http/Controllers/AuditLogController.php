<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\LoginLog;
use App\Support\AuditLogDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function activityLogs(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $event = trim((string) $request->query('event', ''));
        $userId = $request->query('user_id');

        $logs = ActivityLog::query()
            ->with(['user:id,name,email'])
            ->where(function (Builder $query) use ($user): void {
                $query->where('company_id', $user->company_id)
                    ->orWhereNull('company_id');
            })
            ->where(function (Builder $query): void {
                // 排除新資料中的登入事件，避免登入類事件混入操作稽核頁。
                $query->whereNull('event')
                    ->orWhere('event', 'not like', 'auth.%');
            })
            ->where(function (Builder $query): void {
                // 排除舊資料相容情境：event 為 null 但 action 為 auth.* 的登入事件。
                $query->whereNotNull('event')
                    ->orWhereNull('action')
                    ->orWhere('action', 'not like', 'auth.%');
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('action', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($event !== '', fn (Builder $query): Builder => $query->where('event', $event))
            ->when($userId !== null && $userId !== '', fn (Builder $query): Builder => $query->where('user_id', (int) $userId))
            ->latest('id')
            ->paginate(20)
            ->through(function (ActivityLog $log): array {
                $payload = $log->toArray();
                // 技術註解：顯示資訊由後端集中產生，避免前端依 raw module key 做權限或模組推斷造成顯示不一致。
                $payload['display'] = AuditLogDisplay::payload($log);

                return $payload;
            })
            ->withQueryString();

        return Inertia::render('Audit/ActivityLogs', [
            'logs' => $logs,
            'filters' => [
                'search' => $search,
                'event' => $event,
                'user_id' => $userId,
            ],
        ]);
    }

    public function loginLogs(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $event = trim((string) $request->query('event', ''));
        $userId = $request->query('user_id');

        $logs = LoginLog::query()
            ->with(['user:id,name,email'])
            ->where(function (Builder $query) use ($user): void {
                $query->where('company_id', $user->company_id)
                    ->orWhereNull('company_id');
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('event', 'like', "%{$search}%")
                        ->orWhere('login_identifier', 'like', "%{$search}%");
                });
            })
            ->when($event !== '', fn (Builder $query): Builder => $query->where('event', $event))
            ->when($userId !== null && $userId !== '', fn (Builder $query): Builder => $query->where('user_id', (int) $userId))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Audit/LoginLogs', [
            'logs' => $logs,
            'filters' => [
                'search' => $search,
                'event' => $event,
                'user_id' => $userId,
            ],
        ]);
    }
}
