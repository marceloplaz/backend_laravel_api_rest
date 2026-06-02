<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KardexVacacion;
use App\Models\Vacacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KardexVacacionController extends Controller
{
    
    public function store(Request $request)
{
    $request->validate([
        'user_id'     => 'required|exists:users,id',
        'tipo'        => 'required|in:INGRESO,SALIDA',
        'gestion_id'  => 'required|exists:gestiones,id',
        'servicio_id' => 'required',
    ]);

    return DB::transaction(function () use ($request) {
        
        $ultimoMovimiento = KardexVacacion::where('user_id', $request->user_id)//SALDO ACTUAL DE VACACION
                            ->orderBy('id', 'desc')
                            ->lockForUpdate()
                            ->first();
                            
        $saldoAnterior = $ultimoMovimiento ? (int)$ultimoMovimiento->saldo_restante : 0;

        $kardex = new KardexVacacion();
        $kardex->user_id     = $request->user_id;
        $kardex->tipo         = $request->tipo;
        $kardex->servicio_id = $request->servicio_id;
        $kardex->gestion_id  = $request->gestion_id;

        if ($request->tipo === 'INGRESO') {
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
            'saldo_total'     => $vacacionPrincipal->saldo_total - $kardex->dias_solicitados,
        ]);                   //saldo total de vacacion-dias solicitados, se almacena en vacacion principal 
    }
}

return response()->json([
    'res' => true,
    'mensaje' => 'Registro procesado y saldo actualizado con éxito',
    'data' => $kardex
]);
    });
}

    
public function mostrarHistorial($user_id)// cargamos el historial
{
      $historial = KardexVacacion::with(['gestion', 'servicio','user.persona'])
        ->where('user_id', $user_id)
        ->orderBy('gestiones_cumplidas', 'desc')
        ->get();

    return response()->json([
        'res' => true,
        'data' => $historial // en data viaja los datos e historial
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