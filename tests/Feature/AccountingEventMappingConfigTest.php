<?php

use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalEntryLine;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

function accountingEventVehicleSaleCompletedMapping(): array
{
    return config('accounting_event_mappings.event_types.vehicle_sale_completed', []);
}

function accountingEventMappingConfigKeys(array $items): array
{
    $keys = [];

    foreach ($items as $key => $value) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        if (is_array($value)) {
            $keys = array_merge($keys, accountingEventMappingConfigKeys($value));
        }
    }

    return $keys;
}

it('accounting event mappings config contains vehicle sale completed mapping', function (): void {
    $mapping = accountingEventVehicleSaleCompletedMapping();

    expect($mapping)->not->toBeEmpty()
        ->and($mapping['label'] ?? null)->toBe('車輛交易完成')
        ->and($mapping['source_type'] ?? null)->toBe('vehicle_sale_completion')
        ->and($mapping['required_status'] ?? null)->toBe('reviewed')
        ->and($mapping['creates_journal_status'] ?? null)->toBe('draft')
        ->and($mapping['enabled'] ?? null)->toBeTrue();
});

it('vehicle sale completed mapping defines required and optional mapping keys', function (): void {
    $mapping = accountingEventVehicleSaleCompletedMapping();

    expect($mapping['required_mapping_keys'])->toContain('accounts_receivable_account')
        ->and($mapping['required_mapping_keys'])->toContain('sales_revenue_account')
        ->and($mapping['optional_mapping_keys'])->toContain('vehicle_inventory_account')
        ->and($mapping['optional_mapping_keys'])->toContain('cogs_account')
        ->and($mapping['optional_mapping_keys'])->toContain('tax_payable_account')
        ->and($mapping['optional_mapping_keys'])->toContain('overpayment_account')
        ->and($mapping['optional_mapping_keys'])->toContain('rounding_adjustment_account');
});

it('mapping keys do not contain runtime account ids or fixed account codes', function (): void {
    $mappingKeys = accountingEventVehicleSaleCompletedMapping()['mapping_keys'] ?? [];

    expect($mappingKeys)->not->toBeEmpty();

    foreach ($mappingKeys as $key => $metadata) {
        expect($metadata)->toHaveKeys(['label', 'description', 'intended_account_types', 'side', 'required', 'runtime_account_id'])
            ->and($metadata['runtime_account_id'])->toBeNull()
            ->and(array_key_exists('account_id', $metadata))->toBeFalse("{$key} must not define account_id")
            ->and(array_key_exists('account_code', $metadata))->toBeFalse("{$key} must not define account_code")
            ->and(array_key_exists('code', $metadata))->toBeFalse("{$key} must not define code");
    }
});

it('mapping account type metadata is present', function (): void {
    $mappingKeys = accountingEventVehicleSaleCompletedMapping()['mapping_keys'] ?? [];

    expect($mappingKeys['accounts_receivable_account']['intended_account_types'])->toContain('asset')
        ->and($mappingKeys['sales_revenue_account']['intended_account_types'])->toContain('revenue')
        ->and($mappingKeys['vehicle_inventory_account']['intended_account_types'])->toContain('asset')
        ->and($mappingKeys['cogs_account']['intended_account_types'])->toContain('expense')
        ->and($mappingKeys['tax_payable_account']['intended_account_types'])->toContain('liability')
        ->and($mappingKeys['overpayment_account']['intended_account_types'])->toContain('liability');
});

it('journal line templates do not imply posting or non revenue runtime', function (): void {
    $templates = accountingEventVehicleSaleCompletedMapping()['journal_line_templates'] ?? [];

    expect($templates)->not->toBeEmpty();

    foreach ($templates as $template) {
        expect($template)->toHaveKeys(['key', 'mapping_key', 'side', 'amount_source', 'enabled', 'description'])
            ->and($template['journal_status'] ?? null)->not->toBe('posted');
    }

    expect(collect($templates)->pluck('key')->all())->toContain('receivable_debit')
        ->and(collect($templates)->pluck('key')->all())->toContain('sales_revenue_credit')
        ->and(collect($templates)->pluck('key')->all())->toContain('cogs_debit')
        ->and(collect($templates)->pluck('key')->all())->toContain('vehicle_inventory_credit');
});

it('mapping config explicitly preserves non-goals', function (): void {
    $nonGoals = accountingEventVehicleSaleCompletedMapping()['non_goals'] ?? [];

    expect($nonGoals)->toContain('no_runtime_account_ids')
        ->and($nonGoals)->toContain('no_automatic_posting')
        ->and($nonGoals)->toContain('no_cogs_recognition_runtime')
        ->and($nonGoals)->toContain('no_profit_or_gross_margin_payload')
        ->and($nonGoals)->toContain('no_tax_runtime')
        ->and($nonGoals)->toContain('no_refund_or_reversal_runtime');
});

it('mapping config contains no profit gross margin or recognition amount keys', function (): void {
    $keys = accountingEventMappingConfigKeys(config('accounting_event_mappings', []));
    $forbiddenKeys = [
        'profit',
        'gross_profit',
        'gross_margin',
        'gross_margin_rate',
        'revenue_amount',
        'cogs_amount',
        'purchase_cost',
        'account_id',
        'account_code',
        'journal_entry_id',
    ];

    foreach ($forbiddenKeys as $forbiddenKey) {
        expect($keys)->not->toContain($forbiddenKey);
    }
});

it('mapping config does not create runtime journal entries', function (): void {
    config('accounting_event_mappings');

    expect(AccountingJournalEntry::count())->toBe(0)
        ->and(AccountingJournalEntryLine::count())->toBe(0);
});

it('mapping config exposes convert skeleton route without mapping management routes or permissions', function (): void {
    $this->seed(RolePermissionSeeder::class);

    expect(Route::has('employee-system.accounting.events.convert'))->toBeTrue()
        ->and(Route::has('employee-system.accounting.mappings.index'))->toBeFalse()
        ->and(Route::has('employee-system.accounting.mappings.update'))->toBeFalse()
        ->and(Permission::query()->where('name', 'module.accounting.events.convert')->exists())->toBeTrue()
        ->and(Permission::query()->whereIn('name', [
            'module.accounting.mappings.view',
            'module.accounting.mappings.update',
        ])->exists())->toBeFalse();
});

it('mapping config enables revenue side draft generation without automatic posting or future runtime', function (): void {
    $mapping = accountingEventVehicleSaleCompletedMapping();
    $templates = $mapping['journal_line_templates'] ?? [];

    expect($mapping['enabled'])->toBeTrue()
        ->and(class_exists('App\\Services\\AccountingEventConvertService'))->toBeTrue()
        ->and(collect($templates)->firstWhere('key', 'cogs_debit')['enabled'])->toBeFalse()
        ->and(collect($templates)->firstWhere('key', 'vehicle_inventory_credit')['enabled'])->toBeFalse()
        ->and($mapping['non_goals'])->toContain('no_automatic_posting')
        ->and($mapping['non_goals'])->toContain('no_cogs_recognition_runtime')
        ->and($mapping['non_goals'])->toContain('no_tax_runtime')
        ->and($mapping['non_goals'])->toContain('no_profit_or_gross_margin_payload');
});
