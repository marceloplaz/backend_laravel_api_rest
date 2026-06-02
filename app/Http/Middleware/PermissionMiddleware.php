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




public function handle(Request $request, Closure $next, ...$permissions): Response
{
    $user = $request->user();
    
    // 1. Obtener los nombres de los roles del usuario logueado
    $rolesEnDB = \DB::table('roles')
        ->join('role_user', 'roles.id', '=', 'role_user.role_id')
        ->where('role_user.user_id', $user->id)
        ->pluck('name')
        ->toArray();

    // 2. Procesar los roles requeridos, separando por '|' o ','
    $allPermissions = [];
    foreach ($permissions as $p) {
        // Reemplazamos el pipe '|' por una coma antes de hacer el explode
        $rolesNormalizados = str_replace('|', ',', $p);
        $allPermissions = array_merge($allPermissions, explode(',', $rolesNormalizados));
    }

    // 3. Verificamos si hay coincidencia usando array_intersect
    $hasPermission = !empty(array_intersect($rolesEnDB, $allPermissions));

    if ($hasPermission) {
        return $next($request);
    }

    // 4. Si falla, retornamos el error con los datos procesados para depurar
    return response()->json([
        'message' => 'No tienes permisos para acceder a esta ruta.',
        'tus_roles' => $rolesEnDB,
        'roles_requeridos' => $allPermissions
    ], 403);
}
}