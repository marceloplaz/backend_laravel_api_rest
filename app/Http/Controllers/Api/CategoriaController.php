<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function index(Request $request)
    {
        $query = Categoria::query();

        // Filtro por nivel
        if ($request->has('nivel')) {
            $query->where('nivel', $request->nivel);
        }

        return response()->json($query->orderBy('nivel')->get());
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