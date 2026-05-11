<?php

namespace App\Http\Controllers;

use App\Models\ModulePermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModulePermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $modulePermissions = ModulePermission::query()
            ->orderBy('id')
            ->get([
                'id',
                'module_key',
                'module_name',
                'allowed_roles',
                'allowed_user_ids',
                'enabled',
                'updated_at',
            ]);

        return response()->json([
            'data' => $modulePermissions,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_key' => ['required', 'string', 'max:255'],
            'module_name' => ['required', 'string', 'max:255'],
            'allowed_roles' => ['nullable', 'array'],
            'allowed_roles.*' => ['string', 'max:255'],
            'allowed_user_ids' => ['nullable', 'array'],
            'allowed_user_ids.*' => ['integer'],
            'enabled' => ['required', 'boolean'],
        ]);

        $modulePermission = ModulePermission::query()->updateOrCreate(
            ['module_key' => $validated['module_key']],
            [
                'module_name' => $validated['module_name'],
                'allowed_roles' => $validated['allowed_roles'] ?? [],
                'allowed_user_ids' => $validated['allowed_user_ids'] ?? [],
                'enabled' => $validated['enabled'],
            ]
        );

        return response()->json([
            'message' => 'Module permission updated successfully.',
            'data' => $modulePermission,
        ]);
    }

    public function batchUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.module_key' => ['required', 'string', 'max:255'],
            'items.*.module_name' => ['required', 'string', 'max:255'],
            'items.*.allowed_roles' => ['nullable', 'array'],
            'items.*.allowed_roles.*' => ['string', 'max:255'],
            'items.*.allowed_user_ids' => ['nullable', 'array'],
            'items.*.allowed_user_ids.*' => ['integer'],
            'items.*.enabled' => ['required', 'boolean'],
        ]);

        foreach ($validated['items'] as $item) {
            ModulePermission::query()->updateOrCreate(
                ['module_key' => $item['module_key']],
                [
                    'module_name' => $item['module_name'],
                    'allowed_roles' => $item['allowed_roles'] ?? [],
                    'allowed_user_ids' => $item['allowed_user_ids'] ?? [],
                    'enabled' => $item['enabled'],
                ]
            );
        }

        return response()->json([
            'message' => 'Module permissions updated successfully.',
        ]);
    }
}

