<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TurnoAsignado;
use App\Models\Semana;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TurnoAsignadoController extends Controller
{
    /**
     * Registrar un nuevo turno asignado con lógica automática de calendario.
     */
    public function store(Request $request)
    {
        $request->validate([
            'servicio_id' => 'required|exists:servicios,id',
            'turno_id'    => 'required|exists:turnos,id',
            'fecha'       => 'required|date',
            'observacion' => 'nullable|string|max:500'
        ]);

        $fechaFormateada = Carbon::parse($request->fecha)->format('Y-m-d');

        // Buscar semana que contiene la fecha
        $semana = Semana::where('fecha_inicio', '<=', $fechaFormateada)
            ->where('fecha_fin', '>=', $fechaFormateada)
            ->with('mes.gestion')
            ->first();

        if (!$semana) {
            return response()->json([
                'message' => 'La fecha seleccionada no existe en el calendario configurado.'
            ], 422);
        }

        // Evitar duplicado (mismo usuario, misma fecha, mismo turno)
        $existe = TurnoAsignado::where('usuario_id', auth()->id())
            ->where('fecha', $fechaFormateada)
            ->where('turno_id', $request->turno_id)
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'Ya tienes este turno asignado en esa fecha.'
            ], 422);
        }

        $asignacion = TurnoAsignado::create([
            'usuario_id'  => auth()->id(),
            'servicio_id' => $request->servicio_id,
            'turno_id'    => $request->turno_id,
            'semana_id'   => $semana->id,
            'mes_id'      => $semana->mes_id,
            'gestion_id'  => $semana->mes->gestion_id,
            'fecha'       => $fechaFormateada,
            'estado'      => 'programado',
            'observacion' => $request->observacion
        ]);

        return response()->json([
            'message' => 'Turno asignado correctamente',
            'data' => $asignacion
        ], 201);
    }

    /**
     * Reporte de turnos por mes
     */
    public function reporteMensual($mes_id)
    {
        $turnos = TurnoAsignado::with([
                'usuario.persona',
                'servicio',
                'turno',
                'semana'
            ])
            ->where('mes_id', $mes_id)
            ->orderBy('fecha', 'asc')
            ->get();

        return response()->json($turnos);
    }

    /**
     * Turnos del usuario autenticado
     */
    public function misTurnos()
    {
        $turnos = TurnoAsignado::with([
                'servicio',
                'turno',
                'semana.mes'
            ])
            ->where('usuario_id', auth()->id())
            ->orderBy('fecha', 'asc')
            ->get();

        return response()->json($turnos);
    }
}