<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NovedadLaboral;
use App\Models\TurnoAsignado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
        'id_origen'            => 'required|exists:turnos_asignados,id',
        'usuario_reemplazo_id' => 'required|exists:users,id',
        'tipo_novedad'         => 'required|in:permiso,baja_medica,vacacion,licencia,devolucion_turno',
        'fecha_devolucion'     => 'nullable|date',
        'observacion'          => 'nullable|string'
    ]);

    try {
        return DB::transaction(function () use ($request) {
            // 1. Obtener el turno origen
            $turnoA = TurnoAsignado::findOrFail($request->id_origen);
            $solicitanteId = $turnoA->usuario_id; 
            $reemplazoId = $request->usuario_reemplazo_id;

            // 2. Ejecutar cambio de dueño
            $turnoA->usuario_id = $reemplazoId;
            $turnoA->estado = $request->tipo_novedad;
            $turnoA->save();

            // 3. Devolución automática (Juan recupera un turno de Wilson)
            $conDevolucion = 0;
            if ($request->fecha_devolucion) {
                $turnoDevolucion = TurnoAsignado::where('usuario_id', $reemplazoId)
                    ->whereDate('fecha', $request->fecha_devolucion)
                    ->first();

                if ($turnoDevolucion) {
                    $turnoDevolucion->usuario_id = $solicitanteId;
                    $turnoDevolucion->save();
                    $conDevolucion = 1;
                }
            }

            // 4. Registrar la novedad
            $novedad = NovedadLaboral::create([
                'asignacion_id'          => $turnoA->id,
                'tipo_novedad'           => $request->tipo_novedad,
                'usuario_solicitante_id' => $solicitanteId,
                'usuario_reemplazo_id'   => $reemplazoId,
                'fecha_original'         => $turnoA->fecha,
                'fecha_devolucion'       => $request->fecha_devolucion,
                'con_devolucion'         => $conDevolucion,
                'observacion_detalle'    => $request->observacion ?? "Cambio automático realizado",
                'estado'                 => 'PROCESADO' 
            ]);

            $turnoA->refresh(); 

// 2. Cargamos la novedad con TODAS las personas involucradas de nuevo
$novedadCompleta = NovedadLaboral::with([
    'solicitante.persona', 
    'reemplazo.persona'
])->find($novedad->id);

return response()->json([
    'status' => 'success', 
    'message' => 'Novedad procesada correctamente.',
    'data' => $novedadCompleta 
]);


        });
    } catch (\Exception $e) {
        Log::error("Error en permutarConNovedad: " . $e->getMessage());
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

// NovedadLaboralController.php
public function registrarNovedad(Request $request)
{
    DB::beginTransaction();
    try {
        // 1. Crear el registro de la novedad (esto se queda igual para tu historial)
        $novedad = NovedadLaboral::create([
            'asignacion_id'          => $request->asignacion_id,
            'tipo_novedad'           => $request->tipo_novedad,
            'usuario_solicitante_id' => $request->usuario_actual_id, // SOFIA
            'usuario_reemplazo_id'   => $request->usuario_reemplazo_id, // EDSON
            'fecha_original'         => $request->fecha_turno,
            'con_devolucion'         => ($request->tipo_novedad == 'devolucion_turno') ? 1 : 0,
            'observacion_detalle'    => $request->observacion
        ]);

        // 2. PASO CLAVE: Anotación Informativa (SIN CAMBIAR usuario_id)
        
        // A. Turno del Solicitante (Sofia): Ella sigue en su fila, pero anotamos su reemplazo
        $turnoSolicitante = TurnoAsignado::find($request->asignacion_id);
        // Usamos campos de estado o meta-datos para no mover la fila
        $turnoSolicitante->estado = 'REEMPLAZADO'; 
        // Si añadiste las columnas que sugerimos:
        // $turnoSolicitante->reemplazo_por_id = $request->usuario_reemplazo_id;
        $turnoSolicitante->save();

        // B. Turno del Reemplazo (Edson): Buscamos su turno en la misma fecha
        $turnoReemplazo = TurnoAsignado::where('usuario_id', $request->usuario_reemplazo_id)
            ->whereDate('fecha', $request->fecha_turno)
            ->first();

        if ($turnoReemplazo) {
            $turnoReemplazo->estado = 'CUBRIENDO_TURNO';
            // $turnoReemplazo->cubriendo_a_id = $request->usuario_actual_id;
            $turnoReemplazo->save();
        }

        DB::commit();
        return response()->json([
            'message' => 'Novedad registrada: Sofia se mantiene en su fila y Edson en la suya.',
            'novedad' => $novedad
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

public function confirmar(Request $request, $id)
    {
        return DB::transaction(function () use ($id) {
            // 1. Inicio de traza
            Log::info("=== INICIANDO PROCESO DE CONFIRMACIÓN - NOVEDAD #$id ===");

            $novedad = NovedadLaboral::findOrFail($id);

            // 2. Validación del turno original (El que Juan le da a Wilson)
            if (!$novedad->turno_origen_id) {
                Log::warning("Falla: Novedad #$id no tiene vinculado un turno_origen_id.");
                return response()->json([
                    'res' => false,
                    'message' => "Error: La novedad no tiene un turno original asociado."
                ], 422);
            }

            $turnoOrigen = TurnoAsignado::find($novedad->turno_origen_id);

            if (!$turnoOrigen) {
                Log::error("Falla: El TurnoAsignado #{$novedad->turno_origen_id} no existe en la DB.");
                return response()->json([
                    'res' => false,
                    'message' => "No se encontró el turno original (ID: #{$novedad->turno_origen_id}). Verifique si fue eliminado."
                ], 404);
            }

            // 3. Ejecutar primer cambio: Juan -> Wilson (Jueves)
            Log::info("Paso 1: Moviendo turno #{$turnoOrigen->id} del solicitante (ID:{$novedad->solicitante_id}) al reemplazo (ID:{$novedad->usuario_reemplazo_id})");
            
            $turnoOrigen->usuario_id = $novedad->usuario_reemplazo_id;
            $turnoOrigen->save();

            // 4. Ejecutar segundo cambio: Wilson -> Juan (Domingo - Devolución)
            if ($novedad->fecha_devolucion) {
                Log::info("Paso 2: Buscando turno de Wilson para devolver el día: {$novedad->fecha_devolucion}");

                $turnoDevolucion = TurnoAsignado::where('usuario_id', $novedad->usuario_reemplazo_id)
                    ->whereDate('fecha', $novedad->fecha_devolucion)
                    ->first();

                if ($turnoDevolucion) {
                    Log::info("Éxito: Turno de devolución encontrado (ID: #{$turnoDevolucion->id}). Moviendo a solicitante original.");
                    
                    $turnoDevolucion->usuario_id = $novedad->solicitante_id;
                    $turnoDevolucion->save();
                } else {
                    Log::warning("Aviso: Wilson no tiene un turno asignado el {$novedad->fecha_devolucion}. No hay qué devolver.");
                    
                    // Si quieres que el proceso falle si no hay devolución, descomenta el return de abajo:
                    /*
                    return response()->json([
                        'res' => false,
                        'message' => "Se movió el primer turno, pero Wilson no tiene un turno asignado el domingo para devolver."
                    ], 422);
                    */
                }
            }

            // 5. Finalizar Novedad
            $novedad->con_devolucion = 1;
            $novedad->save();

            Log::info("=== PROCESO COMPLETADO EXITOSAMENTE PARA NOVEDAD #$id ===");

            return response()->json([
                'res' => true,
                'message' => 'Intercambio de turnos realizado correctamente.'
            ], 200);
        });
    }
}