<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Importación de controladores
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
use App\Http\Controllers\Api\NovedadLaboralController;

Route::prefix("v1")->group(function () {

    // 🔓 RUTAS PÚBLICAS
    Route::post("/auth/login", [AuthController::class, "funLogin"])->middleware('throttle:5,1');
    Route::post("/auth/register", [AuthController::class, "funRegister"]);
    Route::get('buscar-profesionales', [UserController::class, 'index']); 
     Route::get('personal/exportar-pdf', [PersonaController::class, 'exportarPdf']);

    
    // 🔒 RUTAS PROTEGIDAS (Token Sanctum)
    Route::middleware('auth:sanctum')->group(function () {

        // Definición de grupos de acceso (Sincronizado con Angular)
        $ROLES_ADMIN_FULL = 'super_admin,admin,admin_jefe_medico,admin_jefa_enfermeras,jefa_servicios_generales';
        $ROLES_JEFATURAS  = $ROLES_ADMIN_FULL . ',jefe_medico_servicio,jefa_enfermeras,jefe_servicio';
        $ROLES_TURNOS     = $ROLES_JEFATURAS  . ',jefa_enfermeras_servicio';
        $ROLES_TECNICO    = $ROLES_JEFATURAS  . ',tecnico';

        // --- Perfil y Sesión ---
        Route::get("/auth/profile", [AuthController::class, "funprofile"]);
        Route::post("/auth/logout", [AuthController::class, "funlogout"]);
        Route::get('persona/{id}', [PersonaController::class, 'show']);
      
        // --- Consultas Base del Dashboard (Accesibles para todos los logueados) ---
        Route::get("/roles", [UserController::class, "getRoles"]);
        Route::get("/categorias-lista", [TurnoAsignadoController::class, "listaCategorias"]);
        Route::get("/filtros-jerarquia", [TurnoController::class, "getFiltrosPorJerarquia"]);
        Route::get("/lista-turnos-disponibles", [TurnoController::class, "index"]);

        Route::get('servicios/{id}/turnos-habilitados', [ServicioTurnoController::class, 'getTurnosHabilitados']);
        Route::get("/mis-turnos", [TurnoAsignadoController::class, "misTurnos"]);
        Route::get("/equipo-filtrado", [TurnoAsignadoController::class, "getEquipoFiltrado"]);
        
        // =========================================================
        // 🏥 GESTIÓN DE SERVICIOS Y PERSONAL (ROLES_JEFATURAS)
        // =========================================================
        Route::middleware("jugadordeunbit:{$ROLES_JEFATURAS}")->group(function () {
          Route::get('persona-catalogos', [PersonaController::class, 'getFormDependencies']);  
          Route::apiResource('usuarios', UserController::class);            
            Route::apiResource('turnos', TurnoController::class);
            Route::get('servicios/{id}', [ServicioController::class, 'show']);
            Route::apiResource('servicios', ServicioController::class); 

            Route::get('areas', [ServicioController::class, 'getAreas']);
            Route::apiResource('usuario-servicio', UsuarioServicioController::class);

            Route::apiResource('persona', PersonaController::class);
           
            Route::apiResource('categorias', CategoriaController::class);

            // Configuración de turnos por servicio
            Route::prefix('servicios/{servicioId}/turnos')->group(function () {
                Route::post('/', [ServicioTurnoController::class, 'asignar']);
                Route::put('/sync', [ServicioTurnoController::class, 'sync']);
                Route::delete('/{turnoId}', [ServicioTurnoController::class, 'quitar']);
            });
        });

        // =========================================================
        // 📅 PLANIFICACIÓN DE TURNOS Y NOVEDADES (ROLES_TURNOS)
        // =========================================================
        Route::middleware("jugadordeunbit:{$ROLES_TURNOS}")->group(function () {
            
            Route::prefix('turnos-asignados')->group(function () {
                Route::post('/', [TurnoAsignadoController::class, 'store']);
                Route::post('intercambiar', [TurnoAsignadoController::class, 'intercambiarTurno']);
                Route::post('replicar-mes', [TurnoAsignadoController::class, 'replicarMes']);
                Route::post('/vaciar-mes', [TurnoAsignadoController::class, 'vaciarMes']);
                Route::post('rotar-mensual', [TurnoAsignadoController::class, 'rotarPersonalPorMes']);
                Route::post('actualizar', [TurnoAsignadoController::class, 'actualizarPosicion']);
                Route::put('{id}', [TurnoAsignadoController::class, 'update']);
                Route::delete('{id}', [TurnoAsignadoController::class, 'destroy']);
            });

            Route::prefix('novedades')->group(function () {
                Route::get('/', [NovedadLaboralController::class, 'index']);
                Route::post('/registrar', [NovedadLaboralController::class, 'store']);
                Route::post('/permutar-turnos', [NovedadLaboralController::class, 'permutarConNovedad']);
                Route::put('/{id}/devolver', [NovedadLaboralController::class, 'marcarDevolucion']);
            });
        });

        // =========================================================
        // 📊 REPORTES Y VISUALIZACIÓN
        // =========================================================
        Route::get('turnos/resumen-mensual', [TurnoAsignadoController::class, 'getResumenMensual']);
        Route::get('reporte-semanal/{semana_id}/{usuario_id?}', [TurnoAsignadoController::class, 'reporteHorasSemana']);
        
        Route::get('/servicio/{servicioId}/equipo', [TurnoAsignadoController::class, 'verTurnosPorJerarquia'])
            ->middleware('jugadordeunbit:ver_equipo');

        Route::get('/reporte/{mes_id}', [TurnoAsignadoController::class, 'reporteMensual'])
            ->middleware('jugadordeunbit:ver_reportes');

        // =========================================================
        // ⚠️ GESTIÓN DE INCIDENCIAS (ROLES_TECNICO)
        // =========================================================
        Route::get('incidencias', [IncidenciaController::class, 'index']);
        Route::middleware("jugadordeunbit:{$ROLES_TECNICO}")->group(function () {
            Route::post('incidencias', [IncidenciaController::class, 'store']);
            Route::put('incidencias/{id}/resolver', [IncidenciaController::class, 'resolver']);
        });

        // =========================================================
        // 🏗️ ADMINISTRACIÓN Y CONFIGURACIÓN (SOLO SUPER_ADMIN / ADMIN)
        // =========================================================
       
        Route::middleware('jugadordeunbit:super_admin,admin')->group(function () {
            
            
            
            // Configuración de turnos vinculados por servicio
            Route::prefix('servicios')->group(function () {
                 
                 Route::post('vincular-turnos', [ServicioTurnoController::class, 'vincularTurnos']);
            });

            // Seguridad y Password
            Route::put('/usuarios/{id}/password', [UserController::class, 'updatePassword']);
            Route::post("/auth/update-initial-password", [AuthController::class, "updateFirstPassword"]);
            
            // Rutas para AdminAuthorization
            Route::post('/auth/generate-action-token', [AuthController::class, 'generateToken']);
            Route::post('/auth/verify-action-token', [AuthController::class, 'verifyToken']);
        });

        // --- Configuración de Calendario ---
        Route::get('/calendario/configuracion', function () {
            return [
                'gestiones' => \App\Models\Gestion::with('meses.semanas')->get(),
                'mes_actual' => (int)date('n'),
                'anio_actual' => (int)date('Y')
            ];
        });

    }); // Fin auth:sanctum
});