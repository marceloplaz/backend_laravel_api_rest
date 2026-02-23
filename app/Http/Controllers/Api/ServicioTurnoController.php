<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Servicio;
use Illuminate\Http\Request;

class ServicioTurnoController extends Controller
{
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