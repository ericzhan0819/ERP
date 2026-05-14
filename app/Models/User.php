<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use Notifiable;

    /**
     * 技術註解：固定使用 web guard，讓 RBAC 與既有登入 Auth guard 保持一致。
     */
    protected string $guard_name = 'web';

    /**
     * 技術註解：僅開放基本登入與帳號狀態欄位，避免混入角色或權限責任。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'email',
        'phone',
        'password',
        'account_status',
        'is_active',
        'last_login_at',
    ];

    /**
     * 技術註解：密碼與 remember token 必須隱藏，維持 Auth shared props 的最小資料面。
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * 技術註解：使用 Laravel 內建 hashed cast，集中處理密碼雜湊相容性。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * 技術註解：使用者隸屬公司是 tenant 邊界核心，集中於 model relation 方便 policy 與查詢一致使用。
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    /**
     * 技術註解：分店關聯獨立於角色權限，避免將資料邊界責任混入 RBAC 判斷。
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }
}
