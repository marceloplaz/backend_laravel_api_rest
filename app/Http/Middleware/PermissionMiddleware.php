<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission)
{
    // 1. Verificar si el usuario está logueado
    if (!$request->user()) {
        return response()->json(['message' => 'No autenticado'], 401);
    }

    // 2. Usar el método de tu modelo User para verificar permisos
    if (!$request->user()->hasPermission($permission)) {
        return response()->json([
            'message' => "No tienes el permiso necesario: [$permission]"
        ], 403);
    }

    return $next($request);
}
}
