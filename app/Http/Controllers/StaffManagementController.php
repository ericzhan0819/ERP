<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;

class StaffManagementController extends Controller
{
    public function index()
    {
        $staff = User::query()
            ->with(['roles:name', 'permissions:name'])
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->values(),
                'permissions' => $user->permissions->pluck('name')->values(),
            ]);

        $permissions = Permission::query()
            ->orderBy('name')
            ->get(['name'])
            ->pluck('name');

        // 依照 permission 命名規則（module.action）自動分組，避免前端硬編碼模塊。
        $permissionMatrix = $permissions
            ->groupBy(function (string $permissionName): string {
                $firstDotPosition = strpos($permissionName, '.');

                return $firstDotPosition === false
                    ? 'general'
                    : substr($permissionName, 0, $firstDotPosition);
            })
            ->map(fn ($groupPermissions, string $module) => [
                'module' => $module,
                'permissions' => $groupPermissions->values(),
            ])
            ->values();

        return view('staff.permissions', [
            'staff' => $staff,
            'permissionMatrix' => $permissionMatrix,
        ]);
    }

    public function updatePermissions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        $targetUser->syncPermissions($validated['permissions']);

        Log::info('staff_permissions_updated', [
            'actor_id' => $request->user()?->id,
            'target_user_id' => $targetUser->id,
            'permissions' => $validated['permissions'],
        ]);

        return response()->json([
            'message' => 'Permissions updated successfully.',
        ]);
    }
}
