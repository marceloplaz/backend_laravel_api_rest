<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Servicio;
use App\Http\Requests\ServicioStoreRequest;
use App\Http\Requests\ServicioUpdateRequest;

class ServicioController extends Controller
{
    // LISTAR
    public function index()
    {
        return response()->json([
            'data' => Servicio::all()
        ], 200);
    }

    // CREAR
    public function store(ServicioStoreRequest $request)
    {
        $servicio = Servicio::create($request->validated());

        return response()->json([
            'message' => 'Servicio creado correctamente',
            'data' => $servicio
        ], 201);
    }

    // MOSTRAR
    public function show($id)
    {
        $servicio = Servicio::with('turnos')->findOrFail($id);

        return response()->json([
            'data' => $servicio
        ], 200);
    }

    // ACTUALIZAR
    public function update(ServicioUpdateRequest $Request, $id)
    {
        $servicio = Servicio::findOrFail($id);

        $servicio->update($Request->all());

        return response()->json([
            'message' => 'Servicio actualizado correctamente',
            'data' => $servicio
        ], 200);
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