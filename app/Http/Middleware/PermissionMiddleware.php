<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function __construct(protected PermissionService $permissions)
    {
    }

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        foreach ($permissions as $permission) {
            if ($this->permissions->userCan($user, $permission)) {
                return $next($request);
            }
        }

        if ($request->expectsJson()) {
            abort(403, 'No tiene permiso para esta acción.');
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('error', 'No tiene permiso para acceder a esta sección.');
    }
}
