<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IncidenciaTecnica;
use Illuminate\Http\Request;

class IncidenciaController extends Controller
{
    // Listar incidencias (para el técnico)
    public function index() {
        return IncidenciaTecnica::with(['usuario.persona', 'servicio'])
            ->orderBy('fecha', 'desc')
            ->get();
    }

    public function incidenciasCalendario(Request $request) {
    // Obtenemos incidencias de un mes específico
    $incidencias = IncidenciaTecnica::whereMonth('fecha', $request->mes)
        ->select('id', 'fecha', 'categoria', 'estado')
        ->get();

    return response()->json($incidencias);
}
    // Guardar reporte (desde la pantalla de turnos)
    public function store(Request $request) {
        $categoriasValidas = implode(',', [
        'luz', 'agua', 'equipo medico', 'computacion'
        ]);

        $request->validate([
        'categoria' => "required|in:$categoriasValidas", // Valida que sea una de la lista
        'servicio_id' => 'required',
        'fecha' => 'required|date',
        'descripcion' => 'required'
    ]);
   
    

        $incidencia = IncidenciaTecnica::create([
            'usuario_id'  => auth()->id(),
            'servicio_id' => $request->servicio_id,
            'categoria'   => $request->categoria,
            'fecha'       => $request->fecha,
            'descripcion' => $request->descripcion,
            'prioridad'   => $request->prioridad ?? 'media',
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
            'estado' => $request->estado, // 'en_proceso' o 'solucionado'
            'observacion_tecnica' => $request->observacion_tecnica,
            'fecha_solucion' => $request->estado == 'solucionado' ? now() : null
        ]);

        return response()->json(["message" => "Estado de incidencia actualizado"]);
    }
}