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
    
public function index()
{
    return NovedadLaboral::with([
        'solicitante.persona', 
        'reemplazo.persona',
        'asignacion.servicio', // Trae el servicio del turno original
        'asignacion.turno'     // Trae el nombre del turno (Mañana/Tarde)
    ])->orderBy('created_at', 'desc')->get();
}     


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
                'con_devolucion'         => 0,
                'observacion_detalle'    => $request->observacion ?? $msg
            ]);

            return response()->json(['status' => 'success', 'message' => $msg]);
        });
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}
public function marcarDevolucion($id)
{
    try {
        $novedad = NovedadLaboral::findOrFail($id);
        
        // Cambio manual y guardado directo
        $novedad->con_devolucion = 1; 
        $novedad->save(); 

        return response()->json([
            'status' => 'success',
            'message' => 'Turno devuelto con éxito'
        ], 200);
    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], 500);
    }
}

public function store(Request $request)
{
    $request->validate([
        'id_origen' => 'required|exists:asignaciones,id',
        'usuario_reemplazo_id' => 'required|exists:usuarios,id',
        'tipo_novedad' => 'required|string',
        'observacion' => 'nullable|string|max:250',
        'con_devolucion' => 'nullable|integer' // Recibimos 0 o 1 desde Angular
    ]);

    try {
        DB::beginTransaction();

        // 1. Creamos el registro en la tabla novedades_laborales
        $novedad = NovedadLaboral::create([
            'asignacion_id' => $request->id_origen,
            'tipo_novedad' => $request->tipo_novedad,
            'usuario_solicitante_id' => auth()->id(), // El usuario que está logueado
            'usuario_reemplazo_id' => $request->usuario_reemplazo_id,
            'fecha_original' => DB::table('asignaciones')->where('id', $request->id_origen)->value('fecha'),
            'con_devolucion' => $request->con_devolucion ?? 0,
            'observacion_detalle' => $request->observacion,
        ]);

        // 2. Opcional: Actualizar el estado del turno original
        // DB::table('asignaciones')->where('id', $request->id_origen)->update(['estado' => 'cubierto_por_novedad']);

        DB::commit();
        return response()->json(['message' => 'Novedad registrada correctamente', 'data' => $novedad], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['error' => 'Error al registrar: ' . $e->getMessage()], 500);
    }
}
public function show($id)
{
    // Buscamos la novedad con TODAS sus relaciones
    $novedad = NovedadLaboral::with([
        'solicitante.persona', 
        'reemplazo.persona', 
        'asignacion.servicio', 
        'asignacion.turno'
    ])->find($id);

    if (!$novedad) {
        return response()->json(['message' => 'Novedad no encontrada'], 404);
    }

    return response()->json($novedad);
}

}