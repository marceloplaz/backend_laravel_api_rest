<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comida;

class ComidaController extends Controller
{
    public function index()
    {
        // Retorna las comidas activas para alimentar los checkboxes en el frontend
        $comidas = Comida::where('estado', true)->get(['id', 'nombre', 'codigo']);
        return response()->json($comidas);
    }
}
