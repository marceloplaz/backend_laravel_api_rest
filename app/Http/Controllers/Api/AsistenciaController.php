<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class AsistenciaController extends Controller
{
    /**
     * Endpoint API: Retorna el detalle diario de marcaciones para un usuario o rango (PAGINADO Y CACHEADO).
     */
    public function obtenerAsistenciaRango(Request $request)
    {
        $validated = $request->validate([
            'ci'           => 'nullable|string',
            'usuario_id'   => 'nullable|string',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'per_page'     => 'nullable|integer|min:1|max:100',
            'page'         => 'nullable|integer|min:1',
            'categoria_id' => 'nullable|integer',
        ]);

        $inputCi     = $validated['ci'] ?? null;
        $usuarioId   = $validated['usuario_id'] ?? null;
        $categoriaId = $validated['categoria_id'] ?? null;
        $fechaInicio = $validated['fecha_inicio'];
        $fechaFin    = $validated['fecha_fin'];
        $perPage     = (int) ($validated['per_page'] ?? 15);
        $page        = (int) ($validated['page'] ?? 1);

        $usuarioAutenticado = $request->user();
        if (!$usuarioAutenticado) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $permisos = $this->obtenerPermisosUsuario($usuarioAutenticado);
        if ($permisos['error']) {
            return response()->json(['status' => 'success', 'data' => []]);
        }

        // 1. Clave única de caché que incluye al usuario autenticado (para respetar permisos) y los filtros
        $ciKey   = $inputCi ? trim($inputCi) : 'todos';
        $usrKey  = $usuarioId ? trim($usuarioId) : 'todos';
        $catKey  = $categoriaId ?? 'todas';
        $userAuthId = $usuarioAutenticado->id;

        $cacheKey = "asistencia_rango_u{$userAuthId}_ci{$ciKey}_usr{$usrKey}_cat{$catKey}_{$fechaInicio}_{$fechaFin}";

        // 2. Ejecutar la consulta pesada y el cálculo de turnos dentro de la caché por 10 minutos
        $todosLosRegistros = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($validated, $permisos, $fechaInicio, $fechaFin, $inputCi, $usuarioId) {
            
            // Obtener el personal autorizado según roles/permisos
            $empleadosPermitidos = $this->obtenerPersonal($validated, $permisos);
            if ($empleadosPermitidos->isEmpty()) {
                return [];
            }

            $cisPermitidos = $empleadosPermitidos->pluck('ci')
                ->map(fn($i) => trim((string)$i))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

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

            // Consulta a SQL Server de TODOS los registros del rango (sin paginar aún)
            $queryAsistencia = DB::connection('sqlsrv_rrhh')
                ->table('V_asistencia_permisos')
                ->whereBetween('FechaAsistencia', [$fechaInicio, $fechaFin]);

            if ($ciObjetivo !== null) {
                $queryAsistencia->whereRaw("REPLACE(LTRIM(RTRIM(CI)), ' ', '') = ?", [$ciObjetivo]);
            } elseif (!$permisos['esSuperAdmin']) {
                $queryAsistencia->whereIn(DB::raw("REPLACE(LTRIM(RTRIM(CI)), ' ', '')"), $cisPermitidos);
            }

            $marcaciones = $queryAsistencia->orderBy('FechaAsistencia', 'ASC')->get();

            if ($marcaciones->isEmpty()) {
                return [];
            }

            // Extraer todos los CIs involucrados para consultar sus turnos asignados
            $cisEncontrados = $marcaciones->pluck('CI')
                ->filter()
                ->map(fn($i) => trim((string)$i))
                ->unique()
                ->values()
                ->toArray();

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
                    't.duracion_horas',
                    's.nombre as servicio_nombre'
                ])
                ->get()
                ->keyBy(fn($item) => trim((string)$item->ci) . '_' . Carbon::parse($item->fecha)->format('Y-m-d'));

            // Transformar y procesar todos los ítems
            return $marcaciones->map(function ($item) use ($turnosProgramados) {
                $fechaActual = Carbon::parse($item->FechaAsistencia)->format('Y-m-d');
                $ciLimpio    = trim((string)$item->CI);

                $horaIngreso = $item->HoraIngreso ? Carbon::parse($item->HoraIngreso)->format('H:i:s') : null;
                $horaSalida  = $item->HoraSalida ? Carbon::parse($item->HoraSalida)->format('H:i:s') : null;

                if ($horaIngreso && $horaIngreso === $horaSalida) {
                    $horaSalida = null;
                }

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

                        $esTurnoExtendido = ($finCarbon->lessThanOrEqualTo($iniCarbon)) || ($duracionAnt >= 12);

                        if ($esTurnoExtendido) {
                            $salidaOficialProyectada = Carbon::parse($fechaActual . ' ' . $hFinAnt);
                            $punchCarbon             = Carbon::parse($fechaActual . ' ' . $horaIngreso);

                            $limiteInferior = (clone $salidaOficialProyectada)->subHours(6);
                            $limiteSuperior = (clone $salidaOficialProyectada)->addHours(4);

                            if ($punchCarbon->between($limiteInferior, $limiteSuperior)) {
                                $horaSalida       = $horaIngreso;
                                $horaIngreso      = null;
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

                   if ($horaIngreso && $horaEntradaOficial) {
    $ingresoCarbon        = Carbon::parse($fechaActual . ' ' . $horaIngreso);
    $entradaOficialCarbon = Carbon::parse($fechaActual . ' ' . $horaEntradaOficial);
    $limiteTolerancia     = (clone $entradaOficialCarbon)->addMinutes(5);

    if ($ingresoCarbon->greaterThan($limiteTolerancia)) {
        $retrasoMinutos = (int) floor($limiteTolerancia->diffInMinutes($ingresoCarbon, true));
    } else {
        $retrasoMinutos = 0;
    }
}

                    if ($horaSalida && $horaSalidaOficial) {
                        $salidaCarbon        = Carbon::parse($fechaActual . ' ' . $horaSalida);
                        $salidaOficialCarbon = Carbon::parse($fechaActual . ' ' . $horaSalidaOficial);

                        $esGuardiaLarga = $duracionCalculo >= 18;
                        $saleDesde09AM  = $salidaCarbon->gte(Carbon::parse($fechaActual . ' 00:09:00'));

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
                    
                    'feriado_descripcion' => $item->DescripcionFeriadoTolerancia ?? 'Laboral',
                    'tipo_jornada'        => $item->TipoJornadaEspecial ?? 'Laboral',
                    'alcance_codigo'      => $item->AlcanceCodigo ?? null,
                    'alcance_descripcion' => $item->AlcanceDescripcion ?? 'N/A',


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
            })->all();
        });

        // 3. Paginar la colección de datos almacenados en caché
        $coleccion = collect($todosLosRegistros);
        $total     = $coleccion->count();

        $registrosPagina = $coleccion->slice(($page - 1) * $perPage, $perPage)->values();

        // 4. Instanciar LengthAwarePaginator para mantener exactamente la misma estructura JSON que Angular espera (current_page, data, total, per_page, etc.)
        $paginador = new LengthAwarePaginator(
            $registrosPagina,
            $total,
            $perPage,
            $page,
            [
                'path'  => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]
        );

        return response()->json([
            'status' => 'success',
            'data'   => $paginador
        ]);
    }

    public function obtenerMatrizAsistencia(Request $request)
    {
        $data = $this->procesarPlanillaAsistencia($request);
        if (isset($data['error'])) {
            return response()->json(['status' => 'error', 'message' => $data['error']], $data['code'] ?? 400);
        }

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function generarMatrizPdf(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);

        $data = $this->procesarPlanillaAsistencia($request);
        if (isset($data['error'])) {
            return response()->json(['message' => $data['error']], $data['code'] ?? 400);
        }

        $pdf = Pdf::loadView('pdf.matriz_asistencia_hospital', $data)
            ->setPaper('letter', 'landscape')
            ->setOption([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
            ]);

        $filename = 'planilla_asistencia_' . $data['fecha_inicio'] . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function procesarPlanillaAsistencia(Request $request): array
    {
        $validated = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'categoria_id' => 'nullable|integer',
            'ci'           => 'nullable|string',
            'usuario_id'   => 'nullable|string',
        ]);

        $fechaInicio = $validated['fecha_inicio'];
        $fechaFin    = $validated['fecha_fin'];

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

        $fechaFinConsulta = Carbon::parse($fechaFin)->addDay()->format('Y-m-d');

        $marcacionesSql = DB::connection('sqlsrv_rrhh')
            ->table('V_asistencia_permisos')
            ->whereBetween('FechaAsistencia', [$fechaInicio, $fechaFinConsulta])
            ->whereIn(DB::raw("REPLACE(LTRIM(RTRIM(CI)), ' ', '')"), $cis)
            ->get()
            ->groupBy(fn($item) => trim((string)$item->CI) . '_' . Carbon::parse($item->FechaAsistencia)->format('Y-m-d'));

        $turnosAsignados = DB::table('turnos_asignados as ta')
            ->join('turnos as t', 'ta.turno_id', '=', 't.id')
            ->whereIn('ta.usuario_id', $empleados->pluck('usuario_id'))
            ->whereBetween('ta.fecha', [$fechaInicio, $fechaFin])
            ->select(['ta.usuario_id', 'ta.fecha', 't.nombre_turno', 't.hora_inicio', 't.hora_fin', 't.duracion_horas'])
            ->get()
            ->groupBy('usuario_id');

            $feriadosGlobales = DB::connection('sqlsrv_rrhh')
            ->table('V_asistencia_permisos')
            ->whereBetween('FechaAsistencia', [$fechaInicio, $fechaFin])
            ->where('TipoJornadaEspecial', '<>', 'Laboral')
            ->select(['FechaAsistencia', 'DescripcionFeriadoTolerancia', 'TipoJornadaEspecial', 'AlcanceDescripcion'])
            ->get()
            ->keyBy(fn($item) => Carbon::parse($item->FechaAsistencia)->format('Y-m-d'));


        $fechasRango = [];
        $curr = Carbon::parse($fechaInicio);
        $fin  = Carbon::parse($fechaFin);
        while ($curr->lte($fin)) {
            $fechasRango[] = $curr->format('Y-m-d');
            $curr->addDay();
        }

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

            $diasCubiertosContinuos = [];

            foreach ($fechasRango as $fec) {
                if (in_array($fec, $diasCubiertosContinuos)) {
                    $diasDetalle[$fec] = [
                        'estado'              => '',
                        'hora_ingreso'        => null,
                        'hora_salida'         => null,
                        'minutos_retraso'     => 0,
                        'permiso'             => null,
                        'feriado_descripcion' => 'Laboral',
                        'tipo_jornada'        => 'Laboral',
                        'alcance_descripcion' => 'N/A'
                    ];
                    continue;
                }

                $carbonFecha    = Carbon::parse($fec);
                $key            = $ciLimpio . '_' . $fec;
                $marcacionesDia = $marcacionesSql[$key] ?? collect();
                $marcacion      = $marcacionesDia->first();
                $turnoDia       = $turnosPorFecha[$fec] ?? null;

                $horaIngreso   = isset($marcacion->HoraIngreso) ? Carbon::parse($marcacion->HoraIngreso)->format('H:i:s') : null;
                $horaSalida    = isset($marcacion->HoraSalida) ? Carbon::parse($marcacion->HoraSalida)->format('H:i:s') : null;
                $permisoNombre = $marcacion->NombrePermiso ?? null;

                // --- CAPTURAR NUEVOS CAMPOS DE FERIADOS Y TOLERANCIAS ---
                $feriadoGlobalDia = $feriadosGlobales[$fec] ?? null;
                $feriadoDesc   = $marcacion->DescripcionFeriadoTolerancia ?? 'Laboral';
                $tipoJornada   = $marcacion->TipoJornadaEspecial ?? 'Laboral'; // Ej: Feriado, Tolerancia, Laboral
                $alcanceDesc   = $marcacion->AlcanceDescripcion ?? 'N/A';

                if ($horaIngreso && $horaIngreso === $horaSalida) {
                    $horaSalida = null;
                }

                $esFinDeSemana = $carbonFecha->isWeekend();
                $retrasoMin    = 0;
                $estadoDia     = '';



                $fechaSiguiente = $carbonFecha->copy()->addDay()->format('Y-m-d');
                $keySiguiente   = $ciLimpio . '_' . $fechaSiguiente;
                $marcacionSig   = ($marcacionesSql[$keySiguiente] ?? collect())->first();

                $esTurnoContinuoCruzado = false;
                if ($horaIngreso && !$horaSalida && $marcacionSig) {
                    $punchSalidaSig = $marcacionSig->HoraSalida ?: $marcacionSig->HoraIngreso;
                    if ($punchSalidaSig) {
                        $horaSalida             = Carbon::parse($punchSalidaSig)->format('H:i:s');
                        $diasCubiertosContinuos[] = $fechaSiguiente;
                        $esTurnoContinuoCruzado   = true;
                    }
                }

                            if ($tipoJornada === 'Feriado' || $tipoJornada === 'Tolerancia') {
                    $diasFeriado++;
                    $estadoDia = strtoupper(substr($tipoJornada, 0, 3)); // 'FER' o 'TOL'
                    
                    $fechaFmt = $carbonFecha->format('d/m/Y');
                    $obsItem = "{$fechaFmt}: {$feriadoDesc} ({$tipoJornada})";
                    if (!in_array($obsItem, $observacionesList)) {
                        $observacionesList[] = $obsItem;
                    }
                }

                if ($permisoNombre) {
                    $permisoUpper = strtoupper($permisoNombre);
                    $fechaFmt     = $carbonFecha->format('d/m/Y');
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

              // --- EVALUAR SI ES FERIADO O TOLERANCIA DESDE SQL SERVER ---
if ($tipoJornada === 'Feriado' || $tipoJornada === 'Tolerancia') {
    $diasFeriado++;
    $estadoDia = strtoupper(substr($tipoJornada, 0, 3)); // Esto asigna 'FER' o 'TOL'
    $fechaFmt = $carbonFecha->format('d/m/Y');

    $obsItem = "{$fechaFmt}: {$feriadoDesc} ({$tipoJornada})";
    if (!in_array($obsItem, $observacionesList)) {
        $observacionesList[] = $obsItem;
    }
}
                $estadoDia = '';

                if ($horaIngreso || $horaSalida) {
                    $diasEfectTrabajados++;

                    if ($horaIngreso && $horaSalida) {
                        $timeIngreso   = Carbon::parse($fec . ' ' . $horaIngreso);

                        if ($esTurnoContinuoCruzado) {
                            $timeSalida = Carbon::parse($fechaSiguiente . ' ' . $horaSalida);
                        } else {
                            $timeSalida = Carbon::parse($fec . ' ' . $horaSalida);
                            if ($timeSalida->lessThanOrEqualTo($timeIngreso)) {
                                $timeSalida->addDay();
                            }
                        }

                        $minutosReales   = $timeIngreso->diffInMinutes($timeSalida, true);
                        $horasCalculadas = round($minutosReales / 60.0, 1);

                        $totalHorasMes += $horasCalculadas;
                        $estadoDia = ($horasCalculadas == (int)$horasCalculadas) ? (int)$horasCalculadas : $horasCalculadas;
                    } else {
                        $duracionHoras = (float)($turnoDia->duracion_horas ?? 8);
                        $totalHorasMes += $duracionHoras;
                        $estadoDia = ($duracionHoras == (int)$duracionHoras) ? (int)$duracionHoras : $duracionHoras;

                        if (($horaIngreso && !$horaSalida) || (!$horaIngreso && $horaSalida)) {
                            $omisionMarcadoCount++;
                        }
                    }

                    if ($horaIngreso && $turnoDia && !empty($turnoDia->hora_inicio)) {
                        $ingresoCarbon        = Carbon::parse($fec . ' ' . $horaIngreso);
                        $entradaOficialCarbon = Carbon::parse($fec . ' ' . $turnoDia->hora_inicio);
                        $limiteTolerancia     = (clone $entradaOficialCarbon)->addMinutes(5);

                        if ($ingresoCarbon->greaterThan($limiteTolerancia)) {
                            $retrasoMin = (int) floor($limiteTolerancia->diffInMinutes($ingresoCarbon, true));
                            $totalMinutosAtraso += $retrasoMin;
                        } else {
                            $retrasoMin = 0;
                        }
                    }
                } elseif ($turnoDia) {
                    // Si hay turno asignado pero no marcó, verificamos que no sea Feriado/Tolerancia antes de marcar Falta (F)
                    if ($tipoJornada === 'Feriado' || $tipoJornada === 'Tolerancia') {
                        $estadoDia = strtoupper(substr($tipoJornada, 0, 3)); // Ej: FER o TOL
                    } else {

                        $estadoDia = $permisoNombre ? 'PER' : 'F';
                        if (!$permisoNombre) {
                            $faltasCount++;
                        }
                    }
                } elseif ($permisoNombre) {
                    $estadoDia = 'PER';
                } elseif ($tipoJornada === 'Feriado' || $tipoJornada === 'Tolerancia') {
                    $estadoDia = strtoupper(substr($tipoJornada, 0, 3));
                } elseif ($esFinDeSemana) {
                    $diasFinSemana++;
                }

                // --- AGREGAR LOS NUEVOS CAMPOS AL ARRAY DE DETALLE DIARIO ---
                $diasDetalle[$fec] = [
                    'estado'              => $estadoDia,
                    'hora_ingreso'        => $horaIngreso,
                    'hora_salida'         => $horaSalida,
                    'minutos_retraso'     => $retrasoMin,
                    'permiso'             => $permisoNombre,
                    'feriado_descripcion' => $feriadoDesc,
                    'tipo_jornada'        => $tipoJornada,
                    'alcance_descripcion' => $alcanceDesc
                ];
            }

            $totalDiasMes       = $diasEfectTrabajados + $faltasCount + $diasBajaMedica + $diasLicencia + $diasVacacion + $diasComision + $diasFeriado + $diasFinSemana;
            $totalDiasDescontar = $faltasCount + floor($totalMinutosAtraso / 120);

            $obsTexto = implode('; ', array_unique($observacionesList));
            if ($totalMinutosAtraso > 0) {
                $obsTexto = trim("Suma {$totalMinutosAtraso} min de retraso. " . $obsTexto);
            }

            $matrizEmpleados[] = [
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

                'faltas'                => $faltasCount,
                'minutos_retraso'       => $totalMinutosAtraso,
                'total_minutos_atraso'  => $totalMinutosAtraso,
                'abandono'              => $abandonoCount,
                'omision_marcado'       => $omisionMarcadoCount,
                'total_dias_descontar'  => $totalDiasDescontar,

                'dias_efect_trabajados' => $diasEfectTrabajados,
                'dias_falta'            => $faltasCount,
                'dias_baja_medica'      => $diasBajaMedica,
                'dias_licencia'         => $diasLicencia,
                'dias_vacacion'         => $diasVacacion,
                'dias_comision'         => $diasComision,
                'dias_feriado'          => $diasFeriado,
                'dias_fin_semana'       => $diasFinSemana,
                'total_dias_mes'        => $totalDiasMes,
                'total_horas_mes'       => (float) round($totalHorasMes, 1),

                'observacion'           => $obsTexto,
                'observaciones'         => $obsTexto,

                'dias'                  => $diasDetalle,
                'dias_detalle'          => $diasDetalle
            ];
        }

        $nombreCat = $this->obtenerNombreCategoria($request->get('categoria_id'));

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

    private function obtenerNombreCategoria($categoriaId): string
    {
        if (!$categoriaId) {
            return 'TODAS LAS CATEGORÍAS';
        }
        return DB::table('categorias')->where('id', $categoriaId)->value('nombre') ?? 'CATEGORÍA SELECCIONADA';
    }
}