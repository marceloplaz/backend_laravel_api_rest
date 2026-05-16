<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KardexVacacion;
use App\Models\Vacacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KardexVacacionController extends Controller
{
    /**
     * Guarda un nuevo registro o actualiza uno existente en la Tarjeta de Control.
     */
    public function store(Request $request)
{
    $request->validate([
        'user_id'     => 'required|exists:users,id',
        'tipo'        => 'required|in:INGRESO,SALIDA',
        'gestion_id'  => 'required|exists:gestiones,id',
        'servicio_id' => 'required',
    ]);

    return DB::transaction(function () use ($request) {
        
        // 1. Obtener saldo actual bloqueando la fila para seguridad
        $ultimoMovimiento = KardexVacacion::where('user_id', $request->user_id)
                            ->orderBy('id', 'desc')
                            ->lockForUpdate()
                            ->first();
                            
        $saldoAnterior = $ultimoMovimiento ? (int)$ultimoMovimiento->saldo_restante : 0;

        $kardex = new KardexVacacion();
        $kardex->user_id     = $request->user_id;
        $kardex->tipo         = $request->tipo; // Asegúrate de que esté en el $fillable del modelo
        $kardex->servicio_id = $request->servicio_id;
        $kardex->gestion_id  = $request->gestion_id;

        if ($request->tipo === 'INGRESO') {
            // Lógica de Ingreso
            $kardex->gestiones_cumplidas = $request->gestiones_cumplidas;
            $kardex->cas_calificacion    = (int)$request->cas_calificacion;
            $kardex->dias_derecho        = (int)$request->dias_derecho;
            $kardex->dias_solicitados    = 0;
            $kardex->fecha_solicitud     = $request->fecha_solicitud;

            // IMPORTANTE: Ponemos fechas en null ya que es un ingreso administrativo
            $kardex->fecha_inicio = null;
            $kardex->fecha_fin    = null;
            $kardex->fecha_retorno       = null;

            $kardex->saldo_restante = $saldoAnterior + $kardex->cas_calificacion + $kardex->dias_derecho;
            $kardex->descripcion    = $request->descripcion ?? "Carga de Gestión {$request->gestiones_cumplidas}";
        } 
        else {
            // Lógica de Salida (Resta)
            $diasAPermitir = (int)$request->dias_solicitados;

            if ($saldoAnterior < $diasAPermitir) {
                return response()->json([
                    'res' => false,
                    'mensaje' => "Saldo insuficiente. El personal solo tiene {$saldoAnterior} días disponibles."
                ], 422);
            }

            $kardex->gestiones_cumplidas = "USO DE VACACIÓN";
            $kardex->dias_solicitados    = $diasAPermitir;
            $kardex->cas_calificacion    = 0;
            $kardex->dias_derecho        = 0;
            
            $kardex->saldo_restante = $saldoAnterior - $diasAPermitir;
            
            $kardex->fecha_inicio    = $request->fecha_inicio;
            $kardex->fecha_fin       = $request->fecha_fin;
            $kardex->fecha_retorno   = $request->fecha_retorno;
            $kardex->fecha_solicitud = $request->fecha_solicitud;

            $kardex->descripcion    = $request->descripcion ?? "Salida programada";
        }

        $kardex->estado = 1;
        $kardex->save();

        
            
 if ($kardex->tipo === 'SALIDA') {
    $vacacionPrincipal = Vacacion::where('usuario_id', $kardex->usuario_id)->first();

    if ($vacacionPrincipal) {
        $vacacionPrincipal->update([
            'fecha_inicio'    => $kardex->fecha_inicio,
            'fecha_fin'       => $kardex->fecha_fin,
            // Importante: Restamos los días solicitados del saldo_total
            // Asegúrate de que el campo en tu tabla 'vacaciones' se llame 'saldo_total'
            'saldo_total'     => $vacacionPrincipal->saldo_total - $kardex->dias_solicitados,
        ]);
    }
}

return response()->json([
    'res' => true,
    'mensaje' => 'Registro procesado y saldo actualizado con éxito',
    'data' => $kardex
]);
    });
}

    /**
     * Obtiene todo el historial de un usuario específico para mostrar en el modal.
     */
public function mostrarHistorial($user_id)
{
    // Obtenemos todos los campos del kardex para este usuario específico
    $historial = KardexVacacion::with(['gestion', 'servicio'])
        ->where('user_id', $user_id)
        ->orderBy('gestiones_cumplidas', 'desc')
        ->get();

    return response()->json([
        'res' => true,
        'data' => $historial // Aquí viajan cas_calificacion, gestiones_cumplidas, saldo_restante, etc.
    ]);
}

public function destroy($id)
    {
        $registro = KardexVacacion::find($id);

        if (!$registro) {
            return response()->json([
                'res' => false,
                'mensaje' => 'El registro no existe'
            ], 404);
        }

        $registro->delete();

        return response()->json([
            'res' => true,
            'mensaje' => 'Registro eliminado correctamente'
        ]);
    }
}