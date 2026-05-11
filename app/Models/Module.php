<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    /**
     * 技術註解：僅允許模組註冊必要欄位批次寫入，維持資料入口明確可審計。
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'label',
        'route_name',
        'base_permission',
        'icon',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * 技術註解：集中封裝啟用狀態查詢，避免模組入口判斷散落各處。
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * 技術註解：固定以 sort_order 與 id 排序，提供穩定且可預期的模組顯示順序。
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
