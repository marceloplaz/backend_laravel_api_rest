<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Persona;
use App\Http\Resources\PersonaResource;
use Illuminate\Http\Request;

// ESTA ES LA CLASE QUE FALTA:
class PersonaController extends Controller 
{
    // Ahora sí, la función dentro de la clase

public function index(Request $request)
{
    // 1. Iniciamos la consulta
    $query = Persona::query();

    // 2. Filtro por búsqueda (nombre o CI)
    if ($request->has('buscar')) {
        $buscar = $request->get('buscar');
        $query->where('nombre_completo', 'like', "%$buscar%")
              ->orWhere('carnet_identidad', 'like', "%$buscar%");
    }

    // 3. Filtro por cargo (opcional)
    if ($request->has('cargo')) {
        $query->where('tipo_trabajador', $request->get('cargo'));
    }

    // 4. Paginación (10 registros por página)
    // El método paginate lee automáticamente el parámetro ?page de la URL
    $personas = $query->paginate(10);

    // 5. Retornamos la colección paginada a través del Resource
    return PersonaResource::collection($personas);
}

    public function store(Request $request)
    {
        $request->validate([
            "nombre_completo" => "required|string",
            "carnet_identidad" => "required|unique:personas",
            "tipo_trabajador" => "required",
            "tipo_salario" => "required"
        ]);

        $persona = new Persona($request->all());
        $persona->user_id = $request->user()->id; 
        $persona->save();

        return response()->json([
            "message" => "Datos personales guardados correctamente",
            "persona" => new PersonaResource($persona)
        ], 201);
    }
    // 1. VER (Read) - Ver los datos de la persona vinculada al usuario logueado
public function show(Request $request)
{
    $persona = $request->user()->persona;

    if (!$persona) {
        return response()->json(["message" => "No tienes datos registrados"], 404);
    }

    return new PersonaResource($persona);
}

// 2. EDITAR (Update) - Actualizar la información
public function update(Request $request)
{
    $persona = $request->user()->persona;

    if (!$persona) {
        return response()->json(["message" => "No tienes datos para actualizar"], 404);
    }

    $request->validate([
        "nombre_completo" => "string",
        "carnet_identidad" => "unique:personas,carnet_identidad," . $persona->id, // Ignora el CI actual del usuario
        "telefono" => "string"
    ]);

    $persona->update($request->all());

    return response()->json([
        "message" => "Datos actualizados correctamente",
        "persona" => new PersonaResource($persona)
    ]);
}

// 3. ELIMINAR (Delete) - Borrar los datos personales
public function destroy(Request $request)
{
    $persona = $request->user()->persona;

    if (!$persona) {
        return response()->json(["message" => "No hay datos que eliminar"], 404);
    }

    $persona->delete();

    return response()->json(["message" => "Datos eliminados correctamente"]);
}
}