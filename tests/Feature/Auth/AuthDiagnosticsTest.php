<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('dashboard named route exists and redirects to employee system overview', function () {
    // 技術註解：最小風險驗證 dashboard 命名路由存在，且維持導向既有 employee-system.overview。
    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response->assertRedirect(route('employee-system.overview', absolute: false));
});

test('authenticated inertia page shares non-null auth user payload', function () {
    // 技術註解：直接驗證 Inertia share 的 auth.user，不重構既有流程。
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/employee-system');

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // 技術註解：最小驗證 auth.user 已注入且非 null，避免資料型別差異造成誤判。
            ->has('auth.user')
            ->where('auth.user', fn ($sharedUser) => $sharedUser !== null)
        );
});
