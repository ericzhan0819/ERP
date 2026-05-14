<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{
    use HasFactory;

    /**
     * 技術註解：第一階段只開放必要欄位，將租戶邊界控制在最小可驗證範圍。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'code',
    ];

    /**
     * 技術註解：分店必須明確回指公司，避免 tenant 判斷出現跨公司資料混用。
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

