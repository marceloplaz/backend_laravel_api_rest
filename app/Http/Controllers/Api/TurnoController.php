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
            'nombre_turno' => 'required|string',
            'hora_inicio' => 'required',
            'hora_fin' => 'required',
            'duracion_horas' => 'required|integer'
        ]);

        $turno = Turno::create($request->all());

        return response()->json($turno, 201);
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