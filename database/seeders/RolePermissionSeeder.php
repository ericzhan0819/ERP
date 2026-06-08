<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * 權限 action 白名單（僅允許統一命名的固定動作）。
     */
    private const ACTION_WHITELIST = ['view', 'create', 'update', 'delete', 'export', 'approve', 'post', 'void', 'mark-sold', 'confirm', 'complete', 'review', 'manage'];

    /**
     * Seed the application's RBAC foundation.
     */
    public function run(): void
    {
        // 技術註解：重建最小 RBAC 快取，確保 fresh seed 後權限判斷立即一致。
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $brandCopyColumns = ['brand_name', 'brand_name_en', 'brand_slogan', 'brand_eyebrow'];
        $hasAllBrandCopyColumns = collect($brandCopyColumns)
            ->every(static fn (string $column): bool => Schema::hasColumn('companies', $column));

        $companyDefaults = [
            'name' => 'OO INTERNATIONAL',
        ];

        if ($hasAllBrandCopyColumns) {
            // 技術註解：僅在欄位完整存在時才回填品牌文案，避免 schema 未同步時 seed 直接失敗。
            $companyDefaults = array_merge($companyDefaults, [
                'brand_name' => 'OO國際車業',
                'brand_name_en' => 'OO INTERNATIONAL',
                'brand_slogan' => '擇車如擇友，敘白如敘舊',
                'brand_eyebrow' => 'EST. 2026',
            ]);
        }

        if (Schema::hasColumn('companies', 'brand_subtitle')) {
            // 技術註解：brand_subtitle 屬既有欄位，僅在存在時寫入以維持 seed 向下相容。
            $companyDefaults['brand_subtitle'] = '以「絕對透明、系統秩序、專業可靠」為核心，建立擇車如擇友的中古車管理中樞。';
        }

        // 技術註解：先建立預設租戶邊界，避免管理員或員工帳號落在 company_id=0 / branch_id=null 造成後續資料污染。
        $defaultCompany = Company::updateOrCreate(
            ['code' => 'OO'],
            $companyDefaults
        );

        // 技術註解：分店以 company + code 作為穩定鍵，可重複執行 seed 且不會建立重複資料。
        $defaultBranch = Branch::updateOrCreate(
            [
                'company_id' => $defaultCompany->id,
                'code' => 'MAIN',
            ],
            ['name' => 'Default Branch']
        );

        $modules = [
            'dashboard' => [
                'label' => '總覽',
                'section' => 'system',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.overview',
                'permission_prefix' => 'module.dashboard',
                'base_permission' => 'module.dashboard.view',
                'icon_key' => 'LayoutDashboard',
                'icon' => 'LayoutDashboard',
                'sort_order' => 10,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => ['employee-system.overview'],
            ],
            'test-module' => [
                'label' => '測試模塊',
                'section' => 'testing',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.test-module',
                'permission_prefix' => 'module.test-module',
                'base_permission' => 'module.test-module.view',
                'icon_key' => 'FlaskConical',
                'icon' => 'FlaskConical',
                'sort_order' => 90,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => ['employee-system.test-module'],
            ],
            'staff-permission' => [
                'label' => '員工權限管理',
                'section' => 'administration',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.staff-permissions.index',
                'permission_prefix' => 'staff-permission',
                'base_permission' => 'staff-permission.view',
                'icon_key' => 'ShieldCheck',
                'icon' => 'ShieldCheck',
                'sort_order' => 20,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => [
                    'employee-system.staff-permissions.*',
                ],
            ],
            'vehicles' => [
                'label' => '車輛管理',
                'section' => 'operations',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.vehicles.index',
                'permission_prefix' => 'module.vehicles',
                'base_permission' => 'module.vehicles.view',
                'icon_key' => 'car',
                'icon' => 'car',
                'sort_order' => 30,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => ['vehicles.*'],
            ],
            'customers' => [
                'label' => '客戶管理',
                'section' => 'operations',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.customers.index',
                'permission_prefix' => 'module.customers',
                'base_permission' => 'module.customers.view',
                'icon_key' => 'employees',
                'icon' => 'employees',
                'sort_order' => 35,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => ['employee-system.customers.*'],
            ],
            'vehicle-costs' => [
                'label' => '車輛成本管理',
                'section' => 'operations',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.vehicle-costs.index',
                'permission_prefix' => 'module.vehicles.costs',
                'base_permission' => 'module.vehicles.costs.view',
                'icon_key' => 'Receipt',
                'icon' => 'Receipt',
                'sort_order' => 36,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => ['employee-system.vehicle-costs.*'],
            ],
            'receivables' => [
                'label' => '收款管理',
                'section' => 'operations',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.receivables.index',
                'permission_prefix' => 'module.receivables',
                'base_permission' => 'module.receivables.view',
                'icon_key' => 'Receipt',
                'icon' => 'Receipt',
                'sort_order' => 37,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => ['employee-system.receivables.*'],
            ],
            'accounting' => [
                'label' => '會計管理',
                'section' => 'accounting',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => null,
                'permission_prefix' => 'module.accounting',
                'base_permission' => 'module.accounting.view',
                'icon_key' => 'Receipt',
                'icon' => 'Receipt',
                'sort_order' => 38,
                'is_enabled' => false,
                'is_active' => true,
                'active_patterns' => [],
            ],
            'accounting-accounts' => [
                'label' => '會計科目',
                'section' => 'accounting',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.accounting.accounts.index',
                'permission_prefix' => 'module.accounting.accounts',
                'base_permission' => 'module.accounting.accounts.view',
                'icon_key' => 'Receipt',
                'icon' => 'Receipt',
                'sort_order' => 39,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => ['employee-system.accounting.accounts.*'],
            ],
            'accounting-journals' => [
                'label' => '會計傳票',
                'section' => 'accounting',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.accounting.journal-entries.index',
                'permission_prefix' => 'module.accounting.journals',
                'base_permission' => 'module.accounting.journals.view',
                'icon_key' => 'Receipt',
                'icon' => 'Receipt',
                'sort_order' => 40,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => ['employee-system.accounting.journal-entries.*'],
            ],
            'accounting-events' => [
                'label' => '會計事件',
                'section' => 'accounting',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.accounting.events.index',
                'permission_prefix' => 'module.accounting.events',
                'base_permission' => 'module.accounting.events.view',
                'icon_key' => 'Receipt',
                'icon' => 'Receipt',
                'sort_order' => 41,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => ['employee-system.accounting.events.*'],
            ],
            'audit' => [
                'label' => '稽核紀錄',
                'section' => 'administration',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.audit.activity-logs',
                'permission_prefix' => 'module.audit',
                'base_permission' => 'module.audit.view',
                'icon_key' => 'ShieldCheck',
                'icon' => 'ShieldCheck',
                'sort_order' => 40,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => ['employee-system.audit.*'],
            ],
            'company-settings' => [
                'label' => '公司設定',
                'section' => 'administration',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.company-settings.edit',
                'permission_prefix' => 'module.company-settings',
                'base_permission' => 'module.company-settings.view',
                'icon_key' => 'ShieldCheck',
                'icon' => 'ShieldCheck',
                'sort_order' => 50,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => ['employee-system.company-settings.*'],
            ],
        ];

        $routeNameIsNullable = collect(Schema::getColumns('modules'))
            ->firstWhere('name', 'route_name')['nullable'] ?? false;

        foreach ($modules as $key => $module) {
            if (! $routeNameIsNullable && $module['route_name'] === null) {
                // 技術註解：目前既有 modules.route_name schema 仍為 NOT NULL；disabled 相容模組以空字串保存，避免新增 migration 仍不產生可點擊 Sidebar 入口。
                $module['route_name'] = '';
            }

            // 技術註解：updateOrCreate 讓模組註冊可重跑，並保持 seed 結果單一真實來源。
            Module::updateOrCreate(['key' => $key], $module);
        }

        $permissionDefinitions = [
            // 技術註解：主要邏輯已切換到 module.{module_key}.{action} 統一命名。
            'module.dashboard.view' => ['label' => '查看總覽', 'group' => '系統'],
            'module.staff.view' => ['label' => '查看員工資料', 'group' => '人事'],
            'module.permissions.view' => ['label' => '查看權限管理', 'group' => '權限管理'],
            'module.permissions.update' => ['label' => '更新權限管理', 'group' => '權限管理'],
            'module.vehicles.view' => ['label' => '查看車輛', 'group' => '車輛'],
            'module.vehicles.create' => ['label' => '建立車輛', 'group' => '車輛'],
            'module.vehicles.update' => ['label' => '更新車輛', 'group' => '車輛'],
            'module.vehicles.delete' => ['label' => '刪除車輛', 'group' => '車輛'],
            'module.vehicles.export' => ['label' => '匯出車輛', 'group' => '車輛'],
            'module.vehicles.pricing.view' => ['label' => '查看車輛價格', 'group' => '車輛價格'],
            'module.vehicles.pricing.update' => ['label' => '更新車輛價格', 'group' => '車輛價格'],
            'module.vehicles.costs.view' => ['label' => '查看車輛成本', 'group' => '車輛成本'],
            'module.vehicles.costs.create' => ['label' => '建立車輛成本', 'group' => '車輛成本'],
            'module.vehicles.costs.update' => ['label' => '更新車輛成本', 'group' => '車輛成本'],
            'module.vehicles.sales.view' => ['label' => '查看車輛銷售', 'group' => '車輛銷售'],
            'module.vehicles.sales.create' => ['label' => '建立車輛銷售', 'group' => '車輛銷售'],
            'module.vehicles.sales.update' => ['label' => '更新車輛銷售', 'group' => '車輛銷售'],
            'module.vehicles.sales.payments.view' => ['label' => '查看銷售收款', 'group' => '車輛銷售收款'],
            'module.vehicles.sales.payments.create' => ['label' => '建立銷售收款', 'group' => '車輛銷售收款'],
            'module.vehicles.sales.payments.void' => ['label' => '作廢銷售收款', 'group' => '車輛銷售收款'],
            'module.vehicles.sales.completion.view' => ['label' => '查看交易完成', 'group' => '交易完成'],
            'module.vehicles.sales.completion.confirm' => ['label' => '確認交易完成', 'group' => '交易完成'],
            'module.receivables.view' => ['label' => '查看收款管理', 'group' => '收款管理'],
            'module.receivables.create' => ['label' => '建立收款', 'group' => '收款管理'],
            'module.receivables.void' => ['label' => '作廢收款', 'group' => '收款管理'],
            'module.receivables.mark-sold' => ['label' => '標記收款成交', 'group' => '收款管理'],
            'module.accounting.view' => ['label' => '查看會計管理', 'group' => '會計管理'],
            'module.accounting.accounts.view' => ['label' => '查看會計科目', 'group' => '會計科目'],
            'module.accounting.accounts.create' => ['label' => '建立會計科目', 'group' => '會計科目'],
            'module.accounting.accounts.update' => ['label' => '更新會計科目', 'group' => '會計科目'],
            'module.accounting.journals.view' => ['label' => '查看會計傳票', 'group' => '會計傳票'],
            'module.accounting.journals.create' => ['label' => '建立會計傳票', 'group' => '會計傳票'],
            'module.accounting.journals.update' => ['label' => '更新會計傳票', 'group' => '會計傳票'],
            'module.accounting.journals.post' => ['label' => '過帳會計傳票', 'group' => '會計傳票'],
            'module.accounting.journals.void' => ['label' => '作廢會計傳票', 'group' => '會計傳票'],
            'module.accounting.events.view' => ['label' => '查看會計事件', 'group' => '會計事件'],
            'module.accounting.events.review' => ['label' => '覆核會計事件', 'group' => '會計事件'],
            'module.customers.view' => ['label' => '查看客戶', 'group' => '客戶管理'],
            'module.customers.create' => ['label' => '建立客戶', 'group' => '客戶管理'],
            'module.customers.update' => ['label' => '更新客戶', 'group' => '客戶管理'],
            'module.customers.sensitive.view' => ['label' => '查看客戶個資', 'group' => '客戶個資'],
            'module.customers.sensitive.update' => ['label' => '更新客戶個資', 'group' => '客戶個資'],
            'module.audit.view' => ['label' => '查看稽核紀錄', 'group' => '系統稽核'],
            'module.company-settings.view' => ['label' => '查看公司設定', 'group' => '系統設定'],
            'module.company-settings.update' => ['label' => '更新公司設定', 'group' => '系統設定'],
            'module.test-module.view' => ['label' => '查看測試模塊', 'group' => '測試'],
        ];

        // 技術註解：deprecated 相容層僅為過渡期保留，避免舊檢查點造成中斷；新功能不得再依賴舊命名。
        $deprecatedPermissionDefinitions = [
            'staff-permission.view' => ['label' => '[Deprecated] 查看員工權限管理', 'group' => 'Deprecated'],
            'staff-permission.update-role' => ['label' => '[Deprecated] 變更員工角色', 'group' => 'Deprecated'],
            'staff-permission.update-permission' => ['label' => '[Deprecated] 變更員工直接權限', 'group' => 'Deprecated'],
            'vehicle.view' => ['label' => '[Deprecated] 查看車輛', 'group' => 'Deprecated'],
            'module.vehicle.view' => ['label' => '[Deprecated] 查看車輛（單數 module）', 'group' => 'Deprecated'],
            'module.vehicle.create' => ['label' => '[Deprecated] 建立車輛（單數 module）', 'group' => 'Deprecated'],
            'module.vehicle.update' => ['label' => '[Deprecated] 更新車輛（單數 module）', 'group' => 'Deprecated'],
            'module.vehicle.delete' => ['label' => '[Deprecated] 刪除車輛（單數 module）', 'group' => 'Deprecated'],
            'module.vehicle.export' => ['label' => '[Deprecated] 匯出車輛（單數 module）', 'group' => 'Deprecated'],
        ];

        $permissionDefinitions = $permissionDefinitions + $deprecatedPermissionDefinitions;

        $permissions = collect($permissionDefinitions)
            ->filter(function (array $definition, string $name): bool {
                // 技術註解：統一命名允許 module.{module_key}.{action} 與 module.{module_key}.{sub_scope}.{action}，並以最後一段作為 action 白名單檢查。
                if (!str_starts_with($name, 'module.')) {
                    return true;
                }

                $segments = explode('.', $name);
                $action = $segments[count($segments) - 1] ?? null;

                return in_array(count($segments), [3, 4, 5], true)
                    && in_array($action, self::ACTION_WHITELIST, true);
            })
            ->mapWithKeys(fn (array $definition, string $name) => [
                $name => Permission::updateOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    [
                        'label' => $definition['label'],
                        'group' => $definition['group'],
                    ],
                ),
            ]);

        $roles = [
            'admin' => ['label' => '管理員'],
            'owner' => ['label' => '負責人'],
            'sales' => ['label' => '業務'],
            'accounting' => ['label' => '會計'],
            'inventory' => ['label' => '庫存'],
            'viewer' => ['label' => '檢視者'],
        ];

        $resolvedRoles = collect($roles)->mapWithKeys(fn (array $definition, string $name) => [
            $name => Role::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['label' => $definition['label']]
            ),
        ]);

        $modulePermissions = $permissions->filter(
            fn (Permission $permission) => str_starts_with($permission->name, 'module.')
        );

        $roleTemplates = [
            'admin' => [
                // 技術註解：admin 以正式權限清單為主來源，避免依賴 deprecated 權限造成能力缺漏與命名漂移。
                'module.dashboard.view',
                'module.staff.view',
                'module.staff.create',
                'module.staff.update',
                'module.staff.delete',
                'module.staff.export',
                'module.permissions.view',
                'module.permissions.update',
                'module.permissions.manage',
                'module.vehicles.view',
                'module.vehicles.create',
                'module.vehicles.update',
                'module.vehicles.delete',
                'module.vehicles.export',
                'module.vehicles.pricing.view',
                'module.vehicles.pricing.update',
                'module.vehicles.costs.view',
                'module.vehicles.costs.create',
                'module.vehicles.costs.update',
                'module.vehicles.sales.view',
                'module.vehicles.sales.create',
                'module.vehicles.sales.update',
                'module.vehicles.sales.payments.view',
                'module.vehicles.sales.payments.create',
                'module.vehicles.sales.payments.void',
                'module.vehicles.sales.completion.view',
                'module.vehicles.sales.completion.confirm',
                'module.receivables.view',
                'module.receivables.create',
                'module.receivables.void',
                'module.receivables.mark-sold',
                'module.accounting.view',
                'module.accounting.accounts.view',
                'module.accounting.accounts.create',
                'module.accounting.accounts.update',
                'module.accounting.journals.view',
                'module.accounting.journals.create',
                'module.accounting.journals.update',
                'module.accounting.journals.post',
                'module.accounting.journals.void',
                'module.accounting.events.view',
                'module.accounting.events.review',
                'module.customers.view',
                'module.customers.create',
                'module.customers.update',
                'module.customers.sensitive.view',
                'module.customers.sensitive.update',
                'module.audit.view',
                'module.company-settings.view',
                'module.company-settings.update',
                // 技術註解：以下 deprecated 權限僅為相容層保留，不作為主要授權來源。
                'staff-permission.view',
                'staff-permission.update-role',
                'staff-permission.update-permission',
            ],
            'owner' => [
                'module.dashboard.view',
                'module.staff.view',
                'module.permissions.view',
                'module.vehicles.view',
                'module.vehicles.create',
                'module.vehicles.update',
                'module.vehicles.delete',
                'module.vehicles.export',
            ],
            'sales' => [
                'module.dashboard.view',
                'module.customers.view',
                'module.customers.create',
                'module.customers.update',
                'module.vehicles.view',
                'module.vehicles.create',
                'module.vehicles.update',
                'module.vehicles.sales.view',
                'module.vehicles.sales.create',
                'module.vehicles.sales.update',
                'module.vehicles.sales.completion.view',
                // 技術註解：業務角色可建立銷售並查看收款狀態，但不給收款新增/作廢/成交確認，降低現金紀錄遭誤改的風險。
                'module.receivables.view',
            ],
            'accounting' => [
                'module.dashboard.view',
                'module.accounting.view',
                'module.accounting.accounts.view',
                'module.accounting.accounts.create',
                'module.accounting.accounts.update',
                'module.accounting.journals.view',
                'module.accounting.journals.create',
                'module.accounting.journals.update',
                'module.accounting.journals.post',
                'module.accounting.journals.void',
                'module.accounting.events.view',
                'module.accounting.events.review',
                'module.customers.view',
                'module.vehicles.view',
                'module.vehicles.sales.view',
                'module.vehicles.sales.completion.view',
                'module.receivables.view',
                'module.receivables.create',
                'module.receivables.void',
                // 技術註解：目前成交確認屬收清後的收款管理節點，預設交由會計角色執行以維持金流與車況同步責任一致。
                'module.receivables.mark-sold',
            ],
            'inventory' => [
                'module.dashboard.view',
                'module.vehicles.view',
                'module.vehicles.create',
                'module.vehicles.update',
                // 技術註解：庫存需檢視成本以維護車況與整備資訊，但不預設建立/修改成本，避免非財務流程更動金額紀錄。
                'module.vehicles.costs.view',
                'module.vehicles.sales.completion.view',
            ],
            'viewer' => [
                'module.dashboard.view',
                'module.vehicles.view',
                'module.customers.view',
            ],
        ];

        foreach ($roleTemplates as $roleName => $permissionNames) {
            $resolvedRoles[$roleName]->syncPermissions(
                collect($permissionNames)
                    ->filter(fn (string $name) => $permissions->has($name))
                    ->map(fn (string $name) => $permissions[$name])
                    ->values()
            );
        }

        $adminUser = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                // 技術註解：User model 的 hashed cast 會統一處理測試密碼雜湊。
                'password' => 'password',
                'account_status' => 'active',
                // 技術註解：管理員必須綁定有效 tenant，避免建立車輛時傳入錯誤 company_id 造成跨租戶序號污染。
                'company_id' => $defaultCompany->id,
                'branch_id' => $defaultBranch->id,
            ],
        );

        $staffUser = User::updateOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Staff',
                // 技術註解：保持與 admin 相同測試密碼，降低驗證 RBAC 的操作成本。
                'password' => 'password',
                'account_status' => 'active',
                // 技術註解：員工帳號同樣需綁定 tenant，避免測試/示範資料觸發 tenant 邊界異常。
                'company_id' => $defaultCompany->id,
                'branch_id' => $defaultBranch->id,
            ],
        );

        $adminUser->syncRoles(['admin']);
        $staffUser->syncRoles(['viewer']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
