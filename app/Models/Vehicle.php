<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * 技術註解：僅允許 Vehicle Foundation Slice 指定欄位，避免後續誤混入採購/會計/佣金等未授權資料面。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'branch_id',
        'stock_number',
        'vin',
        'license_plate',
        'brand',
        'model',
        'variant',
        'model_year',
        'exterior_color',
        'interior_color',
        'odometer_km',
        'lifecycle_status',
        'internal_notes',
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
            'model_year' => 'integer',
            'odometer_km' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    /**
     * 技術註解：VIN 正規化集中於 Model mutator，確保任何寫入路徑都不會遺漏大小寫與空白處理。
     */
    public function setVinAttribute(?string $value): void
    {
        if ($value === null) {
            $this->attributes['vin'] = null;
            return;
        }

        $normalized = strtoupper(str_replace(' ', '', trim($value)));
        $this->attributes['vin'] = $normalized === '' ? null : $normalized;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * 技術註解：明確定義公司關聯，供詳情頁安全輸出公司代碼/名稱，避免直接顯示 FK ID 影響可讀性。
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * 技術註解：明確定義分店關聯，供詳情頁安全輸出分店代碼/名稱，避免直接顯示 FK ID 影響可讀性。
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(VehicleStatusLog::class);
    }
}
