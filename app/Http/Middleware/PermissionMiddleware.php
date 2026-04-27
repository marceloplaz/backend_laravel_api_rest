<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Manejar la petición.
     * * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$permissions  <-- Cambiado a spread operator para recibir múltiples valores
     */
    // App\Http\Middleware\PermissionMiddleware.php

// App\Http\Middleware\PermissionMiddleware.php
// App\Http\Middleware\PermissionMiddleware.php
public function handle(Request $request, Closure $next, ...$permissions): Response
{
    $user = $request->user();
    
    // 1. Obtenemos los roles del usuario
    $rolesEnDB = \DB::table('roles')
        ->join('role_user', 'roles.id', '=', 'role_user.role_id')
        ->where('role_user.user_id', $user->id)
        ->pluck('name')
        ->toArray();

    // 2. Procesamos los permisos/roles requeridos por la ruta
    $allPermissions = [];
    foreach ($permissions as $p) {
        $allPermissions = array_merge($allPermissions, explode(',', $p));
    }

    // 3. Verificamos si hay coincidencia
    $hasPermission = !empty(array_intersect($rolesEnDB, $allPermissions));

    if ($hasPermission) {
        // ESTA ES LA LÍNEA MÁS IMPORTANTE: Permite que la petición siga al controlador
        return $next($request);
    }

    // 4. Si no tiene permiso, lanzamos el error 403 real
    return response()->json([
        'message' => 'No tienes permisos para acceder a esta ruta.',
        'tus_roles' => $rolesEnDB,
        'roles_requeridos' => $allPermissions
    ], 403);
}
}