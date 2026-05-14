<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PermissionService;
use Illuminate\Support\Facades\DB;
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

        // 技術註解：在 Controller 內再次驗證讀取權限，避免僅依賴路由 middleware 造成授權邏輯漂移，
        // 導致敏感 RBAC/HR 權限矩陣被已登入但未授權者直接以 URL 存取。
        abort_unless($currentUser->can('staff-permission.view'), 403);

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
        // 技術註解：Controller 層二次授權可防止路由 middleware 設定漂移時發生越權寫入（Broken Access Control）。
        abort_unless($request->user()->can('staff-permission.update-role'), 403);

        $validated = $request->validate([
            'roles' => ['required', 'array', 'size:1'],
            'roles.*' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        $this->denySelfModification($request, $user);
        $oldRoles = $user->roles()->pluck('name')->values()->all();

        // 技術註解：角色同步統一委派 PermissionService，避免 Controller 直接操作 Spatie RBAC 寫入流程。
        $newRole = $validated['roles'][0];

        DB::transaction(function () use ($request, $user, $oldRoles, $newRole): void {
            // 技術註解：RBAC 寫入與稽核紀錄必須同交易提交，避免權限已改但 audit log 失敗造成不可追溯。
            $this->permissionService->syncUserRole($user, $newRole);
            $this->auditLogService->log(
                $request->user(),
                'staff-permission.role.updated',
                'Updated employee role',
                $user,
                [
                    'old_roles' => $oldRoles,
                    'new_role' => $newRole,
                ]
            );
        });

        return back()->with('success', '員工角色已更新');
    }

    public function updatePermissions(Request $request, User $user): RedirectResponse
    {
        // 技術註解：優先使用既有 update-permissions 權限，若專案仍沿用單數命名則作為相容 fallback，避免部署期間授權中斷。
        abort_unless(
            $request->user()->can('staff-permission.update-permissions')
                || $request->user()->can('staff-permission.update-permission'),
            403
        );

        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->denySelfModification($request, $user);
        $oldPermissions = $user->permissions()->pluck('name')->values()->all();
        $newPermissions = $validated['permissions'] ?? [];

        // 技術註解：直接權限只透過 PermissionService 同步到目標使用者，不新增任何財務或 HR 權限結構。
        DB::transaction(function () use ($request, $user, $oldPermissions, $newPermissions): void {
            // 技術註解：直接權限同步與稽核紀錄同交易處理，避免部分成功導致授權異動與稽核資料不一致。
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
        });

        return back()->with('success', '員工直接權限已更新');
    }

    private function denySelfModification(Request $request, User $user): void
    {
        if ($request->user()->is($user)) {
            abort(403, '不可修改自己的權限，避免管理權限鎖死');
        }
    }
}
