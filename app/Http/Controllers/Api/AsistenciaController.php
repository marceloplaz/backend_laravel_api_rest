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
        // Validamos los parámetros de entrada
        $request->validate([
            'usuario_id'   => 'required|integer',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $usuarioId = $request->input('usuario_id');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        // 1. Consultamos la vista en SQL Server
        $registros = DB::connection('sqlsrv_rrhh')
            ->table('V_asistencia_permisos')
            ->where('pperCodPer', $usuarioId)
            ->whereBetween('FechaAsistencia', [$fechaInicio, $fechaFin])
            ->orderBy('FechaAsistencia', 'ASC')
            ->get();

        // 2. Reglas de asistencia (ej: Hora oficial de entrada 07:00 con 5 min de tolerancia)
        $horaOficialEntrada = '07:00:00';
        $minutosTolerancia = 5;
        $ingresoPermitido = Carbon::parse($horaOficialEntrada)->addMinutes($minutosTolerancia);

        // 3. Procesamiento y mapeo en el Backend
        $resultado = $registros->map(function ($item) use ($ingresoPermitido) {
            $retrasoMinutos = 0;
            $horaIngreso = $item->HoraIngreso;
            $horaSalida = $item->HoraSalida;

            // Control en Backend: Si la hora de entrada y salida son idénticas, significa que hubo un solo marcado
            if ($horaIngreso === $horaSalida) {
                $horaSalida = null; 
            }

            // Cálculo de retrasos si existe hora de ingreso
            if ($horaIngreso) {
                $horaIngresoReal = Carbon::parse($horaIngreso);

                if ($horaIngresoReal->greaterThan($ingresoPermitido)) {
    // Redondeamos a 0 decimales (o cambia el 0 por 2 si prefieres decimales)
    $retrasoMinutos = round($ingresoPermitido->diffInMinutes($horaIngresoReal, true), 0);
}
            }

            return [
                'codigo_personal'   => $item->pperCodPer,
                'id_interno'        => $item->pperIdePer,
                'ci'                => $item->CI,
                'celular'           => $item->pperTelCel,
                'nombre_completo'   => $item->NombreCompleto,
                'fecha_asistencia'  => $item->FechaAsistencia,
                'hora_ingreso'      => $horaIngreso,
                'hora_salida'       => $horaSalida,
                // Datos de permisos solicitados
                'tipo_codigo'       => $item->TipoCodigo,
                'nombre_permiso'    => $item->NombrePermiso,
                'permiso_inicio'    => $item->PermisoFechaInicio,
                'permiso_fin'       => $item->PermisoFechaFin,
                'duracion'          => $item->Duracion,
                'gestiones'         => $item->Gestiones,
                // Cálculos de asistencia
                'minutos_retraso'   => $retrasoMinutos,
                'tiene_retraso'     => $retrasoMinutos > 5, // Ajustado a estricto mayor a 5 minutos
            ];
        })
        // 🚀 4. FILTRADO INTELIGENTE: Solo dejamos pasar los que tienen retraso > 5 O tienen un permiso activo
        ->filter(function ($item) {
            $tieneRetrasoConsiderable = $item['minutos_retraso'] > 5;
            $tienePermiso = !empty($item['nombre_permiso']) || !empty($item['tipo_codigo']);

            return $tieneRetrasoConsiderable || $tienePermiso;
        })
        ->values(); // Resetea los índices de la colección para que viaje como un array limpio a Angular

        return response()->json([
            'status' => 'success',
            'data'   => $resultado
        ]);
    }
}