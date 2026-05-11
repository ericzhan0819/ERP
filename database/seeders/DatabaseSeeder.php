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
        // 技術註解：第二階段僅建立基本 Admin 測試帳號，不建立角色或權限資料。
        $this->call(UserSeeder::class);
    }
}
