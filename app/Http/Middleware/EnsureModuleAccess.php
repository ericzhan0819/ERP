<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function __construct(private readonly PermissionService $permissionService) {}

    /**
     * 技術註解：middleware 參數即 module key；資料庫找不到、停用或權限不足皆回 403。
     */
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $user = $request->user();

        abort_unless($user && $this->permissionService->canAccessModule($user, $moduleKey), 403);

        return $next($request);
    }
}
