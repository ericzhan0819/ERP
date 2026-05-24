<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\LoginLog;
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

