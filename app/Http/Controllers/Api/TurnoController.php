<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turno;
use Illuminate\Http\Request;

class TurnoController extends Controller
{
    public function index()
    {
        return response()->json(Turno::all());
    }

 

public function store(Request $request)
    {
        $request->validate([
            'nombre_turno'   => 'required|string|max:100',
            'hora_inicio'    => 'required',
            'hora_fin'       => 'required',
            'duracion_horas' => 'required|integer',
            'categoria_id'   => 'nullable|integer'
        ]);

        try {
            $data = $request->all();
            
            // Si Angular no manda categoría, asignamos la 1 por defecto para evitar el error de BD
            if (!$request->has('categoria_id')) {
                $data['categoria_id'] = 1; 
            }

            $turno = Turno::create($data);

            return response()->json([
                'message' => 'Turno creado exitosamente',
                'data'    => $turno
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el turno en la base de datos',
                'error'   => $e->getMessage()
            ], 500);
        }
    }



    public function show($id)
    {
        $turno = Turno::with('servicios')->findOrFail($id);

        return response()->json($turno);
    }

    public function update(Request $request, $id)
    {
        $turno = Turno::findOrFail($id);

        $turno->update($request->all());

        return response()->json($turno);
    }

    public function destroy($id)
    {
        Turno::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Turno eliminado'
        ]);
    }
}