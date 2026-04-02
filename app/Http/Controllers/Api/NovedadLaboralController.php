<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NovedadLaboral;
use App\Models\TurnoAsignado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NovedadLaboralController extends Controller
{
    /**
     * Procesa el intercambio de turnos por motivos administrativos (Baja, Permiso, etc.)
     */
    public function permutarConNovedad(Request $request)
    {
        $request->validate([
            'id_origen'    => 'required|exists:turnos_asignados,id',
            'id_destino'   => 'required|exists:turnos_asignados,id',
            'tipo_novedad' => 'required|in:permiso,baja_medica,vacacion,licencia,devolucion_turno',
            'observacion'  => 'nullable|string'
        ]);

        try {
            return DB::transaction(function () use ($request) {
                // 1. Cargar los turnos con sus relaciones para las observaciones
                $turnoA = TurnoAsignado::with('usuario.persona')->findOrFail($request->id_origen);
                $turnoB = TurnoAsignado::with('usuario.persona')->findOrFail($request->id_destino);

                // Datos temporales para el SWAP (Intercambio)
                $fechaA = $turnoA->fecha;
                $semanaA = $turnoA->semana_id;
                $nombreA = $turnoA->usuario->persona->nombre_completo ?? 'Personal A';
                $nombreB = $turnoB->usuario->persona->nombre_completo ?? 'Personal B';

                // 2. EJECUTAR EL INTERCAMBIO FÍSICO EN LA BD
                // El turno A se "muda" a la fecha y semana del turno B
                $turnoA->update([
                    'fecha'       => $turnoB->fecha,
                    'semana_id'   => $turnoB->semana_id,
                    'estado'      => $request->tipo_novedad,
                    'observacion' => "Movido por {$request->tipo_novedad}. Estaba el {$fechaA}"
                ]);

                // El turno B se "muda" a la fecha y semana original de A
                $turnoB->update([
                    'fecha'       => $fechaA,
                    'semana_id'   => $semanaA,
                    'observacion' => "Intercambio por novedad con {$nombreA}"
                ]);

                // 3. REGISTRAR EL HISTORIAL EN LA NUEVA TABLA
                NovedadLaboral::create([
                    'asignacion_id'          => $turnoA->id,
                    'tipo_novedad'           => $request->tipo_novedad,
                    'usuario_solicitante_id' => $turnoA->usuario_id,
                    'usuario_reemplazo_id'   => $turnoB->usuario_id,
                    'fecha_original'         => $fechaA,
                    'fecha_nueva'            => $turnoB->fecha,
                    'observacion_detalle'    => $request->observacion ?? "Permuta: {$nombreA} <-> {$nombreB}"
                ]);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Desplazamiento físico y novedad registrados correctamente.'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error en el servidor: ' . $e->getMessage()
            ], 500);
        }
    }
}