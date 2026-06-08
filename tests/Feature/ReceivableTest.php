<?php

use App\Models\ActivityLog;
use App\Models\Module;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSale;
use App\Models\VehicleSalePayment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Module::updateOrCreate(['key' => 'receivables'], ['label' => '收款管理', 'section' => 'operations', 'route_name' => 'employee-system.receivables.index', 'base_permission' => 'module.receivables.view', 'permission_prefix' => 'module.receivables', 'is_enabled' => true, 'is_active' => true]);
    foreach (['module.receivables.view', 'module.receivables.create', 'module.receivables.void', 'module.receivables.mark-sold', 'module.vehicles.sales.completion.view', 'module.vehicles.sales.completion.confirm', 'staff-permission.view', 'module.permissions.view'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
});

function rcvTenant(int $companyId = 1, int $branchId = 10): void
{
    DB::table('companies')->updateOrInsert(['id' => $companyId], ['name' => 'Rcv Co '.$companyId, 'code' => 'RC'.$companyId, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('branches')->updateOrInsert(['id' => $branchId], ['company_id' => $companyId, 'name' => 'Rcv Br '.$branchId, 'code' => 'RB'.$branchId, 'created_at' => now(), 'updated_at' => now()]);
}
function rcvUser(string $email, int $companyId = 1, int $branchId = 10): User { rcvTenant($companyId, $branchId); return User::create(['name' => 'Rcv User', 'email' => $email, 'password' => 'password', 'account_status' => 'active', 'is_active' => true, 'company_id' => $companyId, 'branch_id' => $branchId]); }
function rcvVehicle(User $user, ?string $stock = null): Vehicle { $stock ??= 'RCV-'.uniqid(); return Vehicle::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'stock_number' => $stock, 'vin' => $stock.'VIN', 'brand' => 'Lexus', 'model' => 'ES', 'model_year' => 2024, 'license_plate' => 'RCV-'.$stock, 'lifecycle_status' => 'reserved']); }
function rcvSale(Vehicle $vehicle, User $user, ?float $price = 100000, string $status = 'reserved', string $customer = '收款客戶', mixed $soldAt = null): VehicleSale { return VehicleSale::create(['company_id' => $vehicle->company_id, 'branch_id' => $vehicle->branch_id, 'vehicle_id' => $vehicle->id, 'customer_name' => $customer, 'customer_phone' => '0912', 'sale_price' => $price, 'sale_status' => $status, 'sold_at' => $soldAt, 'salesperson_name' => '業務A', 'created_by' => $user->id, 'updated_by' => $user->id]); }
function rcvPayment(VehicleSale $sale, User $user, float $amount, string $status = 'received'): VehicleSalePayment { return VehicleSalePayment::create(['company_id' => $sale->company_id, 'branch_id' => $sale->branch_id, 'vehicle_id' => $sale->vehicle_id, 'vehicle_sale_id' => $sale->id, 'payment_number' => 'PAY-R-'.uniqid(), 'payment_type' => 'deposit', 'payment_method' => 'cash', 'amount' => $amount, 'paid_at' => now()->toDateString(), 'status' => $status, 'created_by' => $user->id, 'updated_by' => $user->id]); }

it('Receivables index 權限、tenant scope、搜尋與 filter 正確', function (): void {
    $user = rcvUser('rcv-view@example.com'); $user->givePermissionTo('module.receivables.view');
    $own = rcvSale(rcvVehicle($user, 'RCV-FIND'), $user, 100, 'reserved', '王小明'); rcvPayment($own, $user, 50);
    $cross = rcvUser('rcv-cross@example.com', 2, 20); rcvSale(rcvVehicle($cross, 'RCV-CROSS'), $cross);
    $this->actingAs($user)->get(route('employee-system.receivables.index', ['q' => '王小明', 'receivable_status' => 'partial']))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('sales.data.0.id', $own->id)->where('sales.data.0.payment_summary.receivable_status', 'partial')->missing('sales.data.1'));
    $deny = rcvUser('rcv-deny@example.com');
    $this->actingAs($deny)->get(route('employee-system.receivables.index'))->assertForbidden();
});

it('Receivables show payload isolation 與 summary 正確', function (): void {
    $user = rcvUser('rcv-show@example.com'); $user->givePermissionTo('module.receivables.view');
    $sale = rcvSale(rcvVehicle($user), $user, 100); rcvPayment($sale, $user, 40);
    $this->actingAs($user)->get(route('employee-system.receivables.show', $sale->id))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('sale.payment_summary.received_amount', '40.00')->where('sale.payment_summary.received_payment_count', 1)->where('sale.payment_summary.payment_record_count', 1)->has('sale.canMarkSold')->has('sale.markSoldHelpText')->missing('sale.company_id')->missing('sale.branch_id')->missing('sale.profit')->missing('sale.gross_profit')->missing('sale.payments.0.company_id')->missing('sale.payments.0.branch_id'));
});

it('Receivables show payload 回傳已完成交易 completion object', function (): void {
    $user = rcvUser('rcv-completion-completed@example.com');
    $completer = rcvUser('rcv-completion-completer@example.com');
    $user->givePermissionTo(['module.receivables.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = rcvVehicle($user); $vehicle->update(['lifecycle_status' => 'sold']);
    $completedAt = now()->subHour()->setMicrosecond(0);
    $sale = rcvSale($vehicle, $user, 100, 'sold', '完成客戶', now());
    $sale->update(['completed_at' => $completedAt, 'completed_by' => $completer->id, 'completion_note' => '完成備註']);
    rcvPayment($sale, $user, 100);

    $this->actingAs($user)->get(route('employee-system.receivables.show', $sale->id))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sale.completion.status', 'completed')
        ->where('sale.completion.status_label', '已完成交易')
        ->where('sale.completion.completed_at', $completedAt->format('Y-m-d H:i:s'))
        ->where('sale.completion.completed_by_name', $completer->name)
        ->where('sale.completion.note', '完成備註')
        ->where('sale.completion.can_complete', false)
        ->where('sale.completion.complete_route', null)
    );
});

it('Receivables show payload 回傳可完成交易 completion object', function (): void {
    $user = rcvUser('rcv-completion-ready@example.com');
    $user->givePermissionTo(['module.receivables.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = rcvVehicle($user); $vehicle->update(['lifecycle_status' => 'sold']);
    $sale = rcvSale($vehicle, $user, 100, 'sold', '可完成客戶', now());
    rcvPayment($sale, $user, 120);

    $this->actingAs($user)->get(route('employee-system.receivables.show', $sale->id))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sale.completion.status', 'ready_to_complete')
        ->where('sale.completion.status_label', '可完成交易')
        ->where('sale.completion.can_complete', true)
        ->where('sale.completion.block_reason', null)
        ->where('sale.completion.complete_route', route('employee-system.vehicles.sales.complete', [$vehicle->id, $sale->id]))
    );
});

it('Receivables show payload 依收款狀態阻擋 completion', function (): void {
    $user = rcvUser('rcv-completion-payment-block@example.com');
    $user->givePermissionTo(['module.receivables.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = rcvVehicle($user); $vehicle->update(['lifecycle_status' => 'sold']);
    $sale = rcvSale($vehicle, $user, 100, 'sold', '未收清客戶', now());
    rcvPayment($sale, $user, 40);

    $this->actingAs($user)->get(route('employee-system.receivables.show', $sale->id))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sale.completion.status', 'blocked')
        ->where('sale.completion.can_complete', false)
        ->where('sale.completion.block_reason', '收款尚未完成，無法完成交易。')
        ->where('sale.completion.complete_route', null)
    );
});

it('Receivables show payload 依 completion confirm 權限阻擋 completion', function (): void {
    $user = rcvUser('rcv-completion-permission-block@example.com');
    $user->givePermissionTo(['module.receivables.view', 'module.vehicles.sales.completion.view']);
    $vehicle = rcvVehicle($user); $vehicle->update(['lifecycle_status' => 'sold']);
    $sale = rcvSale($vehicle, $user, 100, 'sold', '無權限客戶', now());
    rcvPayment($sale, $user, 100);

    $this->actingAs($user)->get(route('employee-system.receivables.show', $sale->id))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sale.completion.status', 'blocked')
        ->where('sale.completion.can_complete', false)
        ->where('sale.completion.block_reason', '沒有完成交易權限。')
        ->where('sale.completion.complete_route', null)
    );
});

it('Receivables index payload 只回傳輕量 completion summary', function (): void {
    $user = rcvUser('rcv-completion-index@example.com');
    $completer = rcvUser('rcv-completion-index-completer@example.com');
    $user->givePermissionTo('module.receivables.view');
    $vehicle = rcvVehicle($user, 'RCV-COMP-INDEX'); $vehicle->update(['lifecycle_status' => 'sold']);
    $completedAt = now()->setMicrosecond(0);
    $sale = rcvSale($vehicle, $user, 100, 'sold', '列表完成客戶', now());
    $sale->update(['completed_at' => $completedAt, 'completed_by' => $completer->id, 'completion_note' => '列表不回 note']);

    $this->actingAs($user)->get(route('employee-system.receivables.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sales.data.0.completion.status', 'completed')
        ->where('sales.data.0.completion.status_label', '已完成交易')
        ->where('sales.data.0.completion.completed_at', $completedAt->format('Y-m-d H:i:s'))
        ->where('sales.data.0.completion.completed_by_name', $completer->name)
        ->missing('sales.data.0.completion.can_complete')
        ->missing('sales.data.0.completion.block_reason')
        ->missing('sales.data.0.completion.complete_route')
    );
});

it('Receivables completion payload 不暴露禁止欄位', function (): void {
    $user = rcvUser('rcv-completion-forbidden-fields@example.com');
    $user->givePermissionTo(['module.receivables.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = rcvVehicle($user); $vehicle->update(['lifecycle_status' => 'sold']);
    $sale = rcvSale($vehicle, $user, 100, 'sold', '安全欄位客戶', now());
    rcvPayment($sale, $user, 100);

    $this->actingAs($user)->get(route('employee-system.receivables.show', $sale->id))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->missing('sale.completion.company_id')
        ->missing('sale.completion.branch_id')
        ->missing('sale.completion.completed_by')
        ->missing('sale.completion.completed_by_email')
        ->missing('sale.completion.accounting_event_id')
        ->missing('sale.completion.journal_entry_id')
        ->missing('sale.completion.gross_profit')
        ->missing('sale.completion.gross_margin')
        ->missing('sale.completion.profit')
        ->missing('sale.completion.revenue_amount')
        ->missing('sale.completion.cogs_amount')
    );
});

it('Receivables mark-sold 可在收清後成交並寫入安全 audit payload', function (): void {
    $user = rcvUser('rcv-mark@example.com'); $user->givePermissionTo(['module.receivables.view', 'module.receivables.mark-sold']);
    $sale = rcvSale(rcvVehicle($user), $user, 100, 'reserved', '成交客戶', null); rcvPayment($sale, $user, 100);
    $this->actingAs($user)->patch(route('employee-system.receivables.mark-sold', $sale->id))->assertRedirect(route('employee-system.receivables.show', $sale->id));
    $sale->refresh(); $vehicle = $sale->vehicle()->firstOrFail();
    expect($sale->sale_status)->toBe('sold')->and($vehicle->lifecycle_status)->toBe('sold')->and($sale->sold_at?->toDateString())->toBe(today()->toDateString())->and($sale->updated_by)->toBe($user->id)->and($vehicle->updated_by)->toBe($user->id);
    $audit = ActivityLog::where('event', 'vehicle_sale.marked_sold_from_receivable')->latest('id')->first();
    expect($audit)->not->toBeNull()->and($audit->metadata['module'] ?? null)->toBe('receivables')->and($audit->old_values)->toMatchArray(['sale_status' => 'reserved', 'vehicle_lifecycle_status' => 'reserved'])->and($audit->new_values)->toMatchArray(['sale_status' => 'sold', 'vehicle_lifecycle_status' => 'sold', 'receivable_status' => 'paid', 'received_amount' => '100.00', 'receivable_amount' => '100.00']);
    foreach (['company_id', 'branch_id', 'profit', 'gross_profit', 'gross_margin', 'updated_by'] as $forbidden) {
        expect(array_key_exists($forbidden, $audit->old_values ?? []))->toBeFalse()->and(array_key_exists($forbidden, $audit->new_values ?? []))->toBeFalse();
    }
});

it('Receivables mark-sold 不覆蓋既有 sold_at', function (): void {
    $user = rcvUser('rcv-mark-date@example.com'); $user->givePermissionTo(['module.receivables.view', 'module.receivables.mark-sold']);
    $originalDate = now()->subDays(5)->toDateString();
    $sale = rcvSale(rcvVehicle($user), $user, 100, 'reserved', '既有成交日', $originalDate); rcvPayment($sale, $user, 120);
    $this->actingAs($user)->patch(route('employee-system.receivables.mark-sold', $sale->id))->assertRedirect();
    expect($sale->refresh()->sold_at?->toDateString())->toBe($originalDate);
});

it('Receivables mark-sold 權限、tenant 與狀態條件防護正確', function (): void {
    $user = rcvUser('rcv-mark-guards@example.com'); $user->givePermissionTo(['module.receivables.view', 'module.receivables.mark-sold']);
    $deny = rcvUser('rcv-mark-deny@example.com'); $deny->givePermissionTo('module.receivables.view');
    $denySale = rcvSale(rcvVehicle($deny), $deny, 100, 'reserved'); rcvPayment($denySale, $deny, 100);
    $this->actingAs($deny)->patch(route('employee-system.receivables.mark-sold', $denySale->id))->assertForbidden();

    $cross = rcvUser('rcv-mark-cross@example.com', 2, 20); $crossSale = rcvSale(rcvVehicle($cross), $cross, 100, 'reserved'); rcvPayment($crossSale, $cross, 100);
    $this->actingAs($user)->patch(route('employee-system.receivables.mark-sold', $crossSale->id))->assertNotFound();

    foreach ([[50, 'reserved', 'reserved', 100, '收款尚未完成，無法標記成交。'], [0, 'reserved', 'reserved', 100, '收款尚未完成，無法標記成交。'], [100, 'sold', 'reserved', 100, '只有保留中的銷售可標記成交。'], [100, 'reserved', 'sold', 100, '只有保留中的車輛可標記成交。'], [100, 'cancelled', 'reserved', 100, '已取消銷售不可標記成交。'], [100, 'reserved', 'archived', 100, '已封存車輛不可標記成交。'], [100, 'reserved', 'reserved', null, '銷售價格未設定，無法標記成交。'], [100, 'reserved', 'reserved', 0, '銷售價格未設定，無法標記成交。']] as [$paid, $saleStatus, $vehicleStatus, $price, $message]) {
        $vehicle = rcvVehicle($user, 'GUARD-'.uniqid()); $vehicle->update(['lifecycle_status' => $vehicleStatus]);
        $sale = rcvSale($vehicle, $user, $price, $saleStatus); if ($paid > 0) rcvPayment($sale, $user, $paid);
        $this->actingAs($user)->patch(route('employee-system.receivables.mark-sold', $sale->id))->assertStatus(422)->assertJson(['message' => $message]);
    }
});

it('Receivables create 權限與不可收款狀態防護正確', function (): void {
    $user = rcvUser('rcv-create@example.com'); $user->givePermissionTo(['module.receivables.view', 'module.receivables.create']);
    $sale = rcvSale(rcvVehicle($user), $user); $payload = ['payment_type' => 'deposit', 'payment_method' => 'cash', 'amount' => 1000];
    $this->actingAs($user)->post(route('employee-system.receivables.payments.store', $sale->id), $payload)->assertRedirect();
    expect(VehicleSalePayment::where('vehicle_sale_id', $sale->id)->where('status', 'received')->exists())->toBeTrue();
    $deny = rcvUser('rcv-create-deny@example.com'); $deny->givePermissionTo('module.receivables.view'); $denySale = rcvSale(rcvVehicle($deny), $deny);
    $this->actingAs($deny)->post(route('employee-system.receivables.payments.store', $denySale->id), $payload)->assertForbidden();
    foreach ([['cancelled', 100, '已取消銷售不可新增收款紀錄。'], ['reserved', null, '銷售價格未設定，無法新增收款。'], ['reserved', 0, '銷售價格未設定，無法新增收款。']] as [$status, $price, $message]) {
        $badSale = rcvSale(rcvVehicle($user, 'BAD-'.$status.($price ?? 'null')), $user, $price, $status);
        $this->actingAs($user)->post(route('employee-system.receivables.payments.store', $badSale->id), $payload)->assertStatus(422)->assertJson(['message' => $message]);
    }
});

it('Receivables void 權限、voided 不計入與 overpaid/audit 正確', function (): void {
    $user = rcvUser('rcv-void@example.com'); $user->givePermissionTo(['module.receivables.view', 'module.receivables.create', 'module.receivables.void']);
    $sale = rcvSale(rcvVehicle($user), $user, 100); $payment = rcvPayment($sale, $user, 150);
    $this->actingAs($user)->get(route('employee-system.receivables.show', $sale->id))->assertInertia(fn (AssertableInertia $page) => $page->where('sale.payment_summary.receivable_status', 'overpaid'));
    $this->actingAs($user)->patch(route('employee-system.receivables.payments.void', [$sale->id, $payment->id]), ['void_reason' => '輸入錯誤'])->assertRedirect();
    $this->actingAs($user)->get(route('employee-system.receivables.show', $sale->id))->assertInertia(fn (AssertableInertia $page) => $page->where('sale.payment_summary.received_amount', '0.00')->where('sale.payment_summary.payment_record_count', 1));
    $this->actingAs($user)->post(route('employee-system.receivables.payments.store', $sale->id), ['payment_type' => 'deposit', 'payment_method' => 'cash', 'amount' => 1])->assertRedirect();
    $created = ActivityLog::where('event', 'vehicle_sale_payment.created')->latest('id')->first();
    expect($created)->not->toBeNull()->and(array_key_exists('company_id', $created->new_values ?? []))->toBeFalse()->and(array_key_exists('gross_profit', $created->new_values ?? []))->toBeFalse();
    $newPayment = VehicleSalePayment::latest('id')->first();
    $this->actingAs($user)->patch(route('employee-system.receivables.payments.void', [$sale->id, $newPayment->id]), ['void_reason' => 'audit'])->assertRedirect();
    expect(ActivityLog::where('event', 'vehicle_sale_payment.voided')->exists())->toBeTrue();
    $deny = rcvUser('rcv-void-deny@example.com'); $deny->givePermissionTo('module.receivables.view'); $denySale = rcvSale(rcvVehicle($deny), $deny); $denyPayment = rcvPayment($denySale, $deny, 1);
    $this->actingAs($deny)->patch(route('employee-system.receivables.payments.void', [$denySale->id, $denyPayment->id]), ['void_reason' => 'x'])->assertForbidden();
});

it('Staff Permission matrix 與 admin seeder 含 receivables 權限', function (): void {
    $this->seed(RolePermissionSeeder::class); $admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->actingAs($admin)->get(route('employee-system.staff-permissions.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('actionLabels.mark-sold', '標記成交')->where('permissionMatrix', function ($matrix): bool { $matrix = is_array($matrix) ? $matrix : $matrix->all(); return isset($matrix['receivables']) && ($matrix['receivables']['label'] ?? null) === '收款管理' && ($matrix['receivables']['actions']['view']['permission'] ?? null) === 'module.receivables.view' && ($matrix['receivables']['actions']['create']['permission'] ?? null) === 'module.receivables.create' && ($matrix['receivables']['actions']['void']['permission'] ?? null) === 'module.receivables.void' && ($matrix['receivables']['actions']['mark-sold']['permission'] ?? null) === 'module.receivables.mark-sold'; }));
    expect($admin->hasPermissionTo('module.receivables.view'))->toBeTrue()->and($admin->hasPermissionTo('module.receivables.create'))->toBeTrue()->and($admin->hasPermissionTo('module.receivables.void'))->toBeTrue()->and($admin->hasPermissionTo('module.receivables.mark-sold'))->toBeTrue();
});
