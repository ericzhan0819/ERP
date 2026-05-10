<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class StaffManagementController extends Controller
{
    /**
     * 技術註解：集中白名單，避免任意 permission 被寫入。
     */
    private const ASSIGNABLE_PERMISSIONS = [
        'module.accounting',
        'module.crm',
        'widget.financial_health',
    ];

    public function index(): Response
    {
        $staff = User::query()
            ->with(['roles:name', 'permissions:name'])
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->values(),
                'permissions' => $user->permissions->pluck('name')->values(),
            ]);

        $permissions = Permission::query()
            ->orderBy('name')
            ->get(['name'])
            ->pluck('name');

        // 依照 permission 命名規則（module.action）自動分組，避免前端硬編碼模塊。
        $permissionMatrix = $permissions
            ->groupBy(function (string $permissionName): string {
                $firstDotPosition = strpos($permissionName, '.');

                return $firstDotPosition === false
                    ? 'general'
                    : substr($permissionName, 0, $firstDotPosition);
            })
            ->map(fn ($groupPermissions, string $module) => [
                'module' => $module,
                'permissions' => $groupPermissions->values(),
            ])
            ->values();

        return Inertia::render('StaffManagement/Index', [
            'staff' => $staff,
            'permissionMatrix' => $permissionMatrix,
            'roles' => Role::query()->orderBy('name')->pluck('name')->values(),
            'assignablePermissions' => self::ASSIGNABLE_PERMISSIONS,
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', self::ASSIGNABLE_PERMISSIONS)],
        ]);

        $permissions = array_values(array_unique($validated['permissions'] ?? []));

        // 技術註解：角色採單一主角色同步，符合目前系統結構與最小改動策略。
        $user->syncRoles([$validated['role']]);
        $user->syncPermissions($permissions);

        Log::info('staff_permissions_updated', [
            'actor_id' => $request->user()?->id,
            'target_user_id' => $user->id,
            'role' => $validated['role'],
            'permissions' => $permissions,
        ]);

        return response()->json([
            'message' => '員工權限更新成功。',
        ]);
    }
}
