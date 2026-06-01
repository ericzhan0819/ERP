<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    /**
     * 技術註解：第一階段僅允許最小必要欄位，避免誤把公司設定擴散成完整多租戶流程。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'tax_id',
        'phone',
        'email',
        'address',
        'logo_url',
        'currency',
        'brand_name',
        'brand_name_en',
        'brand_subtitle',
        'brand_slogan',
        'brand_eyebrow',
    ];
}
