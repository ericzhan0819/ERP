<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's users.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                // 技術註解：User model 已設定 hashed cast，Seeder 保持明確測試密碼即可。
                'password' => 'password',
                'account_status' => 'active',
            ],
        );
    }
}
