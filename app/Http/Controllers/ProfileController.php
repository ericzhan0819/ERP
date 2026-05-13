<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            // 技術註解：僅提供前端設定檔頁需要的最小欄位，避免暴露非必要資訊。
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ]);
    }

    public function update(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $old = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ];

        $user->update($validated);

        $auditLogService->log(
            actor: $user,
            action: 'profile.updated',
            description: 'User updated profile',
            targetUser: $user,
            metadata: [
                'old' => $old,
                'new' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
            ]
        );

        return redirect()->route('employee-system.profile.edit')->with('success', '個人資料已更新。');
    }

    public function updatePassword(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = $request->user();
        $user->update([
            // 技術註解：明確使用 Hash::make 進行密碼雜湊，避免任何明文儲存風險。
            'password' => Hash::make($validated['password']),
        ]);

        $auditLogService->log(
            actor: $user,
            action: 'profile.password.updated',
            description: 'User updated password',
            targetUser: $user,
            metadata: [
                'changed_at' => now()->toISOString(),
            ]
        );

        return redirect()->route('employee-system.profile.edit')->with('success', '密碼已更新。');
    }
}
