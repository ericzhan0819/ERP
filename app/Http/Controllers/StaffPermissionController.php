<?php

namespace App\Http\Controllers;

use App\Models\Module;
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
    /**
     * @var array<string>
     */
    private const DEPRECATED_MATRIX_PERMISSIONS = [
        'module.vehicle.view',
        'module.vehicle.create',
        'module.vehicle.update',
        'module.vehicle.delete',
        'module.vehicle.export',
    ];

    /**
     * @var array<string, string>
     */
    private const SUB_SCOPE_LABELS = [
        'vehicles.pricing' => '車輛價格',
        'vehicles.costs' => '車輛成本',
        'vehicles.sales' => '車輛銷售',
        'vehicles.sales.payments' => '車輛銷售收款',
        'vehicles.sales.completion' => '交易完成',
        'receivables' => '收款管理',
        'accounting.events' => '會計事件',
        'customers.sensitive' => '客戶個資',
    ];

    /**
     * @var array<string, string>
     */
    private const MODULE_LABELS = [
        'customers' => '客戶管理',
        'receivables' => '收款管理',
        'accounting' => '會計管理',
        'vehicles' => '車輛管理',
    ];

    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request): Response
    {
        $currentUser = $request->user();

        // 技術註解：後端強制雙權限其一，防止僅靠前端可見性造成越權讀取（Broken Access Control）。
        abort_unless(
            $currentUser->can('staff-permission.view') || $currentUser->can('module.permissions.view'),
            403
        );

        $actions = ['view', 'create', 'update', 'delete', 'export', 'approve', 'post', 'void', 'mark-sold', 'confirm', 'complete', 'review', 'manage'];

        $roles = Role::query()
            ->withCount('users')
            ->with('permissions:name')
            ->orderBy('name')
            ->get(['id', 'name', 'label'])
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label,
                'description' => $role->label,
                'is_system_role' => in_array($role->name, ['admin', 'owner'], true),
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions->count(),
            ])
            ->values();

        $permissions = Permission::query()
            ->whereNotIn('name', self::DEPRECATED_MATRIX_PERMISSIONS)
            // 技術註解：矩陣 UI 僅顯示正式命名群組，避免使用者誤勾 deprecated 權限導致權限來源混亂。
            ->where('group', '!=', 'Deprecated')
            ->orderBy('name')
            ->get(['name', 'label', 'group']);
        $permissionNames = $permissions->pluck('name')->flip();

        $moduleLabels = [];
        $permissionMatrix = [];
        foreach ($permissions as $permission) {
            if (!str_starts_with($permission->name, 'module.')) {
                continue;
            }

            $segments = explode('.', $permission->name);
            if (! in_array(count($segments), [3, 4, 5], true)) {
                continue;
            }

            [, $moduleKey] = $segments;
            $action = $segments[count($segments) - 1];
            if ($moduleKey === 'vehicle') {
                // 技術註解：單數 module.vehicle.* 為過渡期權限，不可進入矩陣以防與正式 module.vehicles.* 並存造成錯誤授權判讀。
                continue;
            }
            if (!in_array($action, $actions, true)) {
                continue;
            }

            $matrixKey = count($segments) >= 4
                ? $moduleKey.'.'.implode('.', array_slice($segments, 2, -1))
                : $moduleKey;

            $moduleLabels[$moduleKey] = $moduleLabels[$moduleKey] ?? (self::MODULE_LABELS[$moduleKey] ?? ucfirst(str_replace('-', ' ', $moduleKey)));
            $matrixLabel = count($segments) >= 4
                ? (self::SUB_SCOPE_LABELS[$matrixKey] ?? ucfirst(str_replace(['-', '.'], ' ', $matrixKey)))
                : $moduleLabels[$moduleKey];

            $permissionMatrix[$matrixKey] ??= [
                'label' => $matrixLabel,
                'actions' => [],
            ];
            $permissionMatrix[$matrixKey]['actions'][$action] = [
                'permission' => $permission->name,
                'exists' => true,
            ];
        }

        foreach (array_keys($permissionMatrix) as $matrixKey) {
            $permissionMatrix[$matrixKey]['actions'] = collect($permissionMatrix[$matrixKey]['actions'])
                ->sortBy(fn (array $item, string $action): int => array_search($action, $actions, true))
                ->all();
        }

        $rolePermissionMap = Role::query()
            ->with('permissions:name')
            ->get(['id'])
            ->mapWithKeys(fn (Role $role) => [
                (string) $role->id => $role->permissions->pluck('name')->values()->all(),
            ]);

        return Inertia::render('StaffPermissions/Index', [
            'roles' => $roles,
            'modules' => Module::query()->orderBy('sort_order')->get(['key', 'label'])->values(),
            'permissions' => $permissions,
            'permissionMatrix' => $permissionMatrix,
            'actionLabels' => [
                'view' => '檢視',
                'create' => '新增',
                'update' => '更新',
                'delete' => '刪除',
                'export' => '匯出',
                'approve' => '核准',
                'post' => '過帳',
                'void' => '作廢',
                'mark-sold' => '標記成交',
                'confirm' => '確認',
                'complete' => '完成',
                'review' => '覆核',
                'manage' => '管理',
            ],
            'moduleLabels' => $moduleLabels,
            'capabilities' => [
                'canUpdatePermissions' => $currentUser->can('staff-permission.update-permission'),
            ],
            'rolePermissionMap' => $rolePermissionMap,
        ]);
    }

    public function updateRolePermissions(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->can('staff-permission.update-permission'), 403);

        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        $newPermissions = collect($validated['permissions'] ?? [])->values();

        if ($role->name === 'admin') {
            // 技術註解：保留最後管理角色關鍵權限，避免系統無人可修復授權設定。
            $required = collect(['module.permissions.view', 'staff-permission.update-permission']);
            abort_unless($required->every(fn (string $name) => $newPermissions->contains($name)), 422, '不得移除 admin 關鍵權限');
        }

        if ($request->user()->hasRole($role->name)) {
            // 技術註解：防止目前操作者把自己角色必要管理權限全移除而自鎖。
            $requiredForSelf = collect(['module.permissions.view', 'staff-permission.update-permission']);
            abort_unless($requiredForSelf->every(fn (string $name) => $newPermissions->contains($name)), 422, '不可移除自身必要管理權限');
        }

        $role->syncPermissions($newPermissions->all());

        return back()->with('success', '角色權限已更新');
    }

    public function createRole(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('staff-permission.update-role'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9\-]+$/', Rule::unique('roles', 'name')],
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        $this->permissionService->createRole($validated['name'], $validated['label'] ?? null);

        return back()->with('success', '角色已新增');
    }

    public function deleteRole(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->can('staff-permission.update-role'), 403);

        if (in_array($role->name, ['admin', 'owner'], true)) {
            abort(422, '系統角色不可刪除');
        }

        $this->permissionService->deleteRole($role);

        return back()->with('success', '角色已刪除');
    }

    public function updateRoleMeta(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->can('staff-permission.update-role'), 403);

        if (in_array($role->name, ['admin', 'owner'], true)) {
            // 技術註解：系統保留角色名稱同時作為授權識別鍵，禁止改名可避免既有安全策略失效。
            abort(422, '系統角色不可修改代碼');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9\-]+$/', Rule::unique('roles', 'name')->ignore($role->id)],
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        $this->permissionService->updateRoleMeta(
            $role,
            $validated['name'],
            $validated['label'] ?? null,
        );

        return back()->with('success', '角色資料已更新');
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
