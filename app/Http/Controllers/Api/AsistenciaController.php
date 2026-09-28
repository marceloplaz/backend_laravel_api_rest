<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class AsistenciaController extends Controller
{
    /**
     * Endpoint API: Retorna el detalle diario de marcaciones para un usuario o rango.
     */
    /**
     * Endpoint para la tabla principal: Devuelve el historial plano día por día.
     */
    public function obtenerAsistenciaRango(Request $request)
{
    // 1. VALIDACIÓN DE INPUTS
    $validated = $request->validate([
        'ci'           => 'nullable|string',
        'usuario_id'   => 'nullable|string',
        'fecha_inicio' => 'required|date',
        'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
    ]);

    $inputCi     = $validated['ci'] ?? null;
    $usuarioId   = $validated['usuario_id'] ?? null;
    $fechaInicio = $validated['fecha_inicio'];
    $fechaFin    = $validated['fecha_fin'];

    // 2. AUTENTICACIÓN Y PERMISOS
    $usuarioAutenticado = $request->user();
    if (!$usuarioAutenticado) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    $permisos = $this->obtenerPermisosUsuario($usuarioAutenticado);
    if ($permisos['error']) {
        return response()->json(['status' => 'success', 'data' => []]);
    }

    // 3. TRADUCCIÓN DE BÚSQUEDA POR CI O USUARIO
    $ciObjetivo = null;
    if (!empty($inputCi)) {
        $ciObjetivo = trim((string)$inputCi);
    } elseif (!empty($usuarioId)) {
        $valorBusqueda = trim((string)$usuarioId);
        if (strlen($valorBusqueda) >= 5) {
            $ciObjetivo = $valorBusqueda;
        } else {
            $ciPersona = DB::table('personas')->where('user_id', $valorBusqueda)->value('carnet_identidad');
            $ciObjetivo = $ciPersona ? trim($ciPersona) : $valorBusqueda;
        }
    }

    // 4. CONSULTA DE MARCACIONES A SQL SERVER
    $queryAsistencia = DB::connection('sqlsrv_rrhh')
        ->table('V_asistencia_permisos')
        ->whereBetween('FechaAsistencia', [$fechaInicio, $fechaFin]);

    if ($ciObjetivo !== null) {
        $queryAsistencia->whereRaw("REPLACE(LTRIM(RTRIM(CI)), ' ', '') = ?", [trim((string)$ciObjetivo)]);
    } elseif (!$permisos['esSuperAdmin'] && !empty($permisos['cis'])) {
        $queryAsistencia->whereIn(DB::raw("REPLACE(LTRIM(RTRIM(CI)), ' ', '')"), $permisos['cis']);
    }

    $marcaciones = $queryAsistencia->orderBy('FechaAsistencia', 'ASC')->get();

    if ($marcaciones->isEmpty()) {
        return response()->json(['status' => 'success', 'data' => []]);
    }

    // 5. OBTENER TURNOS ASIGNADOS EN MYSQL (Incluyendo duracion_horas)
    $cisEncontrados = $marcaciones->pluck('CI')->filter()->map(fn($i) => trim((string)$i))->unique()->values()->toArray();
    $fechaInicioConsulta = Carbon::parse($fechaInicio)->subDay()->format('Y-m-d');

    $turnosProgramados = DB::table('turnos_asignados as ta')
        ->join('users as u', 'ta.usuario_id', '=', 'u.id')
        ->join('personas as p', 'p.user_id', '=', 'u.id')
        ->join('turnos as t', 'ta.turno_id', '=', 't.id')
        ->leftJoin('servicios as s', 'ta.servicio_id', '=', 's.id')
        ->whereIn(DB::raw('TRIM(p.carnet_identidad)'), $cisEncontrados)
        ->whereBetween('ta.fecha', [$fechaInicioConsulta, $fechaFin])
        ->select([
            'p.carnet_identidad as ci',
            'ta.fecha',
            't.nombre_turno',
            't.hora_inicio',
            't.hora_fin',
            't.duracion_horas', // <--- Se agrega duracion_horas para el cálculo matemático
            's.nombre as servicio_nombre'
        ])
        ->get()
        ->keyBy(fn($item) => trim((string)$item->ci) . '_' . Carbon::parse($item->fecha)->format('Y-m-d'));

    // 6. MAPEO A ESTRUCTURA PLANA DIARIA
    $resultado = $marcaciones->map(function ($item) use ($turnosProgramados) {
        $fechaActual = Carbon::parse($item->FechaAsistencia)->format('Y-m-d');
        $ciLimpio    = trim((string)$item->CI);

        $horaIngreso = $item->HoraIngreso ? Carbon::parse($item->HoraIngreso)->format('H:i:s') : null;
        $horaSalida  = $item->HoraSalida ? Carbon::parse($item->HoraSalida)->format('H:i:s') : null;

        if ($horaIngreso && $horaIngreso === $horaSalida) {
            $horaSalida = null;
        }

        // --- EVALUACIÓN MATEMÁTICA PURA PARA SALIDAS AL DÍA SIGUIENTE ---
        $fechaAnterior = Carbon::parse($fechaActual)->subDay()->format('Y-m-d');
        $turnoAnterior = $turnosProgramados[$ciLimpio . '_' . $fechaAnterior] ?? null;

        $esSalidaNocturna = false;
        $turnoEvaluado    = null;

        if ($turnoAnterior && $horaIngreso && !$horaSalida) {
            $hInicioAnt  = $turnoAnterior->hora_inicio ?? null;
            $hFinAnt     = $turnoAnterior->hora_fin ?? null;
            $duracionAnt = (float)($turnoAnterior->duracion_horas ?? 0);

            if ($hInicioAnt && $hFinAnt) {
                $iniCarbon = Carbon::parse($hInicioAnt);
                $finCarbon = Carbon::parse($hFinAnt);

                // REGLA MATEMÁTICA PURA:
                // 1. Cruza la medianoche (hora_fin <= hora_inicio)
                // 2. O tiene una duración >= 12 horas (Ej: 30 Hrs, 24 Hrs)
                $esTurnoExtendido = ($finCarbon->lessThanOrEqualTo($iniCarbon)) || ($duracionAnt >= 12);

                if ($esTurnoExtendido) {
                    $salidaOficialProyectada = Carbon::parse($fechaActual . ' ' . $hFinAnt);
                    $punchCarbon             = Carbon::parse($fechaActual . ' ' . $horaIngreso);

                    // Ventana amplia de salida al día siguiente:
                    // Desde 6 horas antes de la hora oficial (ej. 06:00 AM para salidas de 12:00 PM)
                    // hasta 4 horas después (16:00 PM)
                    $limiteInferior = (clone $salidaOficialProyectada)->subHours(6);
                    $limiteSuperior = (clone $salidaOficialProyectada)->addHours(4);

                    if ($punchCarbon->between($limiteInferior, $limiteSuperior)) {
                        $horaSalida       = $horaIngreso;
                        $horaIngreso      = null; // Se desasigna de hoy para no generar retraso falso
                        $esSalidaNocturna = true;
                        $turnoEvaluado    = $turnoAnterior;
                    }
                }
            }
        }

        $turnoActual      = $turnosProgramados[$ciLimpio . '_' . $fechaActual] ?? null;
        $turnoParaCalculo = $esSalidaNocturna ? $turnoEvaluado : $turnoActual;

        $retrasoMinutos     = 0;
        $salidaTemprana     = false;
        $minutosTemprano    = 0;
        $horaEntradaOficial = null;
        $horaSalidaOficial  = null;
        $servicioNombre     = 'Sin servicio asignado';

        if ($turnoParaCalculo) {
            $horaEntradaOficial = $turnoParaCalculo->hora_inicio ?? null;
            $horaSalidaOficial  = $turnoParaCalculo->hora_fin ?? null;
            $servicioNombre     = $turnoParaCalculo->servicio_nombre ?? 'General';
            $duracionCalculo    = (float)($turnoParaCalculo->duracion_horas ?? 0);

            // Evaluación de retraso en el ingreso
            if ($horaIngreso && $horaEntradaOficial) {
                $ingresoCarbon        = Carbon::parse($fechaActual . ' ' . $horaIngreso);
                $entradaOficialCarbon = Carbon::parse($fechaActual . ' ' . $horaEntradaOficial);
                $limiteTolerancia     = (clone $entradaOficialCarbon)->addMinutes(5)->endOfMinute();

                if ($ingresoCarbon->greaterThan($limiteTolerancia)) {
                    $retrasoMinutos = (int) floor((clone $entradaOficialCarbon)->addMinutes(5)->diffInMinutes($ingresoCarbon, true));
                }
            }

            // Evaluación de salida temprana
            if ($horaSalida && $horaSalidaOficial) {
                $salidaCarbon        = Carbon::parse($fechaActual . ' ' . $horaSalida);
                $salidaOficialCarbon = Carbon::parse($fechaActual . ' ' . $horaSalidaOficial);

                // Para guardias largas (duración >= 18h), si salen a partir de las 09:00 AM no se penaliza como salida temprana
                $esGuardiaLarga = $duracionCalculo >= 18;
                $saleDesde09AM  = $salidaCarbon->gte(Carbon::parse($fechaActual . ' 09:00:00'));

                if ($salidaCarbon->lessThan($salidaOficialCarbon) && !($esGuardiaLarga && $saleDesde09AM)) {
                    $salidaTemprana  = true;
                    $minutosTemprano = (int) round($salidaCarbon->diffInMinutes($salidaOficialCarbon, true), 0);
                }
            }
        }

        $estadoTexto = 'A tiempo';
        if ($retrasoMinutos > 0) {
            $estadoTexto = $retrasoMinutos . ' min de retraso';
        } elseif ($salidaTemprana) {
            $estadoTexto = 'Salida antes de hora (' . $minutosTemprano . ' min)';
        }

        return [
            'codigo_personal'  => $item->pperCodPer ?? null,
            'id_interno'       => $item->pperIdePer ?? null,
            'ci'               => $ciLimpio,
            'celular'          => $item->pperTelCel ?? null,
            'nombre_completo'  => $item->NombreCompleto ?? null,
            'fecha_asistencia' => $item->FechaAsistencia,
            'hora_ingreso'     => $horaIngreso,
            'hora_salida'      => $horaSalida,
            'tipo_codigo'      => $item->TipoCodigo ?? null,
            'nombre_permiso'   => $item->NombrePermiso ?? null,
            'permiso_inicio'   => $item->PermisoFechaInicio ?? null,
            'permiso_fin'      => $item->PermisoFechaFin ?? null,
            'duracion'         => $item->Duracion ?? null,
            'gestiones'        => $item->Gestiones ?? null,
            'minutos_retraso'  => $retrasoMinutos,
            'tiene_retraso'    => $retrasoMinutos > 0,
            'salida_temprana'  => $salidaTemprana,
            'minutos_temprano' => $minutosTemprano,
            'estado_texto'     => $estadoTexto,
            'turno_programado' => [
                'nombre'      => $turnoActual->nombre_turno ?? 'Sin Turno Asignado',
                'servicio'    => $servicioNombre,
                'hora_inicio' => $horaEntradaOficial ? Carbon::parse($horaEntradaOficial)->format('H:i') : null,
                'hora_fin'    => $horaSalidaOficial ? Carbon::parse($horaSalidaOficial)->format('H:i') : null,
            ]
        ];
    })->values();

    return response()->json(['status' => 'success', 'data' => $resultado]);
} /**
     * Endpoint API: Retorna la matriz/planilla consolidada en formato JSON para Angular o exportación Excel.
     */
    public function obtenerMatrizAsistencia(Request $request)
    {
        $data = $this->procesarPlanillaAsistencia($request);
        if (isset($data['error'])) {
            return response()->json(['status' => 'error', 'message' => $data['error']], $data['code'] ?? 400);
        }

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Endpoint PDF: Renderiza la planilla consolidada en PDF.
     */
    public function generarMatrizPdf(Request $request)
    {
        // Incrementar recursos para renderizado DomPDF de tablas extensas
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $data = $this->procesarPlanillaAsistencia($request);
        if (isset($data['error'])) {
            return response()->json(['message' => $data['error']], $data['code'] ?? 400);
        }

        $pdf = Pdf::loadView('pdf.matriz_asistencia_hospital', $data);
        $pdf->setPaper('letter', 'landscape');

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="planilla_asistencia_' . $data['fecha_inicio'] . '.pdf"'
        ]);
    }
    // =========================================================================
    // LÓGICA CENTRALIZADA Y MÉTODOS PRIVADOS
    // =========================================================================

    /**
     * Método Privado Principal: Procesa la asistencia, sanciones R.I.P. y novedades laborales.
     */
    private function procesarPlanillaAsistencia(Request $request): array
    {
        // 1. Validación de inputs
        $validated = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'categoria_id' => 'nullable|integer',
            'ci'           => 'nullable|string',
            'usuario_id'   => 'nullable|string',
        ]);

        $fechaInicio = $validated['fecha_inicio'];
        $fechaFin    = $validated['fecha_fin'];

        // 2. Permisos y obtención de personal
        $permisos = $this->obtenerPermisosUsuario($request->user());
        if ($permisos['error']) {
            return ['error' => $permisos['error'], 'code' => $permisos['code']];
        }

        $empleados = $this->obtenerPersonal($validated, $permisos);
        if ($empleados->isEmpty()) {
            return ['error' => 'No se encontraron registros para los filtros seleccionados.', 'code' => 404];
        }

        $cis = $empleados->pluck('ci')->map(fn($i) => trim((string)$i))->filter()->unique()->values()->toArray();
        if (empty($cis)) {
            return ['error' => 'El personal seleccionado no cuenta con C.I. válido.', 'code' => 422];
        }

        // 3. Consulta de marcaciones en SQL Server
        $marcacionesSql = DB::connection('sqlsrv_rrhh')
            ->table('V_asistencia_permisos')
            ->whereBetween('FechaAsistencia', [$fechaInicio, $fechaFin])
            ->whereIn(DB::raw("REPLACE(LTRIM(RTRIM(CI)), ' ', '')"), $cis)
            ->get()
            ->groupBy(fn($item) => trim((string)$item->CI) . '_' . Carbon::parse($item->FechaAsistencia)->format('Y-m-d'));

        // 4. Consulta de turnos asignados en MySQL
        $turnosAsignados = DB::table('turnos_asignados as ta')
            ->join('turnos as t', 'ta.turno_id', '=', 't.id')
            ->whereIn('ta.usuario_id', $empleados->pluck('usuario_id'))
            ->whereBetween('ta.fecha', [$fechaInicio, $fechaFin])
            ->select(['ta.usuario_id', 'ta.fecha', 't.nombre_turno', 't.hora_inicio', 't.hora_fin', 't.duracion_horas'])
            ->get()
            ->groupBy('usuario_id');

        // 5. Rango de fechas
        $fechasRango = [];
        $curr = Carbon::parse($fechaInicio);
        $fin  = Carbon::parse($fechaFin);
        while ($curr->lte($fin)) {
            $fechasRango[] = $curr->format('Y-m-d');
            $curr->addDay();
        }

        // 6. Procesamiento por empleado
        $matrizEmpleados = [];

        foreach ($empleados as $emp) {
            $ciLimpio = trim((string)$emp->ci);

            $partesNombre = array_values(array_filter(explode(' ', trim($emp->nombre_completo ?? ''))));
            $paterno = $partesNombre[0] ?? '';
            $materno = $partesNombre[1] ?? '';
            $nombres = count($partesNombre) > 2 ? implode(' ', array_slice($partesNombre, 2)) : ($partesNombre[0] ?? '');

            $fechaIngresoFmt = !empty($emp->fecha_ingreso) 
                ? Carbon::parse($emp->fecha_ingreso)->format('d/m/Y') 
                : '';

            $userTurnos = $turnosAsignados[$emp->usuario_id] ?? collect();
            $turnosPorFecha = $userTurnos->keyBy(fn($t) => Carbon::parse($t->fecha)->format('Y-m-d'));

            $faltasCount         = 0;
            $totalMinutosAtraso  = 0;
            $totalHorasMes       = 0;
            $abandonoCount       = 0;
            $omisionMarcadoCount = 0;

            $diasEfectTrabajados = 0;
            $diasBajaMedica      = 0;
            $diasLicencia        = 0;
            $diasVacacion        = 0;
            $diasComision        = 0;
            $diasFeriado         = 0;
            $diasFinSemana       = 0;

            $diasDetalle         = [];
            $observacionesList   = [];

            foreach ($fechasRango as $fec) {
                $carbonFecha    = Carbon::parse($fec);
                $key            = $ciLimpio . '_' . $fec;
                $marcacionesDia = $marcacionesSql[$key] ?? collect();
                $marcacion      = $marcacionesDia->first();
                $turnoDia       = $turnosPorFecha[$fec] ?? null;

                $horaIngreso   = $marcacion->HoraIngreso ?? null;
                $horaSalida    = $marcacion->HoraSalida ?? null;
                $permisoNombre = $marcacion->NombrePermiso ?? null;

                $esFinDeSemana = $carbonFecha->isWeekend();
                $retrasoMin    = 0;

                if ($permisoNombre) {
                    $permisoUpper = strtoupper($permisoNombre);
                    $fechaFmt = $carbonFecha->format('d/m/Y');
                    $observacionesList[] = "{$fechaFmt}: {$permisoNombre}";

                    if (str_contains($permisoUpper, 'MEDICA') || str_contains($permisoUpper, 'BAJA')) {
                        $diasBajaMedica++;
                    } elseif (str_contains($permisoUpper, 'VACACION')) {
                        $diasVacacion++;
                    } elseif (str_contains($permisoUpper, 'COMISION')) {
                        $diasComision++;
                    } elseif (str_contains($permisoUpper, 'FERIADO')) {
                        $diasFeriado++;
                    } else {
                        $diasLicencia++;
                    }
                }

                // Cálculo del estado para la celda individual de la matriz PDF
                $estadoDia = '';
                if ($turnoDia) {
                    $duracionHoras = (float)($turnoDia->duracion_horas ?? 8);

                    if ($horaIngreso || $horaSalida) {
                        $diasEfectTrabajados++;
                        $totalHorasMes += $duracionHoras;
                        $estadoDia = $duracionHoras;

                        if (($horaIngreso && !$horaSalida) || (!$horaIngreso && $horaSalida)) {
                            $omisionMarcadoCount++;
                        }

                        if ($horaIngreso) {
                            $ingresoCarbon        = Carbon::parse($fec . ' ' . $horaIngreso);
                            $entradaOficialCarbon = Carbon::parse($fec . ' ' . $turnoDia->hora_inicio);
                            $limiteTolerancia     = (clone $entradaOficialCarbon)->addMinutes(5)->endOfMinute();

                            if ($ingresoCarbon->greaterThan($limiteTolerancia)) {
                                $retrasoMin = (int) floor((clone $entradaOficialCarbon)->addMinutes(5)->diffInMinutes($ingresoCarbon, true));
                                $totalMinutosAtraso += $retrasoMin;
                            }
                        }
                    } else {
                        $estadoDia = $permisoNombre ? 'PER' : 'F';
                        if (!$permisoNombre) {
                            $faltasCount++;
                        }
                    }
                } elseif ($horaIngreso || $horaSalida) {
                    $diasEfectTrabajados++;
                    $estadoDia = 'P';
                } elseif ($permisoNombre) {
                    $estadoDia = 'PER';
                } elseif ($esFinDeSemana) {
                    $diasFinSemana++;
                }

                $diasDetalle[$fec] = [
                    'estado'          => $estadoDia,
                    'hora_ingreso'    => $horaIngreso,
                    'hora_salida'     => $horaSalida,
                    'minutos_retraso' => $retrasoMin,
                    'permiso'         => $permisoNombre
                ];
            }

            $totalDiasMes       = $diasEfectTrabajados + $faltasCount + $diasBajaMedica + $diasLicencia + $diasVacacion + $diasComision + $diasFeriado + $diasFinSemana;
            $totalDiasDescontar = $faltasCount + floor($totalMinutosAtraso / 120);

            $obsTexto = implode('; ', array_unique($observacionesList));
            if ($totalMinutosAtraso > 0) {
                $obsTexto = trim("Suma {$totalMinutosAtraso} min de retraso. " . $obsTexto);
            }

            $matrizEmpleados[] = [
                // Datos del Empleado
                'item'                  => $emp->item ?? '0',
                'carga_horaria'         => 'T/C',
                'fecha_ingreso'         => $fechaIngresoFmt,
                'ci'                    => $ciLimpio,
                'cargo'                 => $emp->cargo ?? 'ADMINISTRATIVO',
                'tipo_salario'          => $emp->tipo_salario ?? 'TGN',
                'lugar_trabajo'         => 'HRSJDD',
                'apellido_paterno'      => $paterno,
                'apellido_materno'      => $materno,
                'nombres'               => $nombres,
                'nombre_completo'       => $emp->nombre_completo,

                // Sanciones y Totales (compatibles con Excel y PDF Blade)
                'faltas'                => $faltasCount,
                'minutos_retraso'       => $totalMinutosAtraso,
                'total_minutos_atraso'  => $totalMinutosAtraso,
                'abandono'              => $abandonoCount,
                'omision_marcado'       => $omisionMarcadoCount,
                'total_dias_descontar'  => $totalDiasDescontar,

                // Novedades Laborales
                'dias_efect_trabajados' => $diasEfectTrabajados,
                'dias_falta'            => $faltasCount,
                'dias_baja_medica'      => $diasBajaMedica,
                'dias_licencia'         => $diasLicencia,
                'dias_vacacion'         => $diasVacacion,
                'dias_comision'         => $diasComision,
                'dias_feriado'          => $diasFeriado,
                'dias_fin_semana'       => $diasFinSemana,
                'total_dias_mes'        => $totalDiasMes,
                'total_horas_mes'       => $totalHorasMes,

                // Observaciones (para Excel y PDF)
                'observacion'           => $obsTexto,
                'observaciones'         => $obsTexto,

                // Días (para Excel y PDF)
                'dias'                  => $diasDetalle,
                'dias_detalle'          => $diasDetalle
            ];
        }

        $nombreCat = $this->obtenerNombreCategoria($request->get('categoria_id'));

        // Retorno unificado con alias en camelCase y snake_case para Blade
        return [
            'fecha_inicio'     => $fechaInicio,
            'fechaInicio'      => $fechaInicio,
            'fecha_fin'        => $fechaFin,
            'fechaFin'         => $fechaFin,
            'nombre_categoria' => $nombreCat,
            'nombreCategoria'  => $nombreCat,
            'fechas_rango'     => $fechasRango,
            'fechasRango'      => $fechasRango,
            'empleados'        => $matrizEmpleados,
            'matrizEmpleados'  => $matrizEmpleados,
        ];
}
    /**
     * Helper Privado: Extrae los permisos del usuario autenticado en una sola consulta.
     */
    private function obtenerPermisosUsuario($usuario): array
    {
        if (!$usuario) {
            return ['error' => 'Unauthenticated.', 'code' => 401, 'esSuperAdmin' => false];
        }

        $esSuperAdmin = method_exists($usuario, 'hasAnyRole') ? $usuario->hasAnyRole(['super_admin', 'admin']) : false;
        if ($esSuperAdmin) {
            return ['error' => null, 'esSuperAdmin' => true, 'categorias' => [], 'servicios' => [], 'cis' => []];
        }

        $roleIds = DB::table('role_user')->where('user_id', $usuario->id)->pluck('role_id')->toArray();

        $categorias = DB::table('categoria_role')->whereIn('role_id', $roleIds)->pluck('categoria_id')->toArray();
        if (!empty($usuario->categoria_id)) {
            $categorias[] = $usuario->categoria_id;
        }
        $categorias = array_unique($categorias);

        $serviciosPorRol = DB::table('role_servicio')->whereIn('role_id', $roleIds)->pluck('servicio_id')->toArray();
        $serviciosPorUsuario = DB::table('usuario_servicios')->where('usuario_id', $usuario->id)->pluck('servicio_id')->toArray();
        $servicios = array_unique(array_merge($serviciosPorRol, $serviciosPorUsuario));

        if (empty($servicios) && empty($categorias)) {
            return ['error' => 'No cuenta con permisos o categorías asignadas.', 'code' => 403, 'esSuperAdmin' => false];
        }

        return [
            'error'        => null,
            'esSuperAdmin' => false,
            'categorias'   => $categorias,
            'servicios'    => $servicios,
        ];
    }

    /**
     * Helper Privado: Construye la consulta de personal usando la estructura real de la tabla personas.
     */
    /**
     * Helper Privado: Consulta la información del personal seleccionando las columnas reales de la DB.
     */
    private function obtenerPersonal(array $validated, array $permisos)
    {
        $categoriaId = $validated['categoria_id'] ?? null;
        $ciInput     = $validated['ci'] ?? null;

        $query = DB::table('users as u')
            ->join('personas as p', 'p.user_id', '=', 'u.id')
            ->leftJoin('categorias as c', 'u.categoria_id', '=', 'c.id')
            ->whereNotNull('p.carnet_identidad');

        if ($ciInput) {
            $query->where(DB::raw("REPLACE(LTRIM(RTRIM(p.carnet_identidad)), ' ', '')"), trim($ciInput));
        }

        if ($categoriaId) {
            $query->where('u.categoria_id', $categoriaId);
        } elseif (!$permisos['esSuperAdmin'] && !empty($permisos['categorias'])) {
            $query->whereIn('u.categoria_id', $permisos['categorias']);
        }

        return $query->select([
            'u.id as usuario_id',
            'p.carnet_identidad as ci',
            'p.id as codigo_personal',
            'p.nombre_completo',
            'p.numero_tipo_salario as item',
            'p.fecha_ingreso_institucion as fecha_ingreso',
            'p.tipo_trabajador as cargo',
            'p.tipo_salario',
            'c.nombre as categoria_nombre'
        ])->orderBy('p.nombre_completo', 'ASC')->get();
    }
    /**
     * Helper Privado: Obtiene el nombre de la categoría seleccionada.
     */
    private function obtenerNombreCategoria($categoriaId): string
    {
        if (!$categoriaId) {
            return 'TODAS LAS CATEGORÍAS';
        }
        return DB::table('categorias')->where('id', $categoriaId)->value('nombre') ?? 'CATEGORÍA SELECCIONADA';
    }
}