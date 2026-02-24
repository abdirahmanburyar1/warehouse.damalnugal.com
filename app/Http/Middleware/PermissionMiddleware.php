<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (!$user) {
            if ($request->wantsJson()) {
                return response()->json('Unauthorized', 403);
            }
            abort(403, 'Unauthorized');
        }

        $permissions = array_map('trim', explode(',', $permission));
        $allowed = count($permissions) > 1
            ? $user->hasAnyPermission($permissions)
            : $user->hasPermission($permissions[0]);

        if (!$allowed) {
            if ($request->wantsJson()) {
                return response()->json('Unauthorized', 403);
            }
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
