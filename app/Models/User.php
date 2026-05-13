<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'name',
        'email',
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
}
