<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 技術註解：統一由 RBAC Seeder 建立測試帳號、角色與最小模組權限。
        $this->call(RolePermissionSeeder::class);

        // 技術註解：在預設 company 建立完成後，補齊公司層級共用的預設會計科目，讓傳票可以立即選用科目。
        $this->call(AccountingAccountSeeder::class);
    }
}
