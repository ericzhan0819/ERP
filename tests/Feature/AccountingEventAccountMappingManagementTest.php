<?php

use App\Models\AccountingAccount;
use App\Models\AccountingEvent;
use App\Models\AccountingEventAccountMapping;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalEntryLine;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Module::updateOrCreate(['key' => 'accounting-event-mappings'], ['label' => '會計事件映射', 'route_name' => 'employee-system.accounting.event-mappings.index', 'base_permission' => 'module.accounting.event-mappings.view', 'permission_prefix' => 'module.accounting.event-mappings', 'icon' => 'Receipt', 'icon_key' => 'Receipt', 'section' => 'accounting', 'sort_order' => 42, 'is_active' => true, 'is_enabled' => true, 'active_patterns' => ['employee-system.accounting.event-mappings.*']]);
    foreach (['view', 'create', 'update'] as $action) {
        Permission::findOrCreate('module.accounting.event-mappings.'.$action, 'web');
    }
    Permission::findOrCreate('staff-permission.view', 'web');
    Permission::findOrCreate('module.permissions.view', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('accounting', 'web');
    Role::findOrCreate('viewer', 'web');
    Role::findOrCreate('sales', 'web');
    Role::findOrCreate('inventory', 'web');
});

function aeMappingEnsureTenant(int $companyId, ?int $branchId = null): void
{
    DB::table('companies')->updateOrInsert(['id' => $companyId], ['name' => 'AE Mapping Company '.$companyId, 'code' => 'AEM'.$companyId, 'created_at' => now(), 'updated_at' => now()]);
    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(['id' => $branchId], ['company_id' => $companyId, 'name' => 'AE Mapping Branch '.$branchId, 'code' => 'AEMB'.$companyId.'-'.$branchId, 'created_at' => now(), 'updated_at' => now()]);
    }
}

function aeMappingUser(string $role = 'admin', int $companyId = 1, ?int $branchId = 10): User
{
    aeMappingEnsureTenant($companyId, $branchId);
    $user = User::create(['name' => 'AE Mapping '.$role.uniqid(), 'email' => 'ae-mapping-'.$role.uniqid().'@example.com', 'password' => 'password', 'account_status' => 'active', 'is_active' => true, 'company_id' => $companyId, 'branch_id' => $branchId]);
    $user->assignRole($role);
    if (in_array($role, ['admin', 'accounting'], true)) {
        $user->givePermissionTo(['module.accounting.event-mappings.view', 'module.accounting.event-mappings.create', 'module.accounting.event-mappings.update']);
    }

    return $user;
}

function aeMappingAccount(User $user, string $type, array $overrides = []): AccountingAccount
{
    return AccountingAccount::create(array_merge(['company_id' => $user->company_id, 'branch_id' => null, 'code' => 'AEM-'.strtoupper($type).'-'.uniqid(), 'name' => 'AE Mapping '.$type, 'type' => $type, 'opening_balance' => 0, 'is_active' => true, 'created_by' => $user->id, 'updated_by' => $user->id], $overrides));
}

function aeMappingCreate(User $user, string $key, AccountingAccount $account, array $overrides = []): AccountingEventAccountMapping
{
    return AccountingEventAccountMapping::create(array_merge(['company_id' => $user->company_id, 'branch_id' => null, 'event_type' => 'vehicle_sale_completed', 'source_type' => 'vehicle_sale_completion', 'mapping_key' => $key, 'account_id' => $account->id, 'is_active' => true, 'created_by' => $user->id, 'updated_by' => $user->id], $overrides));
}

function aeMappingPayload(AccountingAccount $account, array $overrides = []): array
{
    return array_merge(['branch_id' => null, 'event_type' => 'vehicle_sale_completed', 'mapping_key' => 'accounts_receivable_account', 'account_id' => $account->id, 'is_active' => true, 'notes' => 'Mapping note'], $overrides);
}

function aeMappingAssertNoAccountingMutation(): void
{
    expect(AccountingJournalEntry::count())->toBe(0)
        ->and(AccountingJournalEntryLine::count())->toBe(0)
        ->and(AccountingEvent::query()->where('status', 'converted')->exists())->toBeFalse()
        ->and(AccountingEvent::query()->whereNotNull('converted_journal_entry_id')->exists())->toBeFalse();
}

it('admin and accounting can view mapping index while viewer cannot', function (): void {
    foreach (['admin', 'accounting'] as $role) {
        $this->actingAs(aeMappingUser($role))->get(route('employee-system.accounting.event-mappings.index'))->assertOk();
    }

    $this->actingAs(aeMappingUser('viewer'))->get(route('employee-system.accounting.event-mappings.index'))->assertForbidden();
});

it('broad accounting view permission alone cannot access mapping index', function (): void {
    Permission::findOrCreate('module.accounting.view', 'web');
    $user = aeMappingUser('viewer');
    $user->givePermissionTo('module.accounting.view');

    $this->actingAs($user)->get(route('employee-system.accounting.event-mappings.index'))->assertForbidden();
});

it('admin and accounting can open create page', function (): void {
    foreach (['admin', 'accounting'] as $role) {
        $this->actingAs(aeMappingUser($role))->get(route('employee-system.accounting.event-mappings.create'))->assertOk();
    }
});

it('creates company default AR mapping and ignores frontend protected overrides', function (): void {
    $user = aeMappingUser('admin');
    $account = aeMappingAccount($user, 'asset');

    $this->actingAs($user)->post(route('employee-system.accounting.event-mappings.store'), aeMappingPayload($account, ['company_id' => 999, 'created_by' => 999, 'updated_by' => 999, 'source_type' => 'vehicle_sale_completion']))->assertRedirect(route('employee-system.accounting.event-mappings.index'));

    $mapping = AccountingEventAccountMapping::firstOrFail();
    expect((int) $mapping->company_id)->toBe((int) $user->company_id)
        ->and($mapping->branch_id)->toBeNull()
        ->and((int) $mapping->created_by)->toBe((int) $user->id)
        ->and((int) $mapping->updated_by)->toBe((int) $user->id)
        ->and($mapping->source_type)->toBe('vehicle_sale_completion');
    aeMappingAssertNoAccountingMutation();
});

it('rejects source type outside mapping metadata', function (): void {
    $user = aeMappingUser('admin');
    $account = aeMappingAccount($user, 'asset');

    $this->actingAs($user)->post(route('employee-system.accounting.event-mappings.store'), aeMappingPayload($account, ['source_type' => 'wrong_source']))->assertSessionHasErrors('source_type');
    aeMappingAssertNoAccountingMutation();
});

it('creates branch specific revenue mapping', function (): void {
    $user = aeMappingUser('accounting');
    $account = aeMappingAccount($user, 'revenue', ['branch_id' => $user->branch_id]);

    $this->actingAs($user)->post(route('employee-system.accounting.event-mappings.store'), aeMappingPayload($account, ['branch_id' => $user->branch_id, 'mapping_key' => 'sales_revenue_account']))->assertSessionHasNoErrors();

    expect(AccountingEventAccountMapping::firstOrFail()->branch_id)->toBe($user->branch_id);
    aeMappingAssertNoAccountingMutation();
});

it('rejects invalid account and duplicate active mapping cases', function (callable $accountFactory, string $key): void {
    $user = aeMappingUser('admin');
    $account = $accountFactory($user);

    $this->actingAs($user)->post(route('employee-system.accounting.event-mappings.store'), aeMappingPayload($account, ['mapping_key' => $key]))->assertSessionHasErrors('account_id');
    aeMappingAssertNoAccountingMutation();
})->with([
    'cross company account' => [function (User $user): AccountingAccount { aeMappingEnsureTenant(2, 20); return aeMappingAccount($user, 'asset', ['company_id' => 2, 'branch_id' => 20]); }, 'accounts_receivable_account'],
    'inactive account' => [fn (User $user): AccountingAccount => aeMappingAccount($user, 'asset', ['is_active' => false]), 'accounts_receivable_account'],
    'wrong account type' => [fn (User $user): AccountingAccount => aeMappingAccount($user, 'expense'), 'accounts_receivable_account'],
]);

it('rejects duplicate active mapping but inactive old mapping allows active replacement', function (): void {
    $user = aeMappingUser('admin');
    $first = aeMappingAccount($user, 'asset');
    $second = aeMappingAccount($user, 'asset');
    aeMappingCreate($user, 'accounts_receivable_account', $first);

    $this->actingAs($user)->post(route('employee-system.accounting.event-mappings.store'), aeMappingPayload($second))->assertSessionHasErrors('mapping_key');

    AccountingEventAccountMapping::firstOrFail()->update(['is_active' => false]);
    $this->actingAs($user)->post(route('employee-system.accounting.event-mappings.store'), aeMappingPayload($second))->assertSessionHasNoErrors();

    expect(AccountingEventAccountMapping::where('is_active', true)->count())->toBe(1);
});

it('edit update is tenant scoped and update can change account active state and notes', function (): void {
    $user = aeMappingUser('admin', 1, 10);
    $other = aeMappingUser('admin', 2, 20);
    $mapping = aeMappingCreate($user, 'accounts_receivable_account', aeMappingAccount($user, 'asset'));
    $otherMapping = aeMappingCreate($other, 'accounts_receivable_account', aeMappingAccount($other, 'asset'));
    $newAccount = aeMappingAccount($user, 'asset');

    $this->actingAs($user)->get(route('employee-system.accounting.event-mappings.edit', $otherMapping->id))->assertNotFound();

    $this->actingAs($user)->patch(route('employee-system.accounting.event-mappings.update', $mapping->id), aeMappingPayload($newAccount, ['is_active' => false, 'notes' => 'Updated note']))->assertRedirect(route('employee-system.accounting.event-mappings.index'));

    $mapping->refresh();
    expect((int) $mapping->account_id)->toBe((int) $newAccount->id)->and($mapping->is_active)->toBeFalse()->and($mapping->notes)->toBe('Updated note');
    aeMappingAssertNoAccountingMutation();
});

it('update cannot change to wrong account type', function (): void {
    $user = aeMappingUser('admin');
    $mapping = aeMappingCreate($user, 'accounts_receivable_account', aeMappingAccount($user, 'asset'));
    $wrong = aeMappingAccount($user, 'revenue');

    $this->actingAs($user)->patch(route('employee-system.accounting.event-mappings.update', $mapping->id), aeMappingPayload($wrong))->assertSessionHasErrors('account_id');
});

it('viewer sales and inventory cannot create or update mappings', function (string $role): void {
    $admin = aeMappingUser('admin');
    $mapping = aeMappingCreate($admin, 'accounts_receivable_account', aeMappingAccount($admin, 'asset'));
    $user = aeMappingUser($role, (int) $admin->company_id, $admin->branch_id === null ? null : (int) $admin->branch_id);
    $account = aeMappingAccount($admin, 'asset');

    $this->actingAs($user)->post(route('employee-system.accounting.event-mappings.store'), aeMappingPayload($account))->assertForbidden();
    $this->actingAs($user)->patch(route('employee-system.accounting.event-mappings.update', $mapping->id), aeMappingPayload($account))->assertForbidden();
    aeMappingAssertNoAccountingMutation();
})->with(['viewer', 'sales', 'inventory']);

it('index payload does not expose raw tenant or actor ids', function (): void {
    $user = aeMappingUser('admin');
    aeMappingCreate($user, 'accounts_receivable_account', aeMappingAccount($user, 'asset'));

    $this->actingAs($user)->get(route('employee-system.accounting.event-mappings.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->missing('mappings.data.0.company_id')
        ->missing('mappings.data.0.branch_id')
        ->missing('mappings.data.0.created_by')
        ->missing('mappings.data.0.updated_by')
    );
});

it('seeder registers mapping module permissions and role defaults', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $module = Module::where('key', 'accounting-event-mappings')->firstOrFail();
    expect($module->label)->toBe('會計事件映射')->and($module->route_name)->toBe('employee-system.accounting.event-mappings.index')->and($module->sort_order)->toBe(42);

    foreach (['admin', 'accounting'] as $roleName) {
        $role = Role::findByName($roleName, 'web');
        expect($role->hasPermissionTo('module.accounting.event-mappings.view'))->toBeTrue()->and($role->hasPermissionTo('module.accounting.event-mappings.create'))->toBeTrue()->and($role->hasPermissionTo('module.accounting.event-mappings.update'))->toBeTrue();
    }

    foreach (['viewer', 'sales', 'inventory'] as $roleName) {
        $role = Role::findByName($roleName, 'web');
        expect($role->hasPermissionTo('module.accounting.event-mappings.view'))->toBeFalse()->and($role->hasPermissionTo('module.accounting.event-mappings.create'))->toBeFalse()->and($role->hasPermissionTo('module.accounting.event-mappings.update'))->toBeFalse();
    }
});

it('staff permission matrix displays accounting event mappings actions', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::where('email', 'admin@example.com')->firstOrFail();

    $this->actingAs($admin)->get(route('employee-system.staff-permissions.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('permissionMatrix', function ($matrix): bool {
            $matrix = is_array($matrix) ? $matrix : $matrix->all();

            return isset($matrix['accounting.event-mappings'])
                && ($matrix['accounting.event-mappings']['label'] ?? null) === '會計事件映射'
                && ($matrix['accounting.event-mappings']['actions']['view']['permission'] ?? null) === 'module.accounting.event-mappings.view'
                && ($matrix['accounting.event-mappings']['actions']['create']['permission'] ?? null) === 'module.accounting.event-mappings.create'
                && ($matrix['accounting.event-mappings']['actions']['update']['permission'] ?? null) === 'module.accounting.event-mappings.update';
        })
    );
});
