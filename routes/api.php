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

Route::prefix("v1")->group(function(){

    // 🔓 RUTAS PÚBLICAS (Sin token)
    Route::post("/auth/register", [AuthController::class, "funRegister"]);
    Route::post("/auth/login", [AuthController::class, "funLogin"]);
    Route::get("/roles", [UserController::class, "getRoles"]); 
    Route::get("/categorias", [UserController::class, "index"]); 
    
    // Estas rutas permiten cargar el equipo y las categorías sin problemas de sesión
    Route::get("/categorias-lista", [TurnoAsignadoController::class, "listaCategorias"]);
    Route::get("/equipo-filtrado", [TurnoAsignadoController::class, "getEquipoFiltrado"]);

    // 🔒 RUTAS PROTEGIDAS POR TOKEN (SANCTUM)
    Route::middleware('auth:sanctum')->group(function(){
        
        // --- Perfil y Sesión ---
        Route::get("/auth/profile", [AuthController::class, "funprofile"]);
        Route::post("/auth/logout", [AuthController::class, "funlogout"]);

        // --- 🏗️ ADMINISTRACIÓN DE SISTEMA ---
        Route::middleware('jugadordeunbit:admin_system')->group(function() {
            Route::apiResource('persona', PersonaController::class);
            Route::apiResource('usuarios', UserController::class);
            Route::apiResource('categorias', CategoriaController::class);
            Route::apiResource('servicios', ServicioController::class);
            Route::apiResource('turnos', TurnoController::class);
            Route::apiResource('usuario-servicio', UsuarioServicioController::class);
            
            Route::put('/usuarios/{id}/password', [UserController::class, 'updatePassword'])
                ->middleware('jugadordeunbit:gestionar_seguridad');
            
            Route::post('/auth/generate-action-token', [AuthController::class, 'generateToken'])
                ->middleware('jugadordeunbit:gestionar_seguridad');
            
            Route::post("/auth/update-initial-password", [AuthController::class, "updateFirstPassword"]);
        });

        // --- 📝 GESTIÓN DE INCIDENCIAS ---
        Route::get('incidencias', [IncidenciaController::class, 'index']);
        Route::post('incidencias', [IncidenciaController::class, 'store'])
            ->middleware('jugadordeunbit:reportar_incidencia');
       
            Route::put('incidencias/{id}/resolver', [IncidenciaController::class, 'resolver'])
            ->middleware('jugadordeunbit:resolver_incidencia');

        // --- 📅 CONFIGURACIÓN DE CALENDARIO ---
        Route::get('/calendario/configuracion', function() {
            return [
                'gestiones' => \App\Models\Gestion::with('meses.semanas')->get(),
                'mes_actual' => date('n'),
                'anio_actual' => date('Y')
            ];
        });
            
        // --- 📅 CONFIGURACIÓN DE TURNOS EN SERVICIOS ---
        Route::prefix('servicios/{servicioId}/turnos')->middleware('jugadordeunbit:gestionar_servicios')->group(function () {
            Route::post('/', [ServicioTurnoController::class, 'asignar']);
            Route::put('/sync', [ServicioTurnoController::class, 'sync']);
            Route::delete('/{turnoId}', [ServicioTurnoController::class, 'quitar']);
        });

        // --- 👥 LÓGICA DE ASIGNACIÓN A PERSONAL ---
        Route::prefix('turnos-asignados')->group(function () {
            
            

            Route::post('/', [TurnoAsignadoController::class, 'store'])
                ->middleware('jugadordeunbit:asignar_turnos'); 
            
           Route::post('intercambiar', [TurnoAsignadoController::class, 'intercambiarTurno'])
            ->middleware('jugadordeunbit:asignar_turnos');
            
           Route::get('reporte-semanal/{semana_id}/{usuario_id?}', [TurnoAsignadoController::class, 'reporteHorasSemana']);
            
            Route::get('/servicio/{servicioId}/equipo', [TurnoAsignadoController::class, 'verTurnosPorJerarquia'])
             ->middleware('jugadordeunbit:ver_equipo');

            Route::get('/reporte/{mes_id}', [TurnoAsignadoController::class, 'reporteMensual'])
               ->middleware('jugadordeunbit:ver_reportes');
                
            Route::post('replicar-mes', [TurnoAsignadoController::class, 'replicarMes'])
            ->middleware('jugadordeunbit:asignar_turnos');
            
           Route::post('/vaciar-mes', [TurnoAsignadoController::class, 'vaciarMes'])
           ->middleware('jugadordeunbit:asignar_turnos');

            Route::post('rotar-mensual', [TurnoAsignadoController::class, 'rotarPersonalPorMes'])
            ->middleware('jugadordeunbit:asignar_turnos');
   
    Route::post('actualizar', [TurnoAsignadoController::class, 'actualizarPosicion'])
        ->middleware('jugadordeunbit:asignar_turnos');


            Route::get('/mis-turnos', [TurnoAsignadoController::class, 'misTurnos']); 
        });
    });
});