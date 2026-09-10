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
        $request->validate([
            'usuario_id'   => 'nullable|integer',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $usuarioId   = $request->input('usuario_id');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin    = $request->input('fecha_fin');

        $query = DB::connection('sqlsrv_rrhh')
            ->table('V_asistencia_permisos')
            ->whereBetween('FechaAsistencia', [$fechaInicio, $fechaFin]);

        $query->when(!empty($usuarioId), function ($q) use ($usuarioId) {
            return $q->where('pperCodPer', $usuarioId);
        });

        // Traemos todos los registros del rango para evaluar el turno correcto dinámicamente
        $registros = $query->orderBy('FechaAsistencia', 'ASC')->get();
        $resultado = $registros->map(function ($item) {
            $retrasoMinutos = 0;
            $horaIngreso    = $item->HoraIngreso;
            $horaSalida     = $item->HoraSalida;

            if ($horaIngreso && $horaIngreso === $horaSalida) {
                $horaSalida = null;
            }

            if ($horaIngreso) {
                $ingresoCarbon = Carbon::parse($horaIngreso);
                
                // 1. Identificar el turno correspondiente según el rango de marcación
                $horaEntradaOficial = $this->obtenerTurnoOficial($ingresoCarbon);

                // 2. Definir hora límite agregando los 5 minutos de tolerancia
                $horaLimiteTolerancia = (clone $horaEntradaOficial)->addMinutes(5);

                // 3. Evaluar si existe retraso
                if ($ingresoCarbon->greaterThan($horaLimiteTolerancia)) {
                    // Minutos transcurridos desde el inicio oficial del turno (o desde la tolerancia)
                    $retrasoMinutos = (int) round($horaLimiteTolerancia->diffInMinutes($ingresoCarbon, true), 0);
                }
            }

            return [
                'codigo_personal'  => $item->pperCodPer,
                'id_interno'       => $item->pperIdePer,
                'ci'               => $item->CI,
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
            ];
        })
        // 4. Filtrar únicamente las incidencias reales
        ->filter(function ($item) {
            $tieneRetraso  = $item['minutos_retraso'] > 0;
            $tienePermiso  = !empty($item['nombre_permiso']) || !empty($item['tipo_codigo']);
            return $tieneRetraso || $tienePermiso;
        })
        ->values();

        return response()->json([
            'status' => 'success',
            'data'   => $resultado
        ]);
    }

    /**
     * Determina la hora oficial del turno según el rango de marcación.
     */
    private function obtenerTurnoOficial(Carbon $horaIngreso): Carbon
    {
        $hora = $horaIngreso->hour;

        // Turno Mañana (07:00) -> Marcaciones entre las 05:00 y las 10:59
        if ($hora >= 5 && $hora < 11) {
            return Carbon::parse($horaIngreso->format('Y-m-d') . ' 07:00:00');
        }

        // Turno Tarde (13:00) -> Marcaciones entre las 11:00 y las 16:59
        if ($hora >= 11 && $hora < 17) {
            return Carbon::parse($horaIngreso->format('Y-m-d') . ' 13:00:00');
        }

        // Turno Noche (19:00) -> Marcaciones desde las 17:00 en adelante o de madrugada
        return Carbon::parse($horaIngreso->format('Y-m-d') . ' 19:00:00');
    }
}