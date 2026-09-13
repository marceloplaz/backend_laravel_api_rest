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
use App\Http\Controllers\Api\VacacionController;
use App\Http\Controllers\Api\NovedadLaboralController;
use App\Http\Controllers\Api\KardexVacacionController;
use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\SemanaController;
use App\Http\Controllers\Api\GestionController;
use App\Http\Controllers\Api\ComidaController;
use App\Http\Controllers\Api\AsistenciaController;

Route::prefix("v1")->group(function () {
    Route::post("/auth/login", [AuthController::class, "funLogin"])->middleware('throttle:5,1');
    Route::post("/auth/register", [AuthController::class, "funRegister"]);
    Route::get('buscar-profesionales', [UserController::class, 'index']); 
    Route::get('/usuarios/reporte-turnos', [PersonaController::class, 'generarMatrizTurnos']);
    Route::get('personal/exportar-pdf', [PersonaController::class, 'exportarPdf']);
    Route::post('/personal/importar', [PersonaController::class, 'import']);
    Route::get('reporte-mensual', [TurnoController::class, 'reporteMensual']);
    Route::post('/actualizar-estado', [ServicioController::class, 'actualizarEstadoVinculacion']);
    Route::get('vacaciones/saldos-masivos', [VacacionController::class, 'obtenerSaldosMasivos']);
    Route::post('vacaciones/inicializar-personal', [VacacionController::class, 'inicializarPersonalReal']); 
    Route::put('vacaciones/programar/{id}', [VacacionController::class, 'programarFechas']);
    Route::get('vacaciones/pendientes', [VacacionController::class, 'indexPendientes']);
    Route::get('vacaciones/general', [VacacionController::class, 'indexGeneral']);    
    Route::get('vacaciones/usuario/{id}', [VacacionController::class, 'indexByUsuario']);
    Route::put('vacaciones/{id}/aprobar', [VacacionController::class, 'aprobar']);
    Route::put('vacaciones/{id}/estado', [VacacionController::class, 'actualizarEstado']);
    Route::get('/comidas', [ComidaController::class, 'index']);
   

Route::prefix('vacaciones/kardex')->group(function () {
    Route::get('historial/{user_id}', [KardexVacacionController::class, 'mostrarHistorial']);
    Route::post('/', [KardexVacacionController::class, 'store']);
    Route::put('{id}', [KardexVacacionController::class, 'update']);
    Route::delete('{id}', [KardexVacacionController::class, 'destroy']);
  });
    
    Route::get('gestiones', function() {
        return response()->json(\App\Models\Gestion::all());
    });

    Route::get('servicios-lista', [ServicioController::class, 'index']); 
    Route::get('categorias-lista', [CategoriaController::class, 'index']);
    Route::middleware('auth:sanctum')->group(function () {
    Route::get('/asistencia/reporte-rango', [AsistenciaController::class, 'obtenerAsistenciaRango']);
    $ROLES_ADMIN_FULL = 'super_admin,admin,admin_jefe_medico,admin_jefa_enfermeras,admin_jefa_servicios_generales,jefa_enfermeras';
    $ROLES_JEFATURAS  = $ROLES_ADMIN_FULL . ',jefe_medico_servicio,jefa_enfermeras_servicio,jefe_servicio';
    $ROLES_TURNOS     = $ROLES_JEFATURAS  . ',jefa_enfermeras_servicio'; 
    $ROLES_TECNICO    = $ROLES_JEFATURAS  . ',responsable_tecnico';
    //rutas segun roles 
    Route::prefix('areas')->middleware('role:super_admin|admin')->group(function () {
    Route::get('servicio/{servicioId}', [AreaController::class, 'getPorServicio']);
    Route::post('/guardar', [AreaController::class, 'store']);
    Route::delete('/{id}', [AreaController::class, 'destroy']); });

    Route::prefix('turnos-asignados')->group(function () {
    Route::put('/cambiar-bloqueo', [TurnoAsignadoController::class, 'cambiarBloqueoRol'])
                ->middleware('role:super_admin|admin');
    });
    // El reporte mensual también apuntando a TurnoAsignadoController
    Route::get('/acciones-reporte/mensual-pdf', [TurnoAsignadoController::class, 'obtenerPdfReporteMensual']);
    Route::get("/auth/profile", [AuthController::class, "funprofile"]);
    Route::post("/auth/logout", [AuthController::class, "funlogout"]);
    Route::get('persona/{id}', [PersonaController::class, 'show']);
    Route::get('servicios/inicio', [ServicioController::class, 'inicio']);
    Route::get('turnos/mis-turnos', [TurnoController::class, 'misTurnosMes']);
    Route::prefix('reportes')->group(function () {

        Route::get('reporte-semanal/{semana_id}/{usuario_id?}', [TurnoController::class, 'reporteHorasSemana']);
});
        // Consultas Base del Dashboard 
        Route::get("/roles", [UserController::class, "getRoles"]);
        Route::get("/categorias-lista", [TurnoAsignadoController::class, "listaCategorias"]);
        Route::get("/filtros-jerarquia", [TurnoController::class, "getFiltrosPorJerarquia"]);
        Route::get("/lista-turnos-disponibles", [TurnoController::class, "index"]);
        Route::get('servicios/{id}/turnos-habilitados', [ServicioTurnoController::class, 'getTurnosHabilitados']);
        Route::get("/mis-turnos", [TurnoAsignadoController::class, "misTurnos"]);
        Route::get("/equipo-filtrado", [TurnoAsignadoController::class, "getEquipoFiltrado"]);
        Route::get('/usuarios/{id}/servicios', [TurnoController::class, 'getServiciosUsuario']);
        Route::get('/turnos/mis-turnos', [TurnoController::class, 'misTurnosMes']);
        // rutas de vacaciones
        Route::post('vacaciones', [VacacionController::class, 'store']);
        Route::get('vacaciones/usuario/{id}', [VacacionController::class, 'indexByUsuario']);
        Route::put('vacaciones/{id}/aprobar', [VacacionController::class, 'aprobar']);
        Route::put('vacaciones/{id}/estado', [VacacionController::class, 'actualizarEstado']);
        // GESTIÓN DE SERVICIOS Y PERSONAL (ROLES_JEFATURAS)
        Route::middleware("jugadordeunbit:{$ROLES_JEFATURAS}")->group(function () {
        Route::prefix('reportes')->group(function () {
        Route::get('semanal/{semana_id}', [TurnoAsignadoController::class, 'reporteSemanal']);
        Route::get('mensual/{mes_id}', [TurnoAsignadoController::class, 'reporteMensual']);
        Route::get('turnos/resumen-mensual', [TurnoAsignadoController::class, 'getResumenMensual']);

        
        
        }); 
        // ruta catalogos nos envias todos sus datos y turnos
        Route::get('persona-catalogos', [PersonaController::class, 'getFormDependencies']); 
        
        Route::get('/persona/{id}/reporte-alimentacion', [PersonaController::class, 'reporteAlimentacionPersona']); 
        
        Route::get('/reporte/{mes_id}', [TurnoAsignadoController::class, 'reporteMensual'])
        
        ->middleware('jugadordeunbit:ver_reportes');
        //en el resource guarda varios get-enpoint
        Route::apiResource('usuarios', UserController::class); 
        Route::apiResource('turnos', TurnoController::class);
        Route::get('servicios/{id}', [ServicioController::class, 'show']);
        Route::apiResource('servicios', ServicioController::class); 
        Route::get('areas', [ServicioController::class, 'getAreas']);
        Route::delete('usuario-servicio/servicio/{servicio_id}/usuario/{usuario_id}', [UsuarioServicioController::class, 'destroyByRelation']);
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
        //  PLANIFICACIÓN DE TURNOS Y NOVEDADES (ROLES_TURNOS)
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
        Route::post('/{novedad}/confirmar-devolucion', [NovedadLaboralController::class, 'confirmar']);
        Route::put('/{id}/devolver', [NovedadLaboralController::class, 'marcarDevolucion']);
        });
        });
       // REPORTES Y VISUALIZACIÓN
       Route::get('/servicio/{servicioId}/equipo', [TurnoAsignadoController::class, 'verTurnosPorJerarquia'])
       ->middleware('jugadordeunbit:ver_equipo');
       // GESTIÓN DE INCIDENCIAS TECNICAS 
       Route::get('incidencias', [IncidenciaController::class, 'index']);
       Route::middleware("jugadordeunbit:{$ROLES_TECNICO}")->group(function () {
       Route::post('incidencias', [IncidenciaController::class, 'store']);
       Route::put('incidencias/{id}/resolver', [IncidenciaController::class, 'resolver']);
        });
       // CONFIGURACIÓN SOLO SUPER_ADMIN / ADMIN
       Route::middleware('jugadordeunbit:super_admin,admin')->group(function () {
       Route::prefix('gestion-accesos')->group(function () {
       Route::get('inicializar', [App\Http\Controllers\Api\RoleController::class, 'getDatosIniciales']);
       Route::get('buscar-empleado', [App\Http\Controllers\Api\UserController::class, 'buscarParaAsignacion']);
       Route::post('guardar-matriz', [App\Http\Controllers\Api\RoleController::class, 'guardarMatrizAccesos']);
       // --- RUTA DE GESTION
       Route::get('gestiones', [GestionController::class, 'index']);
       Route::put('semanas/{id}', [\App\Http\Controllers\Api\SemanaController::class, 'update']);
       Route::get('semanas-por-mes', [\App\Http\Controllers\Api\SemanaController::class, 'getSemanasPorMesYCategoria']);
       Route::post('generar-semanas', [App\Http\Controllers\Api\SemanaController::class, 'generarSemanas']);
       Route::get('meses', function() { return response()->json(\App\Models\Mes::all()); });
       Route::get('categorias', [CategoriaController::class, 'index']);
    });
});
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
       //Configuración de Calendario
       Route::get('/calendario/configuracion', function () {
       return [
       'gestiones' => \App\Models\Gestion::with('meses.semanas')->get(),
       'mes_actual' => (int)date('n'),
       'anio_actual' => (int)date('Y')
            ];
        });

    }); 