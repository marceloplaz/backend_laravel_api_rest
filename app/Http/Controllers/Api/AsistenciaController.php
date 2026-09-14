<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

        $cisPermitidos = [];

        // 4. OBTENCIÓN DE CIs PERMITIDOS EN MYSQL
        if (!$esSuperAdmin) {
            $serviciosPermitidos = DB::table('usuario_servicios')
                ->where('usuario_id', $usuarioAutenticado->id)
                ->pluck('servicio_id')
                ->toArray();

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

            if (empty($serviciosPermitidos) && empty($categoriasPermitidas)) {
                return response()->json(['status' => 'success', 'data' => []]);
            }

            $queryCis = DB::table('users as u')
                ->join('personas as p', 'p.user_id', '=', 'u.id')
                ->whereNotNull('p.carnet_identidad');

            if (!empty($serviciosPermitidos)) {
                $queryCis->whereExists(function ($q) use ($serviciosPermitidos) {
                    $q->select(DB::raw(1))
                      ->from('usuario_servicios as us')
                      ->whereColumn('us.usuario_id', 'u.id')
                      ->whereIn('us.servicio_id', $serviciosPermitidos);
                });
            }

            if (!empty($categoriasPermitidas)) {
                $queryCis->whereIn('u.categoria_id', $categoriasPermitidas);
            }

            $cisPermitidos = $queryCis->pluck('p.carnet_identidad')
                ->map(fn($i) => trim((string)$i))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (empty($cisPermitidos)) {
                return response()->json(['status' => 'success', 'data' => []]);
            }
        }

        // 5. TRADUCCIÓN AUTODETECTABLE DE BUSQUEDA (CI / USUARIO_ID)
        $ciObjetivo = null;

        if (!empty($inputCi)) {
            $ciObjetivo = trim((string)$inputCi);
        } elseif (!empty($usuarioId)) {
            $valorBusqueda = trim((string)$usuarioId);

            // Si el valor ingresado es mayor a 6 dígitos, asumimos que Angular envió un CI en usuario_id
            if (strlen($valorBusqueda) >= 5) {
                $ciObjetivo = $valorBusqueda;
            } else {
                // Si es un ID corto, traducimos en personas
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

    // Si NO es super_admin, verificar si tiene permiso para este CI
    if (!$esSuperAdmin) {
        // Normalizamos los CIs permitidos
        $permitidosLimpio = array_map('trim', $cisPermitidos);
        
        if (!in_array($ciLimpioBuscado, $permitidosLimpio)) {
            // Si el CI buscado no está en sus servicios/categorías permitidas, retorna vacío de inmediato
            return response()->json(['status' => 'success', 'data' => []]);
        }
    }

    // Filtramos en SQL Server eliminando espacios en blanco en la columna CI
    $queryAsistencia->whereRaw("REPLACE(LTRIM(RTRIM(CI)), ' ', '') = ?", [$ciLimpioBuscado]);

} else {
    // Si no se buscó un CI específico
    if (!$esSuperAdmin) {
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

        // 8. MAPEO Y CALCULO
        $resultado = $marcaciones->map(function ($item) use ($turnosProgramados) {
            $retrasoMinutos  = 0;
            $salidaTemprana  = false;
            $minutosTemprano = 0;
            $horaIngreso     = $item->HoraIngreso;
            $horaSalida      = $item->HoraSalida;
            $fecha           = Carbon::parse($item->FechaAsistencia)->format('Y-m-d');
            $ciLimpio        = trim((string)$item->CI);

            $key   = $ciLimpio . '_' . $fecha;
            $turno = $turnosProgramados->get($key);

            if ($horaIngreso && $horaIngreso === $horaSalida) {
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

                if ($horaIngreso && $horaEntradaOficial) {
                    $ingresoCarbon        = Carbon::parse($fecha . ' ' . $horaIngreso);
                    $entradaOficialCarbon = Carbon::parse($fecha . ' ' . $horaEntradaOficial);
                    $limiteTolerancia     = (clone $entradaOficialCarbon)->addMinutes($toleranciaMin)->endOfMinute();

                    if ($ingresoCarbon->greaterThan($limiteTolerancia)) {
                        $inicioTolerancia = (clone $entradaOficialCarbon)->addMinutes($toleranciaMin);
                        $retrasoMinutos   = (int) floor($inicioTolerancia->diffInMinutes($ingresoCarbon, true));
                    }
                }

                if ($horaSalida && $horaSalidaOficial) {
                    $salidaCarbon        = Carbon::parse($fecha . ' ' . $horaSalida);
                    $salidaOficialCarbon = Carbon::parse($fecha . ' ' . $horaSalidaOficial);

                    if ($salidaOficialCarbon->lessThan($entradaOficialCarbon ?? $salidaOficialCarbon)) {
                        $salidaOficialCarbon->addDay();
                        if ($salidaCarbon->lessThan(Carbon::parse($fecha . ' ' . $horaEntradaOficial))) {
                            $salidaCarbon->addDay();
                        }
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

        // 9. FILTRADO SI NO HUBO BUSQUEDA ESPECÍFICA
        if (empty($inputCi) && empty($usuarioId)) {
            $resultado = $resultado->filter(function ($item) {
                return $item['minutos_retraso'] > 0 
                    || $item['salida_temprana'] 
                    || !empty($item['nombre_permiso']) 
                    || !empty($item['tipo_codigo'])
                    || $item['turno_programado']['nombre'] !== 'Sin Turno';
            });
        }

        return response()->json([
            'status' => 'success',
            'data'   => $resultado->values()
        ]);
    }
}