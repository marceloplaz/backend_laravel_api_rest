<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Servicio;
use Illuminate\Http\Request;

class ServicioTurnoController extends Controller
{
public function getTurnosHabilitados($id)
    {
        try {
            $servicio = Servicio::with('turnos')->find($id);

            if (!$servicio) {
                return response()->json(['message' => 'Servicio no encontrado'], 404);
            }

            // Retornamos los turnos asociados
            return response()->json([
                'data' => $servicio->turnos
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

public function vincularTurnos(Request $request)
    {
        $request->validate([
            'servicio_id' => 'required|exists:servicios,id',
            'turnos_ids' => 'present|array', // present permite que el array venga vacío si se desmarcan todos
        ]);

        try {
            $servicio = Servicio::findOrFail($request->servicio_id);

            // Usamos sync() para actualizar la tabla intermedia:
            // Borra los que ya no están y agrega los nuevos automáticamente.
            $servicio->turnos()->sync($request->turnos_ids);

            return response()->json([
                'message' => 'Configuración de turnos actualizada correctamente',
                'data' => $servicio->load('turnos')->turnos
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al sincronizar: ' . $e->getMessage()], 500);
        }
    }


public function asignar(Request $request, $servicioId)
{
    $servicio = Servicio::findOrFail($servicioId);
    // Esto es lo que llena la tabla servicio_turno
    $servicio->turnos()->syncWithoutDetaching([$request->turno_id]);

    return response()->json(['message' => 'Turno autorizado en este servicio']);
}

 
public function sync(Request $request, $servicioId)
{
    $request->validate([
        'turnos' => 'required|array',
        'turnos.*' => 'exists:turnos,id' // Validar cada ID dentro del array
    ]);

    $servicio = Servicio::findOrFail($servicioId);
    $servicio->turnos()->sync($request->turnos);

    return response()->json([
        'message' => 'Turnos sincronizados correctamente',
        'data' => $servicio->load('turnos') // Cargamos los turnos para devolverlos
    ]);
}

    public function quitar($servicioId, $turnoId)
    {
        $servicio = Servicio::findOrFail($servicioId);

        $servicio->turnos()->detach($turnoId);

        return response()->json([
            'message' => 'Turno eliminado del servicio'
        ]);
    }
}