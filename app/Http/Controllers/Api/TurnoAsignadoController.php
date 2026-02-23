<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TurnoAsignado;
use App\Models\Semana;
use App\Models\Turno;
use App\Models\Servicio;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TurnoAsignadoController extends Controller
{
    /**
     * 1. REGISTRAR TURNO
     * Validamos que el usuario solo use turnos de SU categoría y en servicios permitidos.
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'servicio_id' => 'required|exists:servicios,id',
            'turno_id'    => 'required|exists:turnos,id',
            'fecha'       => 'required|date',
            'observacion' => 'nullable|string|max:500'
        ]);

        $fechaFormateada = Carbon::parse($request->fecha)->format('Y-m-d');

        // SEGURIDAD: ¿Este turno es de mi categoría y está asignado a este servicio?
        $esValido = Turno::where('id', $request->turno_id)
            ->where('categoria_id', $user->categoria_id)
            ->whereHas('servicios', function($q) use ($request) {
                $q->where('servicios.id', $request->servicio_id);
            })->exists();

        if (!$esValido) {
            return response()->json([
                'message' => "Acceso denegado: Tu categoría ({$user->categoria->nombre}) no está autorizada para este servicio o turno."
            ], 403);
        }

        // LÓGICA DE CALENDARIO
        $semana = Semana::where('fecha_inicio', '<=', $fechaFormateada)
            ->where('fecha_fin', '>=', $fechaFormateada)
            ->with('mes.gestion')
            ->first();

        if (!$semana) {
            return response()->json(['message' => 'La fecha no existe en el calendario configurado.'], 422);
        }

        // EVITAR DUPLICADOS
        $existe = TurnoAsignado::where('usuario_id', $user->id)
            ->where('fecha', $fechaFormateada)
            ->where('turno_id', $request->turno_id)
            ->exists();

        if ($existe) {
            return response()->json(['message' => 'Ya tienes este turno asignado en esa fecha.'], 422);
        }

        $asignacion = TurnoAsignado::create([
            'usuario_id'  => $user->id,
            'servicio_id' => $request->servicio_id,
            'turno_id'    => $request->turno_id,
            'semana_id'   => $semana->id,
            'mes_id'      => $semana->mes_id,
            'gestion_id'  => $semana->mes->gestion_id,
            'fecha'       => $fechaFormateada,
            'estado'      => 'programado',
            'observacion' => $request->observacion
        ]);

        return response()->json(['message' => 'Turno asignado correctamente', 'data' => $asignacion], 201);
    }

    /**
     * 2. VISIBILIDAD POR JERARQUÍA (Dashboard de equipo)
     * Ajustado parámetro a $servicioId para coincidir con api.php
     */
    public function verTurnosPorJerarquia(Request $request, $servicioId)
    {
        $user = auth()->user();
        $nivelUsuario = $user->categoria->nivel; 
        
        $fechaConsulta = $request->query('fecha', now()->format('Y-m-d'));

        // Verificamos si el servicio existe
        if(!Servicio::find($servicioId)) {
            return response()->json(['message' => 'Servicio no encontrado.'], 404);
        }

        $turnos = TurnoAsignado::with(['usuario.persona', 'usuario.categoria', 'turno'])
            ->where('servicio_id', $servicioId)
            ->where('fecha', $fechaConsulta)
            ->whereHas('usuario.categoria', function($query) use ($nivelUsuario) {
                $query->where('nivel', '>=', $nivelUsuario);
            })
            ->get()
            ->groupBy('usuario.categoria.nombre');

        return response()->json([
            'fecha' => $fechaConsulta,
            'mi_nivel' => $nivelUsuario,
            'equipo_visible' => $turnos
        ]);
    }

    /**
     * 3. REPORTE MENSUAL
     */
    public function reporteMensual($mes_id)
    {
        $user = auth()->user();
        $nivelMinimo = $user->categoria->nivel;

        $turnos = TurnoAsignado::with(['usuario.persona', 'usuario.categoria', 'servicio', 'turno'])
            ->where('mes_id', $mes_id)
            ->whereHas('usuario.categoria', function($q) use ($nivelMinimo) {
                $q->where('nivel', '>=', $nivelMinimo);
            })
            ->orderBy('fecha', 'asc')
            ->get();

        return response()->json($turnos);
    }

    /**
     * 4. MIS TURNOS
     */
    public function misTurnos()
    {
        $turnos = TurnoAsignado::with(['servicio', 'turno', 'semana.mes'])
            ->where('usuario_id', auth()->id())
            ->orderBy('fecha', 'asc')
            ->get();

        return response()->json($turnos);
    }
}