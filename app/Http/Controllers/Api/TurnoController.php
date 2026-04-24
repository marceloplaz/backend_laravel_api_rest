<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turno;
use App\Models\Categoria;
use App\Models\Servicio;
use Illuminate\Http\Request;

class TurnoController extends Controller
{
    /**
     * Método nuevo para obtener Categorías y Servicios filtrados según el Rol
     */
public function getFiltrosPorJerarquia(Request $request)
{
    $user = $request->user();
    $rol = $user->roles()->first(); 

    if (!$rol) {
        return response()->json(['error' => 'Usuario sin rol asignado'], 403);
    }

    // --- 1. FILTRADO DE CATEGORÍAS (Por Niveles) ---
    $queryCat = Categoria::query();

    $categorias = match (true) {
        // Agregamos el ID 11 (admin_jefa_enfermeras) aquí para que vea todos los niveles si es necesario, 
        // o mantenemos su restricción de nivel 2 y 3 si solo debe gestionar enfermería y manuales.
        in_array($rol->id, [1, 2, 10, 11]) => $queryCat->get(), 

        // jefa_enfermeras (13) -> Sigue viendo Enfermeras (2) y Manuales (3)
        $rol->id == 13 => $queryCat->whereIn('nivel', [2, 3])->get(),

        // jefa_servicios_generales (12) -> Solo ve Manuales (Nivel 3)
        $rol->id == 12 => $queryCat->where('nivel', 3)->get(),

        default => $queryCat->where('id', $user->persona->categoria_id ?? 0)->get(),
    };

    // --- 2. FILTRADO DE SERVICIOS ---
    // IMPORTANTE: Incluimos el ID 11 aquí para que no dependa de la tabla 'role_servicio'
    // Esto permitirá que el selector de Servicios en el frontend de Angular se llene correctamente.
    if (in_array($rol->id, [1, 2, 10, 11])) {
        $servicios = Servicio::all();
    } else {
        $servicios = $rol->servicios; 
    }

    return response()->json([
        'categorias' => $categorias,
        'servicios' => $servicios
    ]);
}
public function index(Request $request)
{
    // 1. Intentamos filtrar por lo que pide el Frontend (Angular suele mandar categoria_id)
    $categoriaId = $request->query('categoria_id');
    
    if ($categoriaId) {
        $turnos = \App\Models\Turno::where('categoria_id', $categoriaId)->get();
        
        // SI LA LISTA ESTÁ VACÍA: Mandamos todos los turnos para que no se bloquee el modal
        if ($turnos->isEmpty()) {
            return response()->json(\App\Models\Turno::all());
        }
        
        return response()->json($turnos);
    }

    // 2. Si no hay parámetros, mandamos todo
    return response()->json(\App\Models\Turno::all());
}

 public function store(Request $request)
{
    $request->validate([
        'nombre_turno'   => 'required|string|max:100',
        'hora_inicio'    => 'required',
        'hora_fin'       => 'required',
        'duracion_horas' => 'required|integer',
        'categoria_id'   => 'required|integer' // Cambiado a required
    ]);

    try {
        // Usamos directamente los datos validados para asegurar que 
        // la categoria_id que viene del frontend se guarde correctamente.
        $turno = Turno::create($request->all());

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
        return response()->json(['message' => 'Turno eliminado']);
    }
}