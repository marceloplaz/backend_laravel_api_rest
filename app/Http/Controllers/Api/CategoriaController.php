<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Categoria;

class CategoriaController extends Controller
{
    public function index(Request $request)
{
    try {
        $query = Categoria::query();

        // Filtro por nivel: Validamos que si existe, sea un dato procesable
        if ($request->has('nivel') && $request->nivel !== null) {
            $query->where('nivel', $request->nivel);
        }

        // Obtenemos los resultados ordenados
        $categorias = $query->orderBy('nivel', 'asc')->get();

        return response()->json($categorias, 200);

    } catch (\Exception $e) {
        // Esto te dirá en la pestaña 'Preview' de Chrome el error real (ej. columna no encontrada)
        return response()->json([
            'message' => 'Error interno en el servidor al obtener categorías',
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ], 500);
    }
}
    public function show(Categoria $categoria)
{
    return response()->json(
        $categoria->load('users')
    );
}
    public function store(CategoriaRequest $request)
    {
        $categoria = Categoria::create($request->validated());

        return response()->json($categoria, 201);
    }



    public function update(CategoriaRequest $request, Categoria $categoria)
    {
        $categoria->update($request->validated());

        return response()->json($categoria);
    }

    public function destroy(Categoria $categoria)
    {
        $categoria->delete();

        return response()->json([
            'message' => 'Categoría eliminada correctamente'
        ]);
    }
}