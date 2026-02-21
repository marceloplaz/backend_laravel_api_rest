<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Servicio;
use Illuminate\Http\Request;

class ServicioTurnoController extends Controller
{
    public function asignar(Request $request, $servicioId)
    {
        $request->validate([
            'turno_id' => 'required|exists:turnos,id'
        ]);

        $servicio = Servicio::findOrFail($servicioId);

        $servicio->turnos()->attach($request->turno_id);

        return response()->json([
            'message' => 'Turno asignado correctamente'
        ]);
    }

    public function sync(Request $request, $servicioId)
    {
        $request->validate([
            'turnos' => 'required|array'
        ]);

        $servicio = Servicio::findOrFail($servicioId);

        $servicio->turnos()->sync($request->turnos);

        return response()->json([
            'message' => 'Turnos sincronizados correctamente'
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