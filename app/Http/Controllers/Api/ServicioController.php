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
use Carbon\Carbon;
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

  public function inicio()
{
    $usuario = auth()->user();

    if (!$usuario) {
        return response()->json(['message' => 'No autorizado'], 401);
    }

    // Capturamos el mes y año actuales dinámicamente
    $mesActual = \Carbon\Carbon::now()->month;
    $anioActual = \Carbon\Carbon::now()->year;

    $servicios = Servicio::where(function($query) use ($usuario) {
            // Condición 1: Servicios vinculados fijos al usuario
            $query->whereHas('usuarios', function ($q) use ($usuario) {
                // CORREGIDO: Se cambió 'user_id' por 'usuario_id' para que coincida con tu tabla usuario_servicios
                $q->where('usuario_servicios.usuario_id', $usuario->id);
            });
        })
        // Condición 2: Filtro por Usuario, MES y AÑO en turnos asignados
        ->orWhereHas('turnosAsignados', function ($query) use ($usuario, $mesActual, $anioActual) {
            $query->where('turnos_asignados.usuario_id', $usuario->id)
                  ->whereMonth('fecha', $mesActual)
                  ->whereYear('fecha', $anioActual);
        })
        ->select('id', 'nombre')
        ->distinct() 
        ->get();

    return response()->json([
        'success' => true,
        'data' => $servicios
    ], 200, [], JSON_UNESCAPED_UNICODE);
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
    // NO uses wherePivot('estado', 1) aquí, para que aparezcan los inactivos y puedas reactivarlos
    $servicio = Servicio::with('usuarios.persona')->findOrFail($id);
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

public function actualizarEstadoVinculacion(Request $request)
{
    try {
        // Validamos los datos entrantes
        $request->validate([
            'usuario_id' => 'required',
            'servicio_id' => 'required',
            'estado' => 'required|in:0,1'
        ]);

        // Buscamos el servicio
        $servicio = Servicio::findOrFail($request->servicio_id);

        // Actualizamos el estado en la tabla pivote usando updateExistingPivot
        $servicio->usuarios()->updateExistingPivot($request->usuario_id, [
            'estado' => $request->estado
        ]);

        return response()->json(['message' => 'Estado actualizado con éxito']);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
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