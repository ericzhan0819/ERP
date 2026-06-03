<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleSale extends Model
{
    /**
     * 技術註解：僅開放銷售 MVP 業務欄位與必要系統欄位，避免 mass assignment 注入成本、毛利或租戶邊界外資料。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'branch_id',
        'vehicle_id',
        'customer_name',
        'customer_phone',
        'sale_price',
        'deposit_amount',
        'paid_amount',
        'sale_status',
        'sold_at',
        'salesperson_name',
        'commission_amount',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'branch_id' => 'integer',
            'vehicle_id' => 'integer',
            'sale_price' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'sold_at' => 'datetime',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
