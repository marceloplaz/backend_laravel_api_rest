<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UsuarioServicio;
use App\Http\Resources\UsuarioServicioResource;
use App\Http\Resources\UsuarioServicioCollection;

class UsuarioServicioController extends Controller
{
    public function index(Request $request)
    {
        $query = UsuarioServicio::with(['usuario', 'servicio']);

        if ($request->has('usuario_id')) {
            $query->where('usuario_id', $request->usuario_id);
        }

        if ($request->has('servicio_id')) {
            $query->where('servicio_id', $request->servicio_id);
        }

        $usuarioServicios = $query->paginate(10);

        return new UsuarioServicioCollection($usuarioServicios);
    }

    public function store(Request $request)
    {
        $request->validate([
    'usuario_id' => 'required|exists:users,id', // 'users' en plural
    'servicio_id' => 'required|exists:servicios,id', // 'servicios' en plural
    'fecha_inicio' => 'required|date',
    'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio', // Agregué una validación lógica
    'estado' => 'nullable|string'
]);

        $usuarioServicio = UsuarioServicio::create($request->all());

        return new UsuarioServicioResource($usuarioServicio);
    }

    public function show($id)
    {
        $usuarioServicio = UsuarioServicio::with(['usuario', 'servicio'])->find($id);

        if (!$usuarioServicio) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }

        return new UsuarioServicioResource($usuarioServicio);
    }

    public function update(Request $request, $id)
    {
        $usuarioServicio = UsuarioServicio::find($id);

        if (!$usuarioServicio) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }

        $usuarioServicio->update($request->all());

        return new UsuarioServicioResource($usuarioServicio);
    }

    public function destroy($id)
    {
        $usuarioServicio = UsuarioServicio::find($id);

        if (!$usuarioServicio) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }

        $usuarioServicio->delete();

        return response()->json(['message' => 'Registro eliminado'], 200);
    }
}