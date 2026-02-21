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
use App\Http\Controllers\Api\CategoriaController; // Para que la ruta de categorías no falle

Route::prefix("v1")->group(function(){

    // =========================
    // AUTH
    // =========================
    Route::prefix("auth")->group(function(){
        Route::post("/register", [AuthController::class, "funRegister"]);
        Route::post("/login", [AuthController::class, "funLogin"]);

        Route::middleware('auth:sanctum')->group(function(){
            Route::post("/logout", [AuthController::class, "funlogout"]);
            Route::get("/profile", [AuthController::class, "funprofile"]);
        });
    });

    // =========================
    // RUTAS PROTEGIDAS
    // =========================
    Route::middleware('auth:sanctum')->group(function(){

        // ========= PERSONAS =========
        Route::get('/personas', [PersonaController::class, 'index']);
        Route::get('/persona/{id}', [PersonaController::class, 'show']);
        Route::post('/persona', [PersonaController::class, 'store']);
        Route::put('/persona/{id}', [PersonaController::class, 'update']);
        Route::delete('/persona/{id}', [PersonaController::class, 'destroy']);

        // ========= SERVICIOS =========
        Route::apiResource('servicios', ServicioController::class);

        // ========= TURNOS =========
        Route::apiResource('turnos', TurnoController::class);

        // ========= SERVICIO ↔ TURNO =========

        // Asignar un turno a un servicio
        Route::post(
            'servicios/{servicioId}/turnos',
            [ServicioTurnoController::class, 'asignar']
        );

        // Sincronizar múltiples turnos
        Route::put(
            'servicios/{servicioId}/turnos/sync',
            [ServicioTurnoController::class, 'sync']
        );

        // Quitar turno de un servicio
        Route::delete(
            'servicios/{servicioId}/turnos/{turnoId}',
            [ServicioTurnoController::class, 'quitar']
        );

        Route::apiResource('categorias', CategoriaController::class);
    });

    

    Route::prefix('usuario-servicio')->group(function () {
    Route::get('/', [UsuarioServicioController::class, 'index']);
    Route::post('/', [UsuarioServicioController::class, 'store']);
    Route::get('/{id}', [UsuarioServicioController::class, 'show']);
    Route::put('/{id}', [UsuarioServicioController::class, 'update']);
    Route::delete('/{id}', [UsuarioServicioController::class, 'destroy']);
});
// ========= USUARIOS (CRUD) =========
Route::apiResource('usuarios', UserController::class);

// ========= TURNOS ASIGNADOS (Lógica de Calendario) =========
Route::prefix('turnos-asignados')->group(function () {
    Route::post('/', [TurnoAsignadoController::class, 'store']); // Registrar turno
    Route::get('/mis-turnos', [TurnoAsignadoController::class, 'misTurnos']); // Turnos del usuario logueado
    Route::get('/reporte/{mes_id}', [TurnoAsignadoController::class, 'reporteMensual']); // Reportes
});

});