<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

function loginThrottleKey(string $loginInput, string $ip): string
{
    return Str::transliterate(Str::lower(trim($loginInput))).'|'.$ip;
}

it('正確帳密可登入', function (): void {
    $user = User::create([
        'name' => 'Login User',
        'email' => 'login-success@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);

    $response = $this->from(route('login'))
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

    $response->assertRedirect(route('employee-system.overview', absolute: false));
    $this->assertAuthenticatedAs($user);
});

it('錯誤帳密多次後被 throttle', function (): void {
    $ip = '127.0.0.1';

    User::create([
        'name' => 'Throttle User',
        'email' => 'throttle-target@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'throttle-target@example.com',
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('email');
    }

    $this->withServerVariables(['REMOTE_ADDR' => $ip])
        ->from(route('login'))
        ->post(route('login.store'), [
            'email' => 'throttle-target@example.com',
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('email');

    $key = loginThrottleKey('throttle-target@example.com', $ip);
    expect(RateLimiter::tooManyAttempts($key, 5))->toBeTrue();
});

it('throttle 錯誤不暴露帳號是否存在', function (): void {
    $ip = '127.0.0.1';

    User::create([
        'name' => 'Existing User',
        'email' => 'existing-user@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'existing-user@example.com',
                'password' => 'wrong-password',
            ]);

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'not-found-user@example.com',
                'password' => 'wrong-password',
            ]);
    }

    $existingResponse = $this->withServerVariables(['REMOTE_ADDR' => $ip])
        ->from(route('login'))
        ->post(route('login.store'), [
            'email' => 'existing-user@example.com',
            'password' => 'wrong-password',
        ]);

    $missingResponse = $this->withServerVariables(['REMOTE_ADDR' => $ip])
        ->from(route('login'))
        ->post(route('login.store'), [
            'email' => 'not-found-user@example.com',
            'password' => 'wrong-password',
        ]);

    $existingMessage = $existingResponse->baseResponse->getSession()->get('errors')['default']['email'][0] ?? null;
    $missingMessage = $missingResponse->baseResponse->getSession()->get('errors')['default']['email'][0] ?? null;

    $existingResponse->assertSessionHasErrors('email');
    $missingResponse->assertSessionHasErrors('email');
    expect($existingMessage)->toBe($missingMessage);
});

it('成功登入後可清除 throttle 狀態', function (): void {
    $ip = '127.0.0.1';
    $email = 'clear-after-success@example.com';

    User::create([
        'name' => 'Clear User',
        'email' => $email,
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);

    for ($i = 0; $i < 3; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->from(route('login'))
            ->post(route('login.store'), [
                'email' => $email,
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('email');
    }

    $this->withServerVariables(['REMOTE_ADDR' => $ip])
        ->from(route('login'))
        ->post(route('login.store'), [
            'email' => $email,
            'password' => 'password',
        ])
        ->assertRedirect(route('employee-system.overview', absolute: false));

    $key = loginThrottleKey($email, $ip);
    expect(RateLimiter::attempts($key))->toBe(0);
});
