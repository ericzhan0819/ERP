<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleSalePayment extends Model
{
    /**
     * 技術註解：收款紀錄僅開放必要業務欄位與後端決定的系統欄位，避免前端 mass assignment 注入租戶、毛利或退款資料。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'branch_id',
        'vehicle_id',
        'vehicle_sale_id',
        'customer_id',
        'payment_number',
        'payment_type',
        'payment_method',
        'amount',
        'paid_at',
        'reference_no',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'branch_id' => 'integer',
            'vehicle_id' => 'integer',
            'vehicle_sale_id' => 'integer',
            'customer_id' => 'integer',
            'amount' => 'decimal:2',
            'paid_at' => 'date',
            'voided_at' => 'datetime',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'voided_by' => 'integer',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function vehicleSale(): BelongsTo { return $this->belongsTo(VehicleSale::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function voider(): BelongsTo { return $this->belongsTo(User::class, 'voided_by'); }
}