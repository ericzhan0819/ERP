<?php

use App\Models\ActivityLog;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Module::updateOrCreate(['key' => 'audit'], [
        'label' => '稽核紀錄',
        'section' => 'administration',
        'route_name' => 'employee-system.audit.activity-logs',
        'base_permission' => 'module.audit.view',
        'permission_prefix' => 'module.audit',
        'icon_key' => 'ShieldCheck',
        'sort_order' => 40,
        'is_enabled' => true,
        'is_active' => true,
        'active_patterns' => ['employee-system.audit.*'],
    ]);

    Permission::findOrCreate('module.audit.view', 'web');
    Permission::findOrCreate('module.dashboard.view', 'web');
});

function makeAuditDisplayUser(): User
{
    DB::table('companies')->updateOrInsert(['id' => 1], [
        'name' => 'Audit Display Company',
        'code' => 'ADC',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('branches')->updateOrInsert(['id' => 10], [
        'company_id' => 1,
        'name' => 'Audit Display Branch',
        'code' => 'ADB',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = User::create([
        'name' => 'Audit Display User',
        'email' => 'audit-display@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => 1,
        'branch_id' => 10,
    ]);

    $user->givePermissionTo('module.audit.view');

    return $user;
}

function createAuditDisplayLog(string $event, ?array $metadata = null, ?string $description = null): ActivityLog
{
    return ActivityLog::create([
        'company_id' => 1,
        'branch_id' => 10,
        'event' => $event,
        'action' => $event,
        'description' => $description,
        'metadata' => $metadata,
        'old_values' => ['safe_field' => 'old'],
        'new_values' => ['safe_field' => 'new'],
        'ip_address' => '127.0.0.1',
    ]);
}

function findAuditDisplayRow(array $props, string $event): array
{
    $rows = collect($props['logs']['data'] ?? []);
    $row = $rows->first(fn (array $candidate): bool => ($candidate['display']['event_key'] ?? null) === $event);

    expect($row)->not->toBeNull();

    return $row;
}

it('activity audit display localizes event and module labels with legacy module fallback', function (): void {
    $user = makeAuditDisplayUser();

    createAuditDisplayLog('vehicle_sale.created', [], 'Vehicle sale created');
    createAuditDisplayLog('vehicle_sale.updated', [], 'Vehicle sale updated');
    createAuditDisplayLog('vehicle_cost.created', [], 'Vehicle cost created');
    createAuditDisplayLog('customer.updated', ['module' => 'customers'], '更新客戶資料');
    createAuditDisplayLog('vehicle_sale_payment.created', ['module' => 'receivables'], 'Vehicle sale payment created');

    $this->actingAs($user)
        ->get(route('employee-system.audit.activity-logs'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $props = $page->toArray()['props'];

            expect(findAuditDisplayRow($props, 'vehicle_sale.created')['display'])
                ->toMatchArray(['module_label' => '車輛銷售', 'event_label' => '新增車輛銷售', 'description_label' => '新增車輛銷售'])
                ->and(findAuditDisplayRow($props, 'vehicle_sale.updated')['display'])
                ->toMatchArray(['module_label' => '車輛銷售', 'event_label' => '更新車輛銷售', 'description_label' => '更新車輛銷售'])
                ->and(findAuditDisplayRow($props, 'vehicle_cost.created')['display'])
                ->toMatchArray(['module_label' => '車輛成本', 'event_label' => '新增車輛成本', 'description_label' => '新增車輛成本'])
                ->and(findAuditDisplayRow($props, 'customer.updated')['display'])
                ->toMatchArray(['module_label' => '客戶管理', 'event_label' => '更新客戶資料'])
                ->and(findAuditDisplayRow($props, 'vehicle_sale_payment.created')['display'])
                ->toMatchArray(['module_label' => '收款管理', 'event_label' => '新增銷售收款', 'description_label' => '新增銷售收款']);
        });
});

it('payment and receivable events prefer event semantic module over legacy raw module key', function (): void {
    $user = makeAuditDisplayUser();

    createAuditDisplayLog('vehicle_sale_payment.created', ['module' => 'vehicles'], 'Vehicle sale payment created');
    createAuditDisplayLog('vehicle_sale.marked_sold_from_receivable', null, 'Vehicle sale marked sold from receivable');

    $this->actingAs($user)
        ->get(route('employee-system.audit.activity-logs'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $props = $page->toArray()['props'];

            expect(findAuditDisplayRow($props, 'vehicle_sale_payment.created')['display'])
                ->toMatchArray(['module_label' => '收款管理', 'event_label' => '新增銷售收款'])
                ->and(findAuditDisplayRow($props, 'vehicle_sale.marked_sold_from_receivable')['display'])
                ->toMatchArray(['module_label' => '收款管理', 'event_label' => '收款標記成交']);
        });
});

it('transaction completion and accounting journal audit labels are localized without changing raw event keys', function (): void {
    $user = makeAuditDisplayUser();

    createAuditDisplayLog('vehicle_sale.transaction_completed', ['module' => 'vehicle_sales'], 'Vehicle sale transaction completed');
    createAuditDisplayLog('accounting_event.converted', ['module' => 'accounting_events'], 'Accounting event converted');
    createAuditDisplayLog('accounting_journal.posted', ['module' => 'accounting_journals'], '會計傳票已過帳');
    createAuditDisplayLog('accounting_journal.voided', ['module' => 'accounting_journals'], '會計傳票已作廢');
    createAuditDisplayLog('vehicle_sale.marked_sold_from_receivable', null, 'Vehicle sale marked sold from receivable');
    createAuditDisplayLog('vehicle_sale_payment.created', ['module' => 'receivables'], 'Vehicle sale payment created');

    $this->actingAs($user)
        ->get(route('employee-system.audit.activity-logs'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $props = $page->toArray()['props'];

            expect(findAuditDisplayRow($props, 'vehicle_sale.transaction_completed')['display'])
                ->toMatchArray([
                    'event_key' => 'vehicle_sale.transaction_completed',
                    'module_label' => '車輛銷售',
                    'event_label' => '完成交易',
                    'description_label' => '完成交易',
                ])
                ->and(findAuditDisplayRow($props, 'accounting_event.converted')['display'])
                ->toMatchArray([
                    'module_label' => '會計事件',
                    'event_label' => '會計事件轉傳票',
                    'description_label' => '會計事件已轉傳票',
                ])
                ->and(findAuditDisplayRow($props, 'accounting_journal.posted')['display'])
                ->toMatchArray([
                    'module_label' => '會計傳票',
                    'event_label' => '過帳會計傳票',
                    'description_label' => '會計傳票已過帳',
                ])
                ->and(findAuditDisplayRow($props, 'accounting_journal.voided')['display'])
                ->toMatchArray([
                    'module_label' => '會計傳票',
                    'event_label' => '作廢會計傳票',
                    'description_label' => '會計傳票已作廢',
                ])
                ->and(findAuditDisplayRow($props, 'vehicle_sale.marked_sold_from_receivable')['display'])
                ->toMatchArray(['event_label' => '收款標記成交'])
                ->and(findAuditDisplayRow($props, 'vehicle_sale_payment.created')['display'])
                ->toMatchArray(['event_label' => '新增銷售收款']);
        });
});

it('unknown events fallback safely and activity audit does not include login logs or sensitive display fields', function (): void {
    $user = makeAuditDisplayUser();

    createAuditDisplayLog('unknown.event', null, null);
    createAuditDisplayLog('auth.login.success', ['module' => 'audit'], 'login should stay out');

    $this->actingAs($user)
        ->get(route('employee-system.audit.activity-logs'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $rows = collect($page->toArray()['props']['logs']['data'] ?? []);
            $unknown = $rows->first(fn (array $row): bool => ($row['display']['event_key'] ?? null) === 'unknown.event');

            expect($unknown)->not->toBeNull()
                ->and($unknown['display'])->toMatchArray([
                    'event_key' => 'unknown.event',
                    'event_label' => 'unknown.event',
                    'module_label' => '-',
                    'description_label' => 'unknown.event',
                ])
                // 技術註解：display payload 只提供顯示 label，不新增 company/branch 或敏感財務欄位。
                ->and(array_keys($unknown['display']))->not->toContain('company_id', 'branch_id', 'profit', 'gross_margin')
                ->and($rows->pluck('display.event_key')->all())->not->toContain('auth.login.success');
        });
});
