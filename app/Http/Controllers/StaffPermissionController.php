<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StaffPermissionController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request): Response
    {
        $currentUser = $request->user();

        return Inertia::render('StaffPermissions/Index', [
            // 技術註解：權限管理頁僅輸出識別與 RBAC 狀態，不承載 HR 基本資料 CRUD。
            'users' => User::query()
                ->with(['roles:id,name', 'permissions:id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'is_active', 'last_login_at'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'last_login_at' => $user->last_login_at?->toISOString(),
                    'phone' => $user->phone ?? null,
                    'roles' => $user->roles->pluck('name')->values(),
                    'direct_permissions' => $user->permissions->pluck('name')->values(),
                ]),
            'roles' => Role::query()
                ->orderBy('name')
                ->get(['name', 'label']),
            'permissions' => Permission::query()
                ->orderBy('group')
                ->orderBy('name')
                ->get([
                    'name',
                    'label',
                    'group',
                ]),
            'can' => [
                'updateRole' => $currentUser->can('staff-permission.update-role'),
                'updatePermission' => $currentUser->can('staff-permission.update-permission'),
            ],
        ]);
    }

    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'roles' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        $this->denySelfModification($request, $user);
        $oldRoles = $user->roles()->pluck('name')->values()->all();

        // 技術註解：角色同步統一委派 PermissionService，避免 Controller 直接操作 Spatie RBAC 寫入流程。
        $this->permissionService->syncUserRole($user, $validated['roles']);
        $this->auditLogService->log(
            $request->user(),
            'staff-permission.role.updated',
            'Updated employee role',
            $user,
            [
                'old_roles' => $oldRoles,
                'new_role' => $validated['roles'],
            ]
        );

        return back()->with('success', '員工角色已更新');
    }

    public function updatePermissions(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->denySelfModification($request, $user);
        $oldPermissions = $user->permissions()->pluck('name')->values()->all();
        $newPermissions = $validated['permissions'] ?? [];

        // 技術註解：直接權限只透過 PermissionService 同步到目標使用者，不新增任何財務或 HR 權限結構。
        $this->permissionService->syncUserPermissions($user, $newPermissions);
        $this->auditLogService->log(
            $request->user(),
            'staff-permission.permissions.updated',
            'Updated employee direct permissions',
            $user,
            [
                'old_permissions' => $oldPermissions,
                'new_permissions' => $newPermissions,
            ]
        );

        return back()->with('success', '員工直接權限已更新');
    }

    private function denySelfModification(Request $request, User $user): void
    {
        if ($request->user()->is($user)) {
            abort(403, '不可修改自己的權限，避免管理權限鎖死');
        }
    }
}
