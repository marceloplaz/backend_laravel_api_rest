<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gestion;
use Illuminate\Http\Request;

class GestionController extends Controller
{
    public function index()
{
    $anioActual = (int) date('Y');
    $anioLimite = 2036;

    for ($anio = 2026; $anio <= $anioLimite; $anio++) {
        Gestion::firstOrCreate(
            ['año' => $anio],
            ['activo' => ($anio == $anioActual ? 1 : 0)]
        );
    }

    // Mapeamos el resultado para cambiar 'año' por 'anio'
    $gestiones = Gestion::orderBy('año', 'asc')->get()->map(function ($item) {
        return [
            'id' => $item->id,
            'anio' => $item->año, // <-- Se expone como 'anio' para Angular
            'activo' => $item->activo
        ];
    });

    return response()->json($gestiones);
}
}