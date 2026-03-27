<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IncidenciaTecnica;
use Illuminate\Http\Request;

class IncidenciaController extends Controller
{
    // Listar incidencias para el técnico
    public function index() {
        // Cargamos relaciones para saber quién reporta y en qué servicio
        return IncidenciaTecnica::with(['usuario.persona', 'servicio'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function incidenciasCalendario(Request $request) {
        $request->validate(['mes' => 'required|integer']);

        // Obtenemos incidencias de un mes específico
        $incidencias = IncidenciaTecnica::whereMonth('fecha', $request->mes)
            ->select('id', 'fecha', 'estado', 'prioridad', 'servicio_id')
            ->get();

        return response()->json($incidencias);
    }

    // Guardar reporte
    public function store(Request $request) {
        $request->validate([
            'servicio_id' => 'required|exists:servicios,id',
            'fecha'       => 'required|date',
            'descripcion' => 'required|string|min:10',
            'prioridad'   => 'nullable|in:baja,media,alta'
        ]);

        $incidencia = IncidenciaTecnica::create([
            'usuario_id'  => auth()->id() ?? 1, // Fallback a 1 si no hay auth para pruebas
            'servicio_id' => $request->servicio_id,
            'fecha'       => $request->fecha,
            'descripcion' => $request->descripcion,
            'prioridad'   => $request->prioridad ?? 'media',
            'estado'      => 'pendiente',
        ]);

        return response()->json([
            "message" => "Reporte técnico enviado al equipo de mantenimiento",
            "data" => $incidencia
        ], 201);
    }

    // Actualizar estado (cuando el técnico lo arregla)
    public function resolver(Request $request, $id) {
        $incidencia = IncidenciaTecnica::findOrFail($id);
        
        $incidencia->update([
            'estado' => $request->estado, // 'pendiente', 'en_proceso' o 'solucionado'
            'observacion_tecnica' => $request->observacion_tecnica,
            'fecha_solucion' => $request->estado == 'solucionado' ? now() : null
        ]);

        return response()->json(["message" => "Estado de incidencia actualizado correctamente"]);
    }
}