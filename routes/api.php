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

    // 🔓 RUTAS PÚBLICAS
    Route::post("/auth/register", [AuthController::class, "funRegister"]);
    Route::post("/auth/login", [AuthController::class, "funLogin"]);

    // 🔒 RUTAS PROTEGIDAS POR TOKEN (SANCTUM)
    Route::middleware('auth:sanctum')->group(function(){
        
        // --- Perfil y Sesión ---
        Route::get("/auth/profile", [AuthController::class, "funprofile"]);
        Route::post("/auth/logout", [AuthController::class, "funlogout"]);

        // --- 🏗️ ADMINISTRACIÓN DE SISTEMA (Solo Super Admin / Admin) ---
        // Usamos el alias 'jugadordeunbit' con el permiso 'admin_system'
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

        // --- 📝 GESTIÓN DE INCIDENCIAS (Estilo Google Keep) ---
        // Ver notas: Cualquier usuario autenticado puede verlas
        Route::get('incidencias', [IncidenciaController::class, 'index']);
        
        // Crear nota de falla: Solo personal con permiso de reporte
        Route::post('incidencias', [IncidenciaController::class, 'store'])
            ->middleware('jugadordeunbit:reportar_incidencia');
            
        // Marcar como resuelto: Solo el Responsable Técnico
        Route::put('incidencias/{id}/resolver', [IncidenciaController::class, 'resolver'])
            ->middleware('jugadordeunbit:resolver_incidencia');

        
        // --- 📅 CONFIGURACIÓN DE TURNOS EN SERVICIOS (Jefes de Área) ---
        Route::prefix('servicios/{servicioId}/turnos')->middleware('jugadordeunbit:gestionar_servicios')->group(function () {
            Route::post('/', [ServicioTurnoController::class, 'asignar']);
            Route::put('/sync', [ServicioTurnoController::class, 'sync']);
            Route::delete('/{turnoId}', [ServicioTurnoController::class, 'quitar']);
        });

        // --- 👥 LÓGICA DE ASIGNACIÓN A PERSONAL (Jerarquías) ---
        Route::prefix('turnos-asignados')->group(function () {
            
            // Asignar turnos a subordinados (Jefes de Servicio/Enfermería/Generales)
            Route::post('/', [TurnoAsignadoController::class, 'store'])
                ->middleware('jugadordeunbit:asignar_turnos'); 

            // Ver calendario del equipo (Jefes)
            Route::get('/servicio/{servicioId}/equipo', [TurnoAsignadoController::class, 'verTurnosPorJerarquia'])
                ->middleware('jugadordeunbit:ver_equipo');

            // Descarga de reportes (Admin / Jefes)
            Route::get('/reporte/{mes_id}', [TurnoAsignadoController::class, 'reporteMensual'])
                ->middleware('jugadordeunbit:ver_reportes');

            // 🏥 Uso común: Cualquier empleado logueado ve su propio calendario
            Route::get('/mis-turnos', [TurnoAsignadoController::class, 'misTurnos']); 
        });
   
    });
});