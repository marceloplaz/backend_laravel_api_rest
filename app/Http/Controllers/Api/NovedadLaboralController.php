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
        'id_destino'   => 'nullable|exists:turnos_asignados,id', // Puede ser opcional
        'usuario_reemplazo_id' => 'required_if:id_destino,null|exists:users,id',
        'tipo_novedad' => 'required|in:permiso,baja_medica,vacacion,licencia,devolucion_turno',
        'observacion'  => 'nullable|string'
    ]);

    try {
        return DB::transaction(function () use ($request) {
            $turnoA = TurnoAsignado::with('usuario.persona')->findOrFail($request->id_origen);
            
            // Datos para el historial
            $fechaOriginalA = $turnoA->fecha;
            $usuarioA = $turnoA->usuario_id;
            $nombreA = $turnoA->usuario->persona->nombre_completo ?? 'Personal A';

            if ($request->filled('id_destino')) {
                // CASO A: INTERCAMBIO (SWAP)
                $turnoB = TurnoAsignado::with('usuario.persona')->findOrFail($request->id_destino);
                $nombreB = $turnoB->usuario->persona->nombre_completo ?? 'Personal B';

                // Intercambio de datos
                $fechaB = $turnoB->fecha;
                $semanaB = $turnoB->semana_id;

                $turnoA->update([
                    'fecha' => $fechaB,
                    'semana_id' => $semanaB,
                    'estado' => $request->tipo_novedad
                ]);

                $turnoB->update([
                    'fecha' => $fechaOriginalA,
                    'semana_id' => $turnoA->semana_id
                ]);

                $reemplazoId = $turnoB->usuario_id;
                $msg = "Intercambio realizado entre {$nombreA} y {$nombreB}.";
            } else {
                // CASO B: REASIGNACIÓN SIMPLE (Baja/Permiso sin intercambio)
                $turnoA->update([
                    'usuario_id' => $request->usuario_reemplazo_id,
                    'estado' => $request->tipo_novedad
                ]);
                
                $reemplazoId = $request->usuario_reemplazo_id;
                $msg = "Turno reasignado exitosamente.";
            }

            // 3. REGISTRAR EN TABLA DE NOVEDADES
            NovedadLaboral::create([
                'asignacion_id'          => $turnoA->id,
                'tipo_novedad'           => $request->tipo_novedad,
                'usuario_solicitante_id' => $usuarioA,
                'usuario_reemplazo_id'   => $reemplazoId,
                'fecha_original'         => $fechaOriginalA,
                'fecha_nueva'            => $turnoA->fecha,
                'observacion_detalle'    => $request->observacion ?? $msg
            ]);

            return response()->json(['status' => 'success', 'message' => $msg]);
        });
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}
}