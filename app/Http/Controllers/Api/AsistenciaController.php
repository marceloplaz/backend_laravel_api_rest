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
        'ci'           => 'nullable|numeric',
        'usuario_id'   => 'nullable|integer',
        'fecha_inicio' => 'required|date',
        'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
    ]);

    $ci          = $validated['ci'] ?? null;
    $usuarioId   = $validated['usuario_id'] ?? null;
    $fechaInicio = $validated['fecha_inicio'];
    $fechaFin    = $validated['fecha_fin'];

    // 2. AUTENTICACIÓN
    $usuarioAutenticado = $request->user();

    if (!$usuarioAutenticado) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    // 3. INICIALIZACIÓN DE VARIABLES
    $esSuperAdmin = method_exists($usuarioAutenticado, 'hasAnyRole') 
        ? $usuarioAutenticado->hasAnyRole(['super_admin', 'admin']) 
        : false;

    $cisPermitidos = [];

    // 4. VERIFICACIÓN DE ALCANCE
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

        $cisPermitidos = DB::table('users as u')
            ->join('personas as p', 'p.user_id', '=', 'u.id')
            ->leftJoin('usuario_servicios as us', 'us.usuario_id', '=', 'u.id')
            ->where(function($query) use ($serviciosPermitidos, $categoriasPermitidas) {
                $query->whereIn('us.servicio_id', $serviciosPermitidos)
                      ->orWhereIn('u.categoria_id', $categoriasPermitidas);
            })
            ->whereNotNull('p.carnet_identidad')
            ->pluck('p.carnet_identidad')
            ->map(fn($item) => trim($item))
            ->unique()
            ->toArray();
    }

    // 5. CONSULTA A SQL SERVER
    $queryAsistencia = DB::connection('sqlsrv_rrhh')
        ->table('V_asistencia_permisos')
        ->whereBetween('FechaAsistencia', [$fechaInicio, $fechaFin]);

    // Si se busca explícitamente a un usuario específico por ID o CI
    if (!empty($usuarioId)) {
        $queryAsistencia->where('pperCodPer', $usuarioId);
    } elseif (!empty($ci)) {
        $queryAsistencia->whereRaw('TRIM(CI) = ?', [(string)$ci]);
    } elseif (!$esSuperAdmin) {
        // Si no hay búsqueda individual, restringir a los CIs permitidos del servicio
        if (empty($cisPermitidos)) {
            return response()->json(['status' => 'success', 'data' => []]);
        }
        $queryAsistencia->whereIn(DB::raw('TRIM(CI)'), $cisPermitidos);
    }

    // EJECUTAR CONSULTA DE SQL SERVER AQUÍ
    $marcaciones = $queryAsistencia->orderBy('FechaAsistencia', 'ASC')->get();

    if ($marcaciones->isEmpty()) {
        return response()->json(['status' => 'success', 'data' => []]);
    }

    // 6. EXTRAER CIs PARA LOS TURNOS
    $cis = $marcaciones->pluck('CI')
        ->filter()
        ->map(fn($item) => trim($item))
        ->unique()
        ->values()
        ->toArray();

    // 7. OBTENER TURNOS Y SERVICIOS PROGRAMADOS (MySQL)
    $turnosProgramados = DB::connection('mysql')
        ->table('turnos_asignados as ta')
        ->join('users as u', 'ta.usuario_id', '=', 'u.id')
        ->join('personas as p', 'p.user_id', '=', 'u.id')
        ->join('turnos as t', 'ta.turno_id', '=', 't.id')
        ->leftJoin('servicios as s', 'ta.servicio_id', '=', 's.id')
        ->whereIn(DB::raw('TRIM(p.carnet_identidad)'), $cis)
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
            return trim($item->ci) . '_' . Carbon::parse($item->fecha)->format('Y-m-d');
        });

    // 8. MAPEO Y COMPARACIÓN EN MEMORIA
    $resultado = $marcaciones->map(function ($item) use ($turnosProgramados) {
        $retrasoMinutos = 0;
        $horaIngreso    = $item->HoraIngreso;
        $horaSalida     = $item->HoraSalida;
        $fecha          = Carbon::parse($item->FechaAsistencia)->format('Y-m-d');
        $ciLimpio       = trim($item->CI);

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
        } else {
            if ($horaIngreso) {
                $h = Carbon::parse($horaIngreso)->hour;
                if ($h >= 5 && $h < 11) {
                    $horaEntradaOficial = '07:00:00';
                    $horaSalidaOficial  = '13:00:00';
                } elseif ($h >= 11 && $h < 17) {
                    $horaEntradaOficial = '13:00:00';
                    $horaSalidaOficial  = '19:00:00';
                } else {
                    $horaEntradaOficial = '19:00:00';
                    $horaSalidaOficial  = '07:00:00';
                }
            }
        }

        if ($horaIngreso && $horaEntradaOficial) {
            $ingresoCarbon        = Carbon::parse($fecha . ' ' . $horaIngreso);
            $entradaOficialCarbon = Carbon::parse($fecha . ' ' . $horaEntradaOficial);
            $limiteTolerancia     = (clone $entradaOficialCarbon)->addMinutes($toleranciaMin);

            if ($ingresoCarbon->greaterThan($limiteTolerancia)) {
                $retrasoMinutos = (int) round($entradaOficialCarbon->diffInMinutes($ingresoCarbon, true), 0);
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
            'turno_programado' => [
                'nombre'      => $turno ? $turno->nombre_turno : 'Sin Turno',
                'servicio'    => $servicioNombre,
                'hora_inicio' => $horaEntradaOficial ? Carbon::parse($horaEntradaOficial)->format('H:i') : null,
                'hora_fin'    => $horaSalidaOficial ? Carbon::parse($horaSalidaOficial)->format('H:i') : null,
            ]
        ];
    });

    // 9. FILTRAR SOLO SI NO SE ESTÁ BUSCANDO A UN USUARIO ESPECÍFICO
    if (empty($usuarioId) && empty($ci)) {
        $resultado = $resultado->filter(function ($item) {
            $tieneRetraso = $item['minutos_retraso'] > 0;
            $tienePermiso = !empty($item['nombre_permiso']) || !empty($item['tipo_codigo']);
            return $tieneRetraso || $tienePermiso;
        });
    }

    return response()->json([
        'status' => 'success',
        'data'   => $resultado->values()
    ]);
}
}