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
        // 1. Verificar si el usuario está logueado
        if (!$request->user()) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        // 2. Verificar si el usuario tiene AL MENOS UNO de los permisos enviados
        $tienePermiso = false;
        foreach ($permissions as $permission) {
            if ($request->user()->hasPermission($permission)) {
                $tienePermiso = true;
                break;
            }
        }

        if (!$tienePermiso) {
            // Unimos los permisos en un string para el mensaje de error
            $listaPermisos = implode(', ', $permissions);
            return response()->json([
                'message' => "No tienes el permiso necesario: [$listaPermisos]"
            ], 403);
        }

        return $next($request);
    }
}