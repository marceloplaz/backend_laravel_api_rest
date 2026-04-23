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
        $rol = $user->roles()->first(); // Obtenemos el rol del usuario (ID 1, 8, 10, 12, etc.)

        if (!$rol) {
            return response()->json(['error' => 'Usuario sin rol asignado'], 403);
        }

        // --- 1. FILTRADO DE CATEGORÍAS (Por Niveles) ---
        $queryCat = Categoria::query();

        $categorias = match (true) {
            // super_admin (1), admin (2), admin_jefe_medico (10) -> Ven todos los niveles
            in_array($rol->id, [1, 2, 10]) => $queryCat->get(),

            // admin_jefa_enfermeras (11) y jefa_enfermeras (13) -> Ven Enfermeras (2) y Manuales (3)
            in_array($rol->id, [11, 13]) => $queryCat->whereIn('nivel', [2, 3])->get(),

            // jefa_servicios_generales (12) -> Solo ve Manuales (Nivel 3)
            $rol->id == 12 => $queryCat->where('nivel', 3)->get(),

            // Otros roles (Médicos, Internos, etc.) -> Ven su propio nivel
            default => $queryCat->where('id', $user->persona->categoria_id ?? 0)->get(),
        };

        // --- 2. FILTRADO DE SERVICIOS (Por Tabla Intermedia 'role_servicio') ---
        // Si es Admin o Jefe Médico, le damos todos los servicios directamente
        if (in_array($rol->id, [1, 2, 10])) {
            $servicios = Servicio::all();
        } else {
            // Para los demás, usamos la relación que poblamos en el Seeder
            $servicios = $rol->servicios; 
        }

        return response()->json([
            'categorias' => $categorias,
            'servicios' => $servicios
        ]);
    }

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
        return response()->json(['message' => 'Turno eliminado']);
    }
}