<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 以現有系統角色名稱為準（RoleSeeder: Admin/Manager/Staff）
        // 將需求中的中文角色對應至系統角色，避免角色命名差異造成指派失敗
        $roleMap = [
            '管理員' => 'Admin',
            '經理' => 'Manager',
            '員工' => 'Staff',
        ];

        // 使用 updateOrCreate 避免重複建立，並確保可重複執行 seeder
        $users = [
            [
                'name' => '小詹',
                'phone' => '0972111111',
                'email' => '111@test.com',
                'password' => '12345678', // User 模型 casts 已設定 hashed，會自動雜湊
                'role_zh' => '管理員',
            ],
            [
                'name' => '小明',
                'phone' => '0972222222',
                'email' => '222@test.com',
                'password' => '12345678',
                'role_zh' => '經理',
            ],
            [
                'name' => '小花',
                'phone' => '0972333333',
                'email' => '333@test.com',
                'password' => '12345678',
                'role_zh' => '員工',
            ],
        ];

        foreach ($users as $item) {
            $roleName = $roleMap[$item['role_zh']] ?? $item['role_zh'];

            $user = User::updateOrCreate(
                ['email' => $item['email']],
                [
                    'name' => $item['name'],
                    'phone' => $item['phone'],
                    'password' => $item['password'],
                ]
            );

            // syncRoles 確保角色狀態一致且不重複
            $user->syncRoles([$roleName]);
        }
    }
}
