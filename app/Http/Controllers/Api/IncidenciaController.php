<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IncidenciaTecnica;
use Illuminate\Http\Request;

class IncidenciaController extends Controller
{
       public function index() {
             return IncidenciaTecnica::with(['usuario.persona', 'servicio']) // QUIEN REALIZA EL REPORTE Y SERVICIO, LISTAR
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function incidenciasCalendario(Request $request) {
        $request->validate(['mes' => 'required|integer']);
            $incidencias = IncidenciaTecnica::whereMonth('fecha', $request->mes)
            ->select('id', 'fecha', 'estado', 'prioridad', 'servicio_id')
            ->get();
        return response()->json($incidencias);
    }

  
    public function store(Request $request) {
        $request->validate([
            'servicio_id' => 'required|exists:servicios,id',
            'fecha'       => 'required|date',
            'descripcion' => 'required|string|min:10',
            'prioridad'   => 'nullable|in:baja,media,alta'
        ]);

        $incidencia = IncidenciaTecnica::create([
            'usuario_id'  => auth()->id() ?? 1, 
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

    // Actualizar estado (CUANDO EL PROBLEMA FUE RESUELTO)
    public function resolver(Request $request, $id) {
        $incidencia = IncidenciaTecnica::findOrFail($id);
        
        $incidencia->update([
            'estado' => $request->estado, // pendiente, solucionado
            'observacion_tecnica' => $request->observacion_tecnica,
            'fecha_solucion' => $request->estado == 'solucionado' ? now() : null
        ]);

        return response()->json(["message" => "INCIDDENCIA SOLUCIONADA"]);
    }
}