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
    Module::updateOrCreate(['key' => 'vehicles'], ['label' => '車輛管理', 'section' => 'operations', 'route_name' => 'employee-system.vehicles.index', 'base_permission' => 'module.vehicles.view', 'permission_prefix' => 'module.vehicles', 'is_enabled' => true, 'is_active' => true]);
    foreach (['module.vehicles.view', 'module.vehicles.update', 'module.vehicles.sales.view', 'module.vehicles.sales.payments.view', 'module.vehicles.sales.payments.create', 'module.vehicles.sales.payments.void', 'module.permissions.view', 'staff-permission.view'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
});

function vspTenant(int $companyId = 1, int $branchId = 10): void
{
    DB::table('companies')->updateOrInsert(['id' => $companyId], ['name' => 'Payment Co '.$companyId, 'code' => 'PC'.$companyId, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('branches')->updateOrInsert(['id' => $branchId], ['company_id' => $companyId, 'name' => 'Payment Br '.$branchId, 'code' => 'PB'.$branchId, 'created_at' => now(), 'updated_at' => now()]);
}

function vspUser(string $email, int $companyId = 1, int $branchId = 10): User
{
    vspTenant($companyId, $branchId);
    return User::create(['name' => 'VSP User', 'email' => $email, 'password' => 'password', 'account_status' => 'active', 'is_active' => true, 'company_id' => $companyId, 'branch_id' => $branchId]);
}

function vspVehicle(User $user, string $stock = 'VSP-001'): Vehicle
{
    return Vehicle::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'stock_number' => $stock, 'vin' => $stock.'VIN', 'brand' => 'Toyota', 'model' => 'Altis', 'model_year' => 2024, 'lifecycle_status' => 'reserved']);
}

function vspSale(Vehicle $vehicle, User $user, ?float $price = 100000): VehicleSale
{
    return VehicleSale::create(['company_id' => $vehicle->company_id, 'branch_id' => $vehicle->branch_id, 'vehicle_id' => $vehicle->id, 'customer_name' => '收款客戶', 'customer_phone' => '0911', 'sale_price' => $price, 'sale_status' => 'reserved', 'created_by' => $user->id, 'updated_by' => $user->id]);
}

function vspPayment(VehicleSale $sale, User $user, float $amount, string $status = 'received'): VehicleSalePayment
{
    return VehicleSalePayment::create(['company_id' => $sale->company_id, 'branch_id' => $sale->branch_id, 'vehicle_id' => $sale->vehicle_id, 'vehicle_sale_id' => $sale->id, 'payment_number' => 'PAY-T-'.uniqid(), 'payment_type' => 'deposit', 'payment_method' => 'cash', 'amount' => $amount, 'paid_at' => now()->toDateString(), 'status' => $status, 'created_by' => $user->id, 'updated_by' => $user->id]);
}

it('有/無 payments.view 時 Show/Edit payload 正確隔離', function (): void {
    $user = vspUser('vsp-view@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.update', 'module.vehicles.sales.view', 'module.vehicles.sales.payments.view']);
    $vehicle = vspVehicle($user);
    $sale = vspSale($vehicle, $user, 100000);
    vspPayment($sale, $user, 40000);

    foreach (['show', 'edit'] as $action) {
        $this->actingAs($user)->get(route('employee-system.vehicles.'.$action, $vehicle->id))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('vehicleSales.0.payment_summary.received_amount', '40000.00')
            ->where('vehicleSales.0.payment_summary.receivable_status', 'partial')
            ->where('vehicleSales.0.payments.0.payment_method_label', '現金')
            ->missing('vehicleSales.0.payments.0.company_id')
            ->missing('vehicleSales.0.payments.0.branch_id')
            ->missing('vehicleSales.0.payments.0.vehicle_id'));
    }

    $deny = vspUser('vsp-view-deny@example.com');
    $deny->givePermissionTo(['module.vehicles.view', 'module.vehicles.update', 'module.vehicles.sales.view']);
    $denyVehicle = vspVehicle($deny, 'VSP-DENY');
    $denySale = vspSale($denyVehicle, $deny);
    vspPayment($denySale, $deny, 1000);
    $this->actingAs($deny)->get(route('employee-system.vehicles.show', $denyVehicle->id))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->missing('vehicleSales.0.payment_summary')->missing('vehicleSales.0.payments'));
});

it('可新增收款、自動產號且拒絕未授權與系統欄位注入', function (): void {
    $user = vspUser('vsp-create@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.payments.create']);
    $sale = vspSale(vspVehicle($user), $user);
    $payload = ['payment_type' => 'deposit', 'payment_method' => 'cash', 'amount' => 5000, 'paid_at' => '2026-06-04'];

    $this->actingAs($user)->post(route('employee-system.vehicles.sales.payments.store', [$sale->vehicle_id, $sale->id]), $payload)->assertRedirect();
    expect(VehicleSalePayment::latest('id')->first()->payment_number)->toStartWith('PAY-');

    $deny = vspUser('vsp-create-deny@example.com');
    $deny->givePermissionTo('module.vehicles.view');
    $denySale = vspSale(vspVehicle($deny, 'VSP-CD'), $deny);
    $this->actingAs($deny)->post(route('employee-system.vehicles.sales.payments.store', [$denySale->vehicle_id, $denySale->id]), $payload)->assertForbidden();

    $this->actingAs($user)->post(route('employee-system.vehicles.sales.payments.store', [$sale->vehicle_id, $sale->id]), $payload + ['company_id' => 999, 'branch_id' => 999, 'vehicle_id' => 999, 'vehicle_sale_id' => 999, 'customer_id' => 999, 'payment_number' => 'X', 'status' => 'voided', 'created_by' => 1, 'gross_profit' => 1])->assertForbidden();
});

it('跨 tenant 建立或作廢回 404，作廢權限與狀態計算正確', function (): void {
    $user = vspUser('vsp-void@example.com', 1, 10);
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.update', 'module.vehicles.sales.view', 'module.vehicles.sales.payments.view', 'module.vehicles.sales.payments.create', 'module.vehicles.sales.payments.void']);
    $cross = vspUser('vsp-cross@example.com', 2, 20);
    $crossSale = vspSale(vspVehicle($cross, 'VSP-X'), $cross);
    $payment = vspPayment($crossSale, $cross, 10);

    $this->actingAs($user)->post(route('employee-system.vehicles.sales.payments.store', [$crossSale->vehicle_id, $crossSale->id]), ['payment_type' => 'deposit', 'payment_method' => 'cash', 'amount' => 1])->assertNotFound();
    $this->actingAs($user)->patch(route('employee-system.vehicles.sales.payments.void', [$crossSale->vehicle_id, $crossSale->id, $payment->id]), ['void_reason' => 'x'])->assertNotFound();

    $sale = vspSale(vspVehicle($user, 'VSP-V'), $user, 100);
    $ownPayment = vspPayment($sale, $user, 100);
    $this->actingAs($user)->patch(route('employee-system.vehicles.sales.payments.void', [$sale->vehicle_id, $sale->id, $ownPayment->id]), ['void_reason' => '輸入錯誤'])->assertRedirect();
    expect($ownPayment->fresh()->status)->toBe('voided');
    $this->actingAs($user)->get(route('employee-system.vehicles.show', $sale->vehicle_id))->assertInertia(fn (AssertableInertia $page) => $page->where('vehicleSales.0.payment_summary.received_amount', '0.00')->where('vehicleSales.0.payment_summary.receivable_status', 'unpaid'));
});

it('paid partial unpaid overpaid 狀態、audit 與無 sales.view 隔離正確', function (): void {
    $user = vspUser('vsp-status@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.update', 'module.vehicles.sales.view', 'module.vehicles.sales.payments.view', 'module.vehicles.sales.payments.create', 'module.vehicles.sales.payments.void']);
    foreach ([['paid', 100, 100], ['partial', 100, 50], ['unpaid', 100, 0], ['overpaid', 100, 150]] as [$status, $price, $paid]) {
        $sale = vspSale(vspVehicle($user, 'VSP-'.$status), $user, $price);
        if ($paid > 0) vspPayment($sale, $user, $paid);
        $this->actingAs($user)->get(route('employee-system.vehicles.show', $sale->vehicle_id))->assertInertia(fn (AssertableInertia $page) => $page->where('vehicleSales.0.payment_summary.receivable_status', $status));
    }

    $sale = vspSale(vspVehicle($user, 'VSP-AUD'), $user, 100);
    $this->actingAs($user)->post(route('employee-system.vehicles.sales.payments.store', [$sale->vehicle_id, $sale->id]), ['payment_type' => 'deposit', 'payment_method' => 'cash', 'amount' => 20])->assertRedirect();
    $created = ActivityLog::where('event', 'vehicle_sale_payment.created')->latest('id')->first();
    expect($created)->not->toBeNull()->and(array_key_exists('company_id', $created->new_values ?? []))->toBeFalse()->and(array_key_exists('gross_profit', $created->new_values ?? []))->toBeFalse();
    $payment = VehicleSalePayment::latest('id')->first();
    $this->actingAs($user)->patch(route('employee-system.vehicles.sales.payments.void', [$sale->vehicle_id, $sale->id, $payment->id]), ['void_reason' => 'audit'])->assertRedirect();
    expect(ActivityLog::where('event', 'vehicle_sale_payment.voided')->exists())->toBeTrue();

    $noSales = vspUser('vsp-no-sales@example.com');
    $noSales->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.payments.view']);
    $v = vspVehicle($noSales, 'VSP-NS'); $s = vspSale($v, $noSales); vspPayment($s, $noSales, 1);
    $this->actingAs($noSales)->get(route('employee-system.vehicles.show', $v->id))->assertInertia(fn (AssertableInertia $page) => $page->missing('vehicleSales')->missing('vehicleSaleSummary'));
});

it('Staff Permission matrix 可看到 vehicles.sales.payments', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::where('email', 'admin@example.com')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('employee-system.staff-permissions.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('permissionMatrix', function ($matrix): bool {
            $matrix = is_array($matrix) ? $matrix : $matrix->all();

            return isset($matrix['vehicles.sales.payments'])
                && ($matrix['vehicles.sales.payments']['label'] ?? null) === '車輛銷售收款'
                && ($matrix['vehicles.sales.payments']['actions']['view']['permission'] ?? null) === 'module.vehicles.sales.payments.view'
                && ($matrix['vehicles.sales.payments']['actions']['create']['permission'] ?? null) === 'module.vehicles.sales.payments.create'
                && ($matrix['vehicles.sales.payments']['actions']['void']['permission'] ?? null) === 'module.vehicles.sales.payments.void';
        }));
});
