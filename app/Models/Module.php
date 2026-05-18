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
        'section',
        'parent_id',
        'parent_key',
        'route_name',
        'permission_prefix',
        'base_permission',
        'icon_key',
        'icon',
        'sort_order',
        'is_enabled',
        'is_active',
        'active_patterns',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'is_enabled' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'active_patterns' => 'array',
        ];
    }

    /**
     * 技術註解：提供階層式模組關聯，後續可用於群組化側欄與權限繼承判斷。
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * 技術註解：集中子模組關聯，避免階層查詢散落造成維護風險。
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
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
