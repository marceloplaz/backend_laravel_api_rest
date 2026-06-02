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

        if ($request->has('nivel') && $request->nivel !== null) {
            $query->where('nivel', $request->nivel);
        }

         $categorias = $query->orderBy('nivel', 'asc')->get();

        return response()->json($categorias, 200);

    } catch (\Exception $e) {
     
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
        $categoria->load('users') //NECESITAMOS CAMBIAR POR NOMBRE_COPLETO
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