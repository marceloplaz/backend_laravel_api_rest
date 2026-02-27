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

    // En lugar de llamar a la Collection que falla, usamos el Resource 
    // con el método estático 'collection' que ya trae Laravel por defecto.
    return UsuarioServicioResource::collection($usuarioServicios);
}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'usuario_id'                   => 'required|exists:users,id',
            'servicio_id'                  => 'required|exists:servicios,id',
            'fecha_ingreso'                => 'required|date',
            'descripcion_usuario_servicio' => 'nullable|string|max:500',
            'estado'                       => 'boolean'
        ]);

        // Evitar duplicados activos
        $existe = UsuarioServicio::where('usuario_id', $validated['usuario_id'])
            ->where('servicio_id', $validated['servicio_id'])
            ->where('estado', true)
            ->exists();

        if ($existe) {
            return response()->json(['message' => 'El usuario ya tiene una asignación activa en este servicio.'], 422);
        }

        $usuarioServicio = UsuarioServicio::create($validated);

        // CARGAR RELACIONES ANTES DE ENVIAR AL RESOURCE
        $usuarioServicio->load(['usuario', 'servicio']);

        return new UsuarioServicioResource($usuarioServicio);
    }

    public function show($id)
    {
        // findOrFail es más limpio: lanza un 404 automáticamente si no existe
        $usuarioServicio = UsuarioServicio::with(['usuario', 'servicio'])->findOrFail($id);
        
        return new UsuarioServicioResource($usuarioServicio);
    }

    public function update(Request $request, $id)
    {
        $usuarioServicio = UsuarioServicio::findOrFail($id);

        $validated = $request->validate([
            'usuario_id'                   => 'sometimes|exists:users,id',
            'servicio_id'                  => 'sometimes|exists:servicios,id',
            'fecha_ingreso'                => 'sometimes|date',
            'descripcion_usuario_servicio' => 'nullable|string|max:500',
            'estado'                       => 'boolean'
        ]);

        $usuarioServicio->update($validated);

        return new UsuarioServicioResource($usuarioServicio);
    }

    public function destroy($id)
    {
        $usuarioServicio = UsuarioServicio::findOrFail($id);
        $usuarioServicio->delete();

        return response()->json(['message' => 'Registro eliminado'], 200);
    }
}