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
     * 技術註解：模組路由只委派 PermissionService 判斷，不直接呼叫任何 Spatie API。
     */
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $user = $request->user();

        abort_unless($user && $this->permissionService->canAccessModule($user, $moduleKey), 403);

        return $next($request);
    }
}
