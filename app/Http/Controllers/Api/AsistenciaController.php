<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class AsistenciaController extends Controller
{
    public function obtenerAsistenciaRango(Request $request)
    {
        // 1. VALIDACIÓN DE INPUTS
        $validated = $request->validate([
            'ci'           => 'nullable|string',
            'usuario_id'   => 'nullable|string', // Acepta tanto ID de usuario como CI
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $inputCi     = $validated['ci'] ?? null;
        $usuarioId   = $validated['usuario_id'] ?? null;
        $fechaInicio = $validated['fecha_inicio'];
        $fechaFin    = $validated['fecha_fin'];

        // 2. AUTENTICACIÓN
        $usuarioAutenticado = $request->user();
        if (!$usuarioAutenticado) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 3. ROLES
        $esSuperAdmin = method_exists($usuarioAutenticado, 'hasAnyRole') 
            ? $usuarioAutenticado->hasAnyRole(['super_admin', 'admin']) 
            : false;

        $cisPermitidos        = [];
        $categoriasPermitidas = [];
        $serviciosPermitidos  = [];

        // 4. OBTENCIÓN DINÁMICA DE PERMISOS (Basado en roles y tablas pivote)
        if (!$esSuperAdmin) {
            // 4.1. Obtener IDs de roles del usuario desde 'role_user'
            $roleIds = DB::table('role_user')
                ->where('user_id', $usuarioAutenticado->id)
                ->pluck('role_id')
                ->toArray();

            // 4.2. Obtener categorías permitidas desde 'categoria_role' + categoría directa del usuario
            $categoriasPermitidas = DB::table('categoria_role')
                ->whereIn('role_id', $roleIds)
                ->pluck('categoria_id')
                ->toArray();

            if (!empty($usuarioAutenticado->categoria_id)) {
                $categoriasPermitidas[] = $usuarioAutenticado->categoria_id;
                $categoriasPermitidas = array_unique($categoriasPermitidas);
            }

            // 4.3. Obtener servicios permitidos desde 'role_servicio' + 'usuario_servicios' (si aplica)
            $serviciosPorRol = DB::table('role_servicio')
                ->whereIn('role_id', $roleIds)
                ->pluck('servicio_id')
                ->toArray();

            $serviciosPorUsuario = DB::table('usuario_servicios')
                ->where('usuario_id', $usuarioAutenticado->id)
                ->pluck('servicio_id')
                ->toArray();

            $serviciosPermitidos = array_unique(array_merge($serviciosPorRol, $serviciosPorUsuario));

            // Si el usuario no tiene ni servicio ni categoría asociada, no ve registros
            if (empty($serviciosPermitidos) && empty($categoriasPermitidas)) {
                return response()->json(['status' => 'success', 'data' => []]);
            }

            // 4.4. Consulta base de personas permitidas para listados generales
            $queryCis = DB::table('users as u')
                ->join('personas as p', 'p.user_id', '=', 'u.id')
                ->whereNotNull('p.carnet_identidad');

            if (!empty($categoriasPermitidas)) {
                $queryCis->whereIn('u.categoria_id', $categoriasPermitidas);
            }

            if (!empty($serviciosPermitidos)) {
                $queryCis->where(function ($subQuery) use ($serviciosPermitidos, $fechaInicio, $fechaFin) {
                    $subQuery->whereExists(function ($q) use ($serviciosPermitidos, $fechaInicio, $fechaFin) {
                        $q->select(DB::raw(1))
                          ->from('turnos_asignados as ta')
                          ->whereColumn('ta.usuario_id', 'u.id')
                          ->whereIn('ta.servicio_id', $serviciosPermitidos)
                          ->whereBetween('ta.fecha', [$fechaInicio, $fechaFin]);
                    })
                    ->orWhereExists(function ($q) use ($serviciosPermitidos) {
                        $q->select(DB::raw(1))
                          ->from('usuario_servicios as us')
                          ->whereColumn('us.usuario_id', 'u.id')
                          ->whereIn('us.servicio_id', $serviciosPermitidos);
                    });
                });
            }

            $cisPermitidos = $queryCis->pluck('p.carnet_identidad')
                ->map(fn($i) => trim((string)$i))
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        }

        // 5. TRADUCCIÓN AUTODETECTABLE DE BUSQUEDA (CI / USUARIO_ID)
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

        // 6. CONSULTA A SQL SERVER
        $queryAsistencia = DB::connection('sqlsrv_rrhh')
            ->table('V_asistencia_permisos')
            ->whereBetween('FechaAsistencia', [$fechaInicio, $fechaFin]);

        if ($ciObjetivo !== null) {
            $ciLimpioBuscado = trim((string)$ciObjetivo);

            // Si NO es super_admin, validamos si el empleado buscado cumple con pertenecer a los filtros permitidos
            if (!$esSuperAdmin)  {
                $tienePermisoEmpleado = DB::table('users as u')
                    ->join('personas as p', 'p.user_id', '=', 'u.id')
                    ->where(DB::raw('TRIM(p.carnet_identidad)'), $ciLimpioBuscado)
                    ->where(function ($q) use ($categoriasPermitidas, $serviciosPermitidos) {
                        if (!empty($categoriasPermitidas)) {
                            $q->whereIn('u.categoria_id', $categoriasPermitidas);
                        }
                        if (!empty($serviciosPermitidos)) {
                            $q->where(function ($sub) use ($serviciosPermitidos) {
                                $sub->whereExists(function ($ta) use ($serviciosPermitidos) {
                                    $ta->select(DB::raw(1))
                                       ->from('turnos_asignados as t_asig')
                                       ->whereColumn('t_asig.usuario_id', 'u.id')
                                       ->whereIn('t_asig.servicio_id', $serviciosPermitidos);
                                })
                                ->orWhereExists(function ($us) use ($serviciosPermitidos) {
                                    $us->select(DB::raw(1))
                                       ->from('usuario_servicios as u_serv')
                                       ->whereColumn('u_serv.usuario_id', 'u.id')
                                       ->whereIn('u_serv.servicio_id', $serviciosPermitidos);
                                });
                            });
                        }
                    })
                    ->exists();

                if (!$tienePermisoEmpleado) {
                    return response()->json(['status' => 'success', 'data' => []]);
                }
            }

            $queryAsistencia->whereRaw("REPLACE(LTRIM(RTRIM(CI)), ' ', '') = ?", [$ciLimpioBuscado]);

        } else {
            if (!$esSuperAdmin) {
                if (empty($cisPermitidos)) {
                    return response()->json(['status' => 'success', 'data' => []]);
                }
                $queryAsistencia->whereIn(DB::raw("REPLACE(LTRIM(RTRIM(CI)), ' ', '')"), array_map('trim', $cisPermitidos));
            }
        }

        $marcaciones = $queryAsistencia->orderBy('FechaAsistencia', 'ASC')->get();

        // 7. OBTENER TURNOS Y SERVICIOS EN MYSQL
        $cisEncontrados = $marcaciones->pluck('CI')
            ->filter()
            ->map(fn($i) => trim((string)$i))
            ->unique()
            ->values()
            ->toArray();

        $turnosProgramados = [];
        if (!empty($cisEncontrados)) {
            $turnosProgramados = DB::table('turnos_asignados as ta')
                ->join('users as u', 'ta.usuario_id', '=', 'u.id')
                ->join('personas as p', 'p.user_id', '=', 'u.id')
                ->join('turnos as t', 'ta.turno_id', '=', 't.id')
                ->leftJoin('servicios as s', 'ta.servicio_id', '=', 's.id')
                ->whereIn(DB::raw('TRIM(p.carnet_identidad)'), $cisEncontrados)
                ->whereBetween('ta.fecha', [$fechaInicio, $fechaFin])
                ->select([
                    'p.carnet_identidad as ci',
                    'ta.fecha',
                    't.nombre_turno',
                    't.hora_inicio',
                    't.hora_fin',
                    's.nombre as servicio_nombre'
                ])
                ->get()
                ->keyBy(function ($item) {
                    return trim((string)$item->ci) . '_' . Carbon::parse($item->fecha)->format('Y-m-d');
                });
        }

       // 8. MAPEO Y CÁLCULO CON SOPORTE PARA TURNOS NOCTURNOS (CRUCE DE MEDIANOCHE)
        $marcacionesConsumidas = [];

        // 8.1. REUBICAR SALIDAS DE TURNOS NOCTURNOS (Ej: 19:00 - 07:00)
        foreach ($marcaciones as $item) {
            $fechaActual = Carbon::parse($item->FechaAsistencia)->format('Y-m-d');
            $ciLimpio    = trim((string)$item->CI);
            $keyActual   = $ciLimpio . '_' . $fechaActual;

            $turno = $turnosProgramados[$keyActual] ?? null;

            // Verificar si hay turno asignado y si cruza la medianoche
            if ($turno && $turno->hora_inicio && $turno->hora_fin) {
                $entradaOficial = Carbon::parse($fechaActual . ' ' . $turno->hora_inicio);
                $salidaOficial  = Carbon::parse($fechaActual . ' ' . $turno->hora_fin);

                if ($salidaOficial->lessThanOrEqualTo($entradaOficial)) {
                    $salidaOficial->addDay(); // La salida oficial corresponde al día siguiente D+1
                    $fechaSiguiente = Carbon::parse($fechaActual)->addDay()->format('Y-m-d');

                    // Buscar marcación en la mañana del día siguiente para el mismo funcionario
                    foreach ($marcaciones as $mSig) {
                        $fechaSig = Carbon::parse($mSig->FechaAsistencia)->format('Y-m-d');
                        $ciSig    = trim((string)$mSig->CI);

                        if ($ciSig === $ciLimpio && $fechaSig === $fechaSiguiente) {
                            $horaPunch = $mSig->HoraIngreso ?? $mSig->HoraSalida;
                            if ($horaPunch) {
                                $punchCarbon = Carbon::parse($fechaSiguiente . ' ' . $horaPunch);
                                
                                // Ventana de tolerancia de salida (3 horas antes o 4 horas después de la hora fin oficial)
                                $ventanaMin = (clone $salidaOficial)->subHours(3);
                                $ventanaMax = (clone $salidaOficial)->addHours(4);

                                if ($punchCarbon->between($ventanaMin, $ventanaMax)) {
                                    // Asignar esta marcación como la HORA DE SALIDA del turno nocturno (Día D)
                                    $item->HoraSalidaReubicada = $horaPunch;

                                    // Marcar como consumida para que no aparezca como un ingreso sin turno el día D+1
                                    $keyConsumo = $ciLimpio . '_' . $fechaSiguiente . '_' . $horaPunch;
                                    $marcacionesConsumidas[$keyConsumo] = true;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        // 8.2. FILTRAR REGISTROS MOVIDOS Y CONSTRUIR RESULTADO
        $resultado = $marcaciones->reject(function ($item) use ($marcacionesConsumidas) {
            $fecha    = Carbon::parse($item->FechaAsistencia)->format('Y-m-d');
            $ciLimpio = trim((string)$item->CI);
            $hora     = $item->HoraIngreso ?? $item->HoraSalida;

            $keyConsumo = $ciLimpio . '_' . $fecha . '_' . $hora;

            // Ocultar del día actual la marcación que fue reubicada como salida del turno nocturno previo
            return isset($marcacionesConsumidas[$keyConsumo]);
        })->map(function ($item) use ($turnosProgramados) {
            $retrasoMinutos   = 0;
            $salidaTemprana   = false;
            $minutosTemprano  = 0;
            $horaIngreso      = $item->HoraIngreso;
            
            // Usar la salida reubicada si existió cruce de medianoche; de lo contrario la salida normal
            $horaSalida       = $item->HoraSalidaReubicada ?? $item->HoraSalida;
            $fecha            = Carbon::parse($item->FechaAsistencia)->format('Y-m-d');
            $ciLimpio         = trim((string)$item->CI);

            $key   = $ciLimpio . '_' . $fecha;
            $turno = isset($turnosProgramados[$key]) ? $turnosProgramados[$key] : null;

            if ($horaIngreso && $horaIngreso === $horaSalida && !isset($item->HoraSalidaReubicada)) {
                $horaSalida = null;
            }

            $horaEntradaOficial = null;
            $horaSalidaOficial  = null;
            $servicioNombre     = 'Sin servicio asignado';
            $toleranciaMin      = 5;

            if ($turno) {
                $horaEntradaOficial = $turno->hora_inicio;
                $horaSalidaOficial  = $turno->hora_fin;
                $servicioNombre     = $turno->servicio_nombre ?? 'General';

                // Cálculo de Retraso en Ingreso
                if ($horaIngreso && $horaEntradaOficial) {
                    $ingresoCarbon        = Carbon::parse($fecha . ' ' . $horaIngreso);
                    $entradaOficialCarbon = Carbon::parse($fecha . ' ' . $horaEntradaOficial);
                    $limiteTolerancia     = (clone $entradaOficialCarbon)->addMinutes($toleranciaMin)->endOfMinute();

                    if ($ingresoCarbon->greaterThan($limiteTolerancia)) {
                        $inicioTolerancia = (clone $entradaOficialCarbon)->addMinutes($toleranciaMin);
                        $retrasoMinutos   = (int) floor($inicioTolerancia->diffInMinutes($ingresoCarbon, true));
                    }
                }

                // Cálculo de Salida Temprana (Ajustado para fechas distintas)
                if ($horaSalida && $horaSalidaOficial) {
                    $fechaSalidaReal = isset($item->HoraSalidaReubicada) 
                        ? Carbon::parse($fecha)->addDay()->format('Y-m-d') 
                        : $fecha;

                    $salidaCarbon        = Carbon::parse($fechaSalidaReal . ' ' . $horaSalida);
                    $salidaOficialCarbon = Carbon::parse($fecha . ' ' . $horaSalidaOficial);

                    if ($salidaOficialCarbon->lessThanOrEqualTo(Carbon::parse($fecha . ' ' . $horaEntradaOficial))) {
                        $salidaOficialCarbon->addDay();
                    }

                    if ($salidaCarbon->lessThan($salidaOficialCarbon)) {
                        $salidaTemprana  = true;
                        $minutosTemprano = (int) round($salidaCarbon->diffInMinutes($salidaOficialCarbon, true), 0);
                    }
                }
            }

            return [
                'codigo_personal'  => $item->pperCodPer,
                'id_interno'       => $item->pperIdePer,
                'ci'               => $ciLimpio,
                'celular'          => $item->pperTelCel,
                'nombre_completo'  => $item->NombreCompleto,
                'fecha_asistencia' => $item->FechaAsistencia,
                'hora_ingreso'     => $horaIngreso,
                'hora_salida'      => $horaSalida,
                'tipo_codigo'      => $item->TipoCodigo,
                'nombre_permiso'   => $item->NombrePermiso,
                'permiso_inicio'   => $item->PermisoFechaInicio,
                'permiso_fin'      => $item->PermisoFechaFin,
                'duracion'         => $item->Duracion,
                'gestiones'        => $item->Gestiones,
                'minutos_retraso'  => $retrasoMinutos,
                'tiene_retraso'    => $retrasoMinutos > 0,
                'salida_temprana'  => $salidaTemprana,
                'minutos_temprano' => $minutosTemprano,
                'turno_programado' => [
                    'nombre'      => $turno ? $turno->nombre_turno : 'Sin Turno',
                    'servicio'    => $servicioNombre,
                    'hora_inicio' => $horaEntradaOficial ? Carbon::parse($horaEntradaOficial)->format('H:i') : null,
                    'hora_fin'    => $horaSalidaOficial ? Carbon::parse($horaSalidaOficial)->format('H:i') : null,
                ]
            ];
        });
        return response()->json(['status' => 'success', 'data' => $resultado->values()]);         
    }
    
public function generarMatrizPdf(Request $request)
{
    try {
        // 1. VALIDACIÓN DE INPUTS DEL MODAL
        $validated = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'categoria_id' => 'nullable|integer',
        ]);

        $fechaInicio = $validated['fecha_inicio'];
        $fechaFin    = $validated['fecha_fin'];
        $categoriaId = $validated['categoria_id'] ?? null;

        // 2. AUTENTICACIÓN
        $usuarioAutenticado = $request->user();
        if (!$usuarioAutenticado) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 3. ROLES Y PERMISOS (Idéntico a tu método funcional)
        $esSuperAdmin = method_exists($usuarioAutenticado, 'hasAnyRole') 
            ? $usuarioAutenticado->hasAnyRole(['super_admin', 'admin']) 
            : false;

        $cisPermitidos = [];
        $categoriasPermitidas = [];
        $serviciosPermitidos = [];

        if (!$esSuperAdmin) {
            $roleIds = DB::table('role_user')
                ->where('user_id', $usuarioAutenticado->id)
                ->pluck('role_id')
                ->toArray();

            $categoriasPermitidas = DB::table('categoria_role')
                ->whereIn('role_id', $roleIds)
                ->pluck('categoria_id')
                ->toArray();

            if (!empty($usuarioAutenticado->categoria_id)) {
                $categoriasPermitidas[] = $usuarioAutenticado->categoria_id;
                $categoriasPermitidas = array_unique($categoriasPermitidas);
            }

            $serviciosPorRol = DB::table('role_servicio')
                ->whereIn('role_id', $roleIds)
                ->pluck('servicio_id')
                ->toArray();

            $serviciosPorUsuario = DB::table('usuario_servicios')
                ->where('usuario_id', $usuarioAutenticado->id)
                ->pluck('servicio_id')
                ->toArray();

            $serviciosPermitidos = array_unique(array_merge($serviciosPorRol, $serviciosPorUsuario));

            if (empty($serviciosPermitidos) && empty($categoriasPermitidas)) {
                return response()->json(['message' => 'No cuenta con permisos o categorías asignadas.'], 403);
            }

            // Consulta base de CIs permitidos según roles
            $queryCis = DB::table('users as u')
                ->join('personas as p', 'p.user_id', '=', 'u.id')
                ->whereNotNull('p.carnet_identidad');

            if (!empty($categoriasPermitidas)) {
                $queryCis->whereIn('u.categoria_id', $categoriasPermitidas);
            }

            $cisPermitidos = $queryCis->pluck('p.carnet_identidad')
                ->map(fn($i) => trim((string)$i))
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        }

        // Obtener nombre de la categoría para el encabezado del reporte
        $nombreCategoria = 'TODAS LAS CATEGORÍAS';
        if ($categoriaId) {
            if (!$esSuperAdmin && !in_array((int)$categoriaId, array_map('intval', $categoriasPermitidas))) {
                return response()->json(['message' => 'No autorizado para esta categoría.'], 403);
            }
            $nombreCategoria = DB::table('categorias')->where('id', $categoriaId)->value('nombre') ?? 'CATEGORÍA SELECCIONADA';
        }

        // 4. CONSULTAR PERSONAL SEGÚN FILTRO DE CATEGORÍA
        $queryPersonal = DB::table('users as u')
            ->join('personas as p', 'p.user_id', '=', 'u.id')
            ->leftJoin('categorias as c', 'u.categoria_id', '=', 'c.id')
            ->whereNotNull('p.carnet_identidad');

        if ($categoriaId) {
            $queryPersonal->where('u.categoria_id', $categoriaId);
        } elseif (!$esSuperAdmin && !empty($categoriasPermitidas)) {
            $queryPersonal->whereIn('u.categoria_id', $categoriasPermitidas);
        }

        if (!$esSuperAdmin && !empty($cisPermitidos)) {
            $queryPersonal->whereIn(DB::raw('TRIM(p.carnet_identidad)'), $cisPermitidos);
        }

        $empleados = $queryPersonal->select([
            'u.id as usuario_id',
            'p.carnet_identidad as ci',
            'p.id as codigo_personal', 
             'p.nombre_completo as nombre_completo',
            'c.nombre as categoria_nombre'
        ])->orderBy('p.nombre_completo', 'ASC')->get();

        if ($empleados->isEmpty()) {
            return response()->json(['message' => 'No se encontraron registros para los filtros seleccionados.'], 404);
        }

        $cis = $empleados->pluck('ci')->map(fn($i) => trim((string)$i))->filter()->unique()->values()->toArray();

        if (empty($cis)) {
            return response()->json(['message' => 'El personal encontrado no cuenta con número de Cédula de Identidad válido.'], 422);
        }

        // 5. CONSULTAR MARCACIONES Y PERMISOS EN SQL SERVER
        $marcacionesSql = DB::connection('sqlsrv_rrhh')
            ->table('V_asistencia_permisos')
            ->whereBetween('FechaAsistencia', [$fechaInicio, $fechaFin])
            ->whereIn(DB::raw("REPLACE(LTRIM(RTRIM(CI)), ' ', '')"), $cis)
            ->get()
            ->groupBy(function($item) {
                return trim((string)$item->CI) . '_' . Carbon::parse($item->FechaAsistencia)->format('Y-m-d');
            });

        // 6. CONSULTAR TURNOS EN MYSQL
        $turnosAsignados = DB::table('turnos_asignados as ta')
            ->join('turnos as t', 'ta.turno_id', '=', 't.id')
            ->whereIn('ta.usuario_id', $empleados->pluck('usuario_id'))
            ->whereBetween('ta.fecha', [$fechaInicio, $fechaFin])
            ->select([
                'ta.usuario_id', 
                'ta.fecha', 
                't.nombre_turno', 
                't.hora_inicio', 
                't.hora_fin', 
                't.duracion_horas'
            ])
            ->get()
            ->groupBy('usuario_id');

        // 7. CONSTRUIR RANGO DE FECHAS (Columnas 1 al 31)
        $periodoInicio = Carbon::parse($fechaInicio);
        $periodoFin    = Carbon::parse($fechaFin);
        $fechasRango   = [];
        $curr = $periodoInicio->copy();
        while ($curr->lte($periodoFin)) {
            $fechasRango[] = $curr->format('Y-m-d');
            $curr->addDay();
        }

        $matrizEmpleados = [];

        foreach ($empleados as $emp) {
            $ciLimpio = trim((string)$emp->ci);
            $userTurnos = $turnosAsignados[$emp->usuario_id] ?? collect();
            $turnosPorFecha = $userTurnos->keyBy(fn($t) => Carbon::parse($t->fecha)->format('Y-m-d'));

            $diasAsistidosCount  = 0;
            $totalMinutosAtraso  = 0;
            $totalHorasMes       = 0;
            $observacionesList   = [];
            $diasDetalle         = [];

            foreach ($fechasRango as $fec) {
                $key = $ciLimpio . '_' . $fec;
                $marcacionesDia = $marcacionesSql[$key] ?? collect();
                $marcacion = $marcacionesDia->first();
                $turnoDia  = $turnosPorFecha[$fec] ?? null;

                $estadoDia  = '';
                $retrasoMin = 0;
                $obsDia     = null;

                foreach ($marcacionesDia as $m) {
                    if (!empty($m->NombrePermiso)) {
                        $obsDia = $m->NombrePermiso;
                        $fechaFmt = Carbon::parse($fec)->format('d/m/y');
                        $observacionesList[] = "{$fechaFmt} {$obsDia}";
                    }
                }

                if ($turnoDia) {
                    $duracionHoras = (float)($turnoDia->duracion_horas ?? 8);
                    $entradaOficialCarbon = Carbon::parse($fec . ' ' . $turnoDia->hora_inicio);
                    $salidaOficialCarbon  = Carbon::parse($fec . ' ' . $turnoDia->hora_fin);

                    if ($salidaOficialCarbon->lessThanOrEqualTo($entradaOficialCarbon)) {
                        $salidaOficialCarbon->addDay();
                    }

                    if ($marcacion && $marcacion->HoraIngreso) {
                        $diasAsistidosCount++;
                        $totalHorasMes += $duracionHoras;

                        $ingresoCarbon = Carbon::parse($fec . ' ' . $marcacion->HoraIngreso);
                        $limiteTolerancia = (clone $entradaOficialCarbon)->addMinutes(5)->endOfMinute();

                        if ($ingresoCarbon->greaterThan($limiteTolerancia)) {
                            $retrasoMin = (int) floor((clone $entradaOficialCarbon)->addMinutes(5)->diffInMinutes($ingresoCarbon, true));
                            $totalMinutosAtraso += $retrasoMin;
                        }
                        
                        $estadoDia = $duracionHoras; 
                    } else {
                        $estadoDia = $obsDia ? 'PER' : 'F';
                    }
                } elseif ($marcacion && $marcacion->HoraIngreso) {
                    $diasAsistidosCount++;
                    $estadoDia = 'P';
                }

                $diasDetalle[$fec] = [
                    'estado'          => $estadoDia,
                    'minutos_retraso' => $retrasoMin,
                ];
            }

            $matrizEmpleados[] = [
                'codigo_personal'      => $emp->codigo_personal,
                'ci'                   => $ciLimpio,
                'nombre_completo'      => $emp->nombre_completo,
                'categoria'            => $emp->categoria_nombre,
                'dias_asistidos'       => $diasAsistidosCount,
                'total_minutos_atraso' => $totalMinutosAtraso,
                'total_horas_mes'      => $totalHorasMes,
                'observaciones'        => implode(', ', array_unique($observacionesList)),
                'dias'                 => $diasDetalle
            ];
        }

        // 8. RENDERIZAR PDF CON DOMPDF
        $pdf = Pdf::loadView('pdf.matriz_asistencia_hospital', [
            'empleados'       => $matrizEmpleados,
            'fechaInicio'     => $fechaInicio,
            'fechaFin'        => $fechaFin,
            'nombreCategoria' => $nombreCategoria,
            'fechasRango'     => $fechasRango,
        ]);

        $pdf->setPaper('letter', 'landscape');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="matriz_turnos_' . $fechaInicio . '.pdf"'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ], 500);
    }
}
}