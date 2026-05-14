<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleStatusLog extends Model
{
    use HasFactory;

    /**
     * 技術註解：狀態歷程屬於 append-only 稽核資料，不提供更新流程，僅允許建立所需欄位。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'vehicle_id',
        'company_id',
        'branch_id',
        'from_status',
        'to_status',
        'changed_by',
        'reason',
        'metadata',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vehicle_id' => 'integer',
            'company_id' => 'integer',
            'branch_id' => 'integer',
            'changed_by' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public $timestamps = false;

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

