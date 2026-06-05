<?php

use App\Models\AccountingAccount;
use App\Models\ActivityLog;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
});

function ensureAccountingTenantRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(
        ['id' => $companyId],
        ['name' => 'Accounting Company '.$companyId, 'code' => 'ACC'.$companyId, 'created_at' => now(), 'updated_at' => now()]
    );

    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(
            ['id' => $branchId],
            ['company_id' => $companyId, 'name' => 'Accounting Branch '.$branchId, 'code' => 'AB'.$branchId, 'created_at' => now(), 'updated_at' => now()]
        );
    }
}

function makeAccountingUser(string $email, int $companyId = 1, ?int $branchId = 10): User
{
    ensureAccountingTenantRows($companyId, $branchId);

    return User::create([
        'name' => 'Accounting User',
        'email' => $email,
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);
}

function makeAccountingAccount(User $user, array $overrides = []): AccountingAccount
{
    return AccountingAccount::create(array_merge([
        'company_id' => (int) $user->company_id,
        'branch_id' => $user->branch_id,
        'code' => '1001',
        'name' => '庫存現金',
        'type' => 'asset',
        'opening_balance' => 1000,
        'is_active' => true,
        'notes' => '初始科目',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

function validAccountingAccountPayload(array $overrides = []): array
{
    return array_merge([
        'code' => '1101',
        'name' => '銀行存款',
        'type' => 'asset',
        'opening_balance' => 2500.50,
        'is_active' => true,
        'notes' => '會計科目備註',
    ], $overrides);
}

it('有 accounts.view 可進 index', function (): void {
    $user = makeAccountingUser('accounting-index-allow@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.accounts.view');
    makeAccountingAccount($user);

    $this->actingAs($user)
        ->get(route('employee-system.accounting.accounts.index'))
        ->assertOk();
});

it('無 accounts.view 回 403', function (): void {
    $user = makeAccountingUser('accounting-index-deny@example.com');
    $user->givePermissionTo('module.accounting.view');

    $this->actingAs($user)
        ->get(route('employee-system.accounting.accounts.index'))
        ->assertForbidden();
});

it('create 頁需要 create 權限', function (): void {
    $user = makeAccountingUser('accounting-create-deny@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.accounts.view');

    $this->actingAs($user)
        ->get(route('employee-system.accounting.accounts.create'))
        ->assertForbidden();
});

it('store 成功', function (): void {
    $user = makeAccountingUser('accounting-store@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.accounts.view', 'module.accounting.accounts.create');

    $this->actingAs($user)
        ->post(route('employee-system.accounting.accounts.store'), validAccountingAccountPayload())
        ->assertRedirect(route('employee-system.accounting.accounts.index'));

    $account = AccountingAccount::query()->where('code', '1101')->firstOrFail();

    expect($account->company_id)->toBe(1)
        ->and($account->branch_id)->toBe(10)
        ->and($account->created_by)->toBe($user->id)
        ->and($account->updated_by)->toBe($user->id);
});

it('update 成功', function (): void {
    $user = makeAccountingUser('accounting-update@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.accounts.view', 'module.accounting.accounts.update');
    $account = makeAccountingAccount($user);

    $this->actingAs($user)
        ->patch(route('employee-system.accounting.accounts.update', $account->id), validAccountingAccountPayload([
            'code' => '1002',
            'name' => '活期存款',
            'type' => 'asset',
            'opening_balance' => 3000,
            'is_active' => false,
        ]))
        ->assertRedirect(route('employee-system.accounting.accounts.index'));

    expect($account->fresh()->code)->toBe('1002')
        ->and($account->fresh()->name)->toBe('活期存款')
        ->and($account->fresh()->is_active)->toBeFalse();
});

it('code 在同 company unique', function (): void {
    $user = makeAccountingUser('accounting-unique@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.accounts.view', 'module.accounting.accounts.create');
    makeAccountingAccount($user, ['code' => '2001']);

    $this->actingAs($user)
        ->from(route('employee-system.accounting.accounts.create'))
        ->post(route('employee-system.accounting.accounts.store'), validAccountingAccountPayload(['code' => '2001']))
        ->assertSessionHasErrors('code');
});

it('不同 company 可使用相同 code', function (): void {
    $userA = makeAccountingUser('accounting-company-a@example.com', 1, 10);
    $userB = makeAccountingUser('accounting-company-b@example.com', 2, 20);
    $userB->givePermissionTo('module.accounting.view', 'module.accounting.accounts.view', 'module.accounting.accounts.create');
    makeAccountingAccount($userA, ['code' => '3001']);

    $this->actingAs($userB)
        ->post(route('employee-system.accounting.accounts.store'), validAccountingAccountPayload(['code' => '3001']))
        ->assertRedirect(route('employee-system.accounting.accounts.index'));

    expect(AccountingAccount::query()->where('company_id', 2)->where('code', '3001')->exists())->toBeTrue();
});

it('跨 tenant account 不可 edit update', function (): void {
    $owner = makeAccountingUser('accounting-owner@example.com', 1, 10);
    $other = makeAccountingUser('accounting-other@example.com', 2, 20);
    $other->givePermissionTo('module.accounting.view', 'module.accounting.accounts.view', 'module.accounting.accounts.update');
    $account = makeAccountingAccount($owner);

    $this->actingAs($other)
        ->get(route('employee-system.accounting.accounts.edit', $account->id))
        ->assertNotFound();

    $this->actingAs($other)
        ->patch(route('employee-system.accounting.accounts.update', $account->id), validAccountingAccountPayload(['name' => '跨租戶修改']))
        ->assertNotFound();
});

it('前端 payload 夾帶 company_id branch_id created_by updated_by 不會覆寫', function (): void {
    $user = makeAccountingUser('accounting-override@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.accounts.view', 'module.accounting.accounts.create', 'module.accounting.accounts.update');

    $this->actingAs($user)
        ->post(route('employee-system.accounting.accounts.store'), validAccountingAccountPayload([
            'company_id' => 999,
            'branch_id' => 999,
            'created_by' => 999,
            'updated_by' => 999,
        ]));

    $account = AccountingAccount::query()->where('code', '1101')->firstOrFail();
    expect($account->company_id)->toBe(1)
        ->and($account->branch_id)->toBe(10)
        ->and($account->created_by)->toBe($user->id)
        ->and($account->updated_by)->toBe($user->id);

    $this->actingAs($user)
        ->patch(route('employee-system.accounting.accounts.update', $account->id), validAccountingAccountPayload([
            'company_id' => 555,
            'branch_id' => 555,
            'created_by' => 555,
            'updated_by' => 555,
            'name' => '後端保護測試',
        ]));

    $fresh = $account->fresh();
    expect($fresh->company_id)->toBe(1)
        ->and($fresh->branch_id)->toBe(10)
        ->and($fresh->created_by)->toBe($user->id)
        ->and($fresh->updated_by)->toBe($user->id);
});

it('response payload 不包含 company_id branch_id created_by updated_by', function (): void {
    $user = makeAccountingUser('accounting-payload@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.accounts.view');
    makeAccountingAccount($user);

    $this->actingAs($user)
        ->get(route('employee-system.accounting.accounts.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('accounts.data.0.company_id')
            ->missing('accounts.data.0.branch_id')
            ->missing('accounts.data.0.created_by')
            ->missing('accounts.data.0.updated_by')
        );
});

it('response payload 不包含 profit gross_margin margin net_profit', function (): void {
    $user = makeAccountingUser('accounting-no-profit@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.accounts.view');
    makeAccountingAccount($user);

    $this->actingAs($user)
        ->get(route('employee-system.accounting.accounts.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('accounts.data.0.profit')
            ->missing('accounts.data.0.gross_margin')
            ->missing('accounts.data.0.margin')
            ->missing('accounts.data.0.net_profit')
        );
});

it('audit event accounting_account.created updated', function (): void {
    $user = makeAccountingUser('accounting-audit@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.accounts.view', 'module.accounting.accounts.create', 'module.accounting.accounts.update');

    $this->actingAs($user)->post(route('employee-system.accounting.accounts.store'), validAccountingAccountPayload(['code' => '8001']));

    $account = AccountingAccount::query()->where('code', '8001')->firstOrFail();

    $this->actingAs($user)->patch(route('employee-system.accounting.accounts.update', $account->id), validAccountingAccountPayload([
        'code' => '8001',
        'name' => '更新後科目',
    ]));

    expect(ActivityLog::query()->where('event', 'accounting_account.created')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'accounting_account.updated')->exists())->toBeTrue();
});

it('accounting role 有 accounts 權限', function (): void {
    $role = Role::findByName('accounting', 'web');

    expect($role->hasPermissionTo('module.accounting.view'))->toBeTrue()
        ->and($role->hasPermissionTo('module.accounting.accounts.view'))->toBeTrue()
        ->and($role->hasPermissionTo('module.accounting.accounts.create'))->toBeTrue()
        ->and($role->hasPermissionTo('module.accounting.accounts.update'))->toBeTrue();
});

it('sales inventory viewer 不預設 accounting 權限', function (): void {
    foreach (['sales', 'inventory', 'viewer'] as $roleName) {
        $role = Role::findByName($roleName, 'web');
        expect($role->hasPermissionTo('module.accounting.view'))->toBeFalse();
    }
});

it('module registry 有 accounting module', function (): void {
    $module = Module::query()->where('key', 'accounting')->first();

    expect($module)->not->toBeNull()
        ->and($module->route_name)->toBe('employee-system.accounting.accounts.index')
        ->and($module->base_permission)->toBe('module.accounting.view');
});