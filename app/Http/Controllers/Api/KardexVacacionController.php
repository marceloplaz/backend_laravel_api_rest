<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KardexVacacion;
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
            'user_id'             => 'required|exists:users,id',
            'gestiones_cumplidas' => 'required|string', // Ej: 2021-2022
            'cas_calificacion'    => 'required|integer', 
            'dias_derecho'        => 'required|integer',
        ]);

        // Realizamos el cálculo matemático para el saldo inicial de esta fila
        $cas = (int) $request->cas_calificacion;
        $derecho = (int) $request->dias_derecho;
        $saldoInicial = $cas + $derecho;

        $kardex = KardexVacacion::updateOrCreate(
            ['id' => $request->id], // Si envías el ID desde Angular, se edita el registro
            [
                'user_id'             => $request->user_id,
                'gestion_id'          => $request->gestion_id,
                'servicio_id'         => $request->servicio_id,
                'gestiones_cumplidas' => $request->gestiones_cumplidas,
                'cas_calificacion'    => $cas,
                'dias_derecho'        => $derecho,
                'saldo_restante'      => $saldoInicial, 
                'descripcion'         => $request->descripcion,
                'fecha_inicio'        => $request->fecha_inicio ?? now(),
                'fecha_fin'           => $request->fecha_fin ?? now(),
                'dias_solicitados'    => 0,
                'estado'              => 1, // Pendiente/Activo
            ]
        );

        return response()->json([
            'res' => true,
            'mensaje' => 'Registro de Kardex guardado correctamente',
            'data' => $kardex
        ]);
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