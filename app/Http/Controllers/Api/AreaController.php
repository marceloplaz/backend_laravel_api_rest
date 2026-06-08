<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Area; // Importamos el modelo

class AreaController extends Controller
{
    /**
     * Obtiene las áreas vinculadas a un servicio específico con su categoría
     */
    public function getPorServicio($servicioId)
    {
        try {
            // Usamos Eloquent con with() para traer la categoría relacionada
            $areas = Area::with('categoria')
                         ->where('servicio_id', $servicioId)
                         ->orderBy('nombre', 'asc')
                         ->get();
            
            return response()->json($areas, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener áreas', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Guarda una nueva área incluyendo la categoría
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre'       => 'required|string|max:255',
            'servicio_id'  => 'required|exists:servicios,id',
            'categoria_id' => 'required|exists:categorias,id' // Validamos la categoría
        ]);

        try {
            // Eloquent crea el registro de forma más legible
            $area = Area::create([
                'nombre'       => $request->nombre,
                'servicio_id'  => $request->servicio_id,
                'categoria_id' => $request->categoria_id,
            ]);

            return response()->json(['status' => 'success', 'data' => $area], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al guardar el área', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Elimina un área por su ID
     */
    public function destroy($id)
    {
        try {
            $area = Area::find($id);
            
            if (!$area) {
                return response()->json(['message' => 'Área no encontrada'], 404);
            }

            $area->delete();
            return response()->json(['status' => 'success', 'message' => 'Área eliminada'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar', 'error' => $e->getMessage()], 500);
        }
    }
}