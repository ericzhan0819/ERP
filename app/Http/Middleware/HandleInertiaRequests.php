<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                // 技術註解：只共享登入頁與主控台必要辨識資料，避免外洩密碼、角色或權限資訊。
                'user' => $request->user()?->only('id', 'name', 'email'),
            ],
            // 技術註解：帳號狀態獨立於權限系統，僅提供前端呈現帳號可用狀態。
            'accountStatus' => $request->user()?->account_status,
        ];
    }
}
