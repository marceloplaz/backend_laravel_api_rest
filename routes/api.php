<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\PersonaController;
use App\Http\Controllers\Api\ServicioController;
use App\Http\Controllers\Api\TurnoController;
use App\Http\Controllers\Api\ServicioTurnoController;
use App\Http\Controllers\Api\UsuarioServicioController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TurnoAsignadoController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\IncidenciaController;

Route::prefix("v1")->group(function () {

    // 🔓 RUTAS ABIERTAS (Mínimo acceso posible)
    // Se añade 'throttle:5,1' para permitir solo 5 intentos de login por minuto por IP.
    Route::post("/auth/login", [AuthController::class, "funLogin"])
        ->middleware('throttle:5,1');
        
    Route::post("/auth/register", [AuthController::class, "funRegister"]);


    // 🔒 RUTAS PROTEGIDAS POR TOKEN (SANCTUM)
    Route::middleware('auth:sanctum')->group(function () {

        // --- 👤 PERFIL Y SESIÓN ---
        Route::get("/auth/profile", [AuthController::class, "funprofile"]);
        Route::post("/auth/logout", [AuthController::class, "funlogout"]);

        // --- 🔍 CONSULTAS GENERALES (Ahora protegidas con Token) ---
        // Estas rutas antes eran públicas, pero contienen info interna del negocio.
        Route::get("/roles", [UserController::class, "getRoles"]);
        Route::get("/categorias-lista", [TurnoAsignadoController::class, "listaCategorias"]);
        Route::get("/equipo-filtrado", [TurnoAsignadoController::class, "getEquipoFiltrado"]);
        Route::get("/lista-turnos-disponibles", [TurnoController::class, "index"]);

        // --- 🏗️ ADMINISTRACIÓN DE SISTEMA ---
        Route::middleware('jugadordeunbit:admin_system')->group(function () {
            Route::apiResource('persona', PersonaController::class);
            Route::apiResource('usuarios', UserController::class);
            Route::apiResource('categorias', CategoriaController::class);
            Route::apiResource('servicios', ServicioController::class);
            Route::apiResource('turnos', TurnoController::class);
            Route::apiResource('usuario-servicio', UsuarioServicioController::class);

            // Gestión de seguridad avanzada
            Route::middleware('jugadordeunbit:gestionar_seguridad')->group(function () {
                Route::put('/usuarios/{id}/password', [UserController::class, 'updatePassword']);
                Route::post('/auth/generate-action-token', [AuthController::class, 'generateToken']);
            });

            Route::post("/auth/update-initial-password", [AuthController::class, "updateFirstPassword"]);
        });

        // --- 📝 GESTIÓN DE INCIDENCIAS ---
        Route::get('incidencias', [IncidenciaController::class, 'index']);
        Route::post('incidencias', [IncidenciaController::class, 'store'])
            ->middleware('jugadordeunbit:reportar_incidencia');

        Route::put('incidencias/{id}/resolver', [IncidenciaController::class, 'resolver'])
            ->middleware('jugadordeunbit:resolver_incidencia');

        // --- 📅 CONFIGURACIÓN DE CALENDARIO ---
        Route::get('/calendario/configuracion', function () {
            return [
                'gestiones' => \App\Models\Gestion::with('meses.semanas')->get(),
                'mes_actual' => (int)date('n'),
                'anio_actual' => (int)date('Y')
            ];
        });

        // --- 📅 CONFIGURACIÓN DE TURNOS EN SERVICIOS ---
        Route::prefix('servicios/{servicioId}/turnos')
            ->middleware('jugadordeunbit:gestionar_servicios')
            ->group(function () {
                Route::post('/', [ServicioTurnoController::class, 'asignar']);
                Route::put('/sync', [ServicioTurnoController::class, 'sync']);
                Route::delete('/{turnoId}', [ServicioTurnoController::class, 'quitar']);
            });

        // --- 👥 LÓGICA DE ASIGNACIÓN A PERSONAL ---
        Route::prefix('turnos-asignados')->group(function () {

            // Rutas de escritura/gestión (Solo personal autorizado)
            Route::middleware('jugadordeunbit:asignar_turnos')->group(function () {
                Route::post('/', [TurnoAsignadoController::class, 'store']);
                Route::post('intercambiar', [TurnoAsignadoController::class, 'intercambiarTurno']);
                Route::post('replicar-mes', [TurnoAsignadoController::class, 'replicarMes']);
                Route::post('/vaciar-mes', [TurnoAsignadoController::class, 'vaciarMes']);
                Route::post('rotar-mensual', [TurnoAsignadoController::class, 'rotarPersonalPorMes']);
                Route::post('actualizar', [TurnoAsignadoController::class, 'actualizarPosicion']);

                Route::put('{id}', [TurnoAsignadoController::class, 'update']);      // Para editar tipo de turno u observación
                Route::delete('{id}', [TurnoAsignadoController::class, 'destroy']); // Para eliminar una asignación
            });

            // Rutas de visualización con permisos específicos
            Route::get('reporte-semanal/{semana_id}/{usuario_id?}', [TurnoAsignadoController::class, 'reporteHorasSemana']);

            Route::get('/servicio/{servicioId}/equipo', [TurnoAsignadoController::class, 'verTurnosPorJerarquia'])
                ->middleware('jugadordeunbit:ver_equipo');

            Route::get('/reporte/{mes_id}', [TurnoAsignadoController::class, 'reporteMensual'])
                ->middleware('jugadordeunbit:ver_reportes');

            // Ruta personal (Cualquier usuario autenticado ve sus propios turnos)
            Route::get('/mis-turnos', [TurnoAsignadoController::class, 'misTurnos']);
        });
    });
});