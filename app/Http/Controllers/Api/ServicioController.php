<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Servicio;
use App\Models\Area;
use Illuminate\Http\Request;
use App\Http\Requests\ServicioStoreRequest;
use App\Http\Requests\ServicioUpdateRequest;
use App\Http\Resources\UsuarioServicioResource;
use App\Http\Resources\ServicioResource;
class ServicioController extends Controller
{
    // LISTAR

    public function getAreas(Request $request)
    {
        // Validamos que venga el servicio_id
        $servicioId = $request->query('servicio_id');

        if (!$servicioId) {
            return response()->json(['message' => 'El servicio_id es obligatorio'], 400);
        }

        // Buscamos las áreas que pertenecen a ese servicio
        $areas = Area::where('servicio_id', $servicioId)->get();

        return response()->json([
            'success' => true,
            'data' => $areas
        ]);
    }

    public function index()
    {
        return response()->json([ 'data' => Servicio::all()  ], 200);
    }

      public function store(ServicioStoreRequest $request)
    {
        $servicio = Servicio::create($request->validated());
        return response()->json([ 'message' => 'Servicio creado correctamente','data' => $servicio
        ], 201);
    }
    public function show($id)
    {
         $servicio = Servicio::with('usuarios')->findOrFail($id);
         return new ServicioResource($servicio);
    }

    // ACTUALIZAR
    public function update(ServicioUpdateRequest $request, $id)
{
    $servicio = Servicio::findOrFail($id);
    
    $servicio->update($request->validated());

    // Aquí usas tu ServicioResource para devolver la respuesta limpia
    return new ServicioResource($servicio);
}

    // ELIMINAR
    public function destroy($id)
    {
        Servicio::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Servicio eliminado correctamente'
        ], 200);
    }
}