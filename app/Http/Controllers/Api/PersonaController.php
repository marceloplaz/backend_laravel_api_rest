<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Persona;
use App\Models\User;
use App\Http\Resources\PersonaResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class PersonaController extends Controller 
{
    public function index(Request $request)
    {
        $query = Persona::with('user'); 
        // if de filtro de busqueda
        if ($request->has('buscar')) {
            $buscar = $request->get('buscar');
            $query->where('nombre_completo', 'like', "%$buscar%")
                  ->orWhere('carnet_identidad', 'like', "%$buscar%");
        }
    
        if ($request->has('cargo')) {
            $query->where('tipo_trabajador', $request->get('cargo'));
        }
        
        // paginador de 5 
        $perPage = $request->get('per_page', 5);


        return PersonaResource::collection($query->paginate($perPage));
    }

    
    public function store(Request $request)
    {
        // 1. VALIDACIÓN COMPLETA
        // Aseguramos que 'persona' sea un array para evitar errores al acceder a sus hijos
        $request->validate([
            "name"              => "required|string",
            "email"             => "required|email|unique:users,email",
            "password"          => "required|min:6",
            "categoria_id"      => "required|integer", 
            "roles"             => "required|array",
            
            "persona"                     => "required|array",
            "persona.nombre_completo"     => "required|string",
            "persona.carnet_identidad"    => "required|unique:personas,carnet_identidad",
            "persona.fecha_nacimiento"    => "required|date",
            "persona.genero"              => "required|string|max:1",
            "persona.telefono"            => "required|string", 
            "persona.direccion"           => "required|string",
            "persona.nacionalidad"        => "required|string",
            "persona.tipo_trabajador"     => "required|string", 
            "persona.tipo_salario"        => "required|string",
            "persona.numero_tipo_salario" => "required|numeric",
        ]);

        try {
            return DB::transaction(function () use ($request) {
                
                // 2. CREACIÓN DE USUARIO
                $usuario = User::create([
                    "name"         => $request->name,
                    "email"        => $request->email,
                    "password"     => Hash::make($request->password),
                    "categoria_id" => $request->categoria_id, 
                ]);

                // Asignar Roles (Sync para evitar duplicados en tabla pivot)
                if ($request->has('roles')) {
                    $usuario->roles()->sync($request->roles);
                }

                // 3. CREACIÓN DE DATOS PERSONALES
                $datosPersona = $request->persona;
                $datosPersona['user_id'] = $usuario->id; 

                $persona = Persona::create($datosPersona);

                return response()->json([
                    "message" => "Personal registrado con éxito",
                    "persona" => new PersonaResource($persona)
                ], 201);
            });


        } catch (\Exception $e) {
            return response()->json([
                "message" => "Error en el proceso de registro maestro",
                "error" => $e->getMessage()
            ], 500); 
        }
    }

    // entrega tipo salario y tipo de trabajador
public function getFormDependencies()
{
    return response()->json([
        'categorias' => \App\Models\Categoria::all(['id', 'nombre']),
        'roles' => \App\Models\Role::all(['id', 'name']), // O el modelo que uses para roles
        'tipos_salario' => [
            ['id' => 'TGN', 'nombre' => 'TGN'],
            ['id' => 'SUS', 'nombre' => 'SUS'],
            ['id' => 'CONTRATO', 'nombre' => 'CONTRATO'],
        ],
        'tipos_trabajador' => [
            ['id' => 'medico', 'nombre' => 'Médico'],
            ['id' => 'enfermera', 'nombre' => 'Enfermera'],
            ['id' => 'manual', 'nombre' => 'Manual'],
            ['id' => 'chofer', 'nombre' => 'Chofer'],
            ['id' => 'administrativo', 'nombre' => 'Administrativo'],
            ['id' => 'tecnico', 'nombre' => 'Tecnico'],
            ['id' => 'Bioquimico', 'nombre' => 'Bioquimico'],
        ]
    ]);
}
    public function show($id)
{
    // Buscamos la persona y cargamos su usuario (email, name)
    $persona = Persona::with('user')->find($id);

    if (!$persona) {
        return response()->json(['message' => 'No encontrado'], 404);
    }

    // Retornamos el objeto para que Angular lo reciba en 'res.data'
    return response()->json([
        'status' => 'success',
        'data' => $persona
    ]);
}

    public function update(Request $request, $id)
    {
        $persona = Persona::find($id);

        if (!$persona) {
            return response()->json(["message" => "Persona no encontrada"], 404);
        }

        // Si el request trae datos anidados de persona, los extraemos
        $datos = $request->has('persona') ? $request->get('persona') : $request->all();
        $persona->update($datos);

        return response()->json([
            "message" => "Datos actualizados correctamente",
            "persona" => new PersonaResource($persona->fresh())
        ]);
    }

    public function destroy($id)
    {
        $persona = Persona::with('user')->find($id);
        
        if (!$persona) {
            return response()->json(["message" => "No hay datos que eliminar"], 404);
        }

        try {
            DB::transaction(function () use ($persona) {
                $user = $persona->user;
                $persona->delete();
                if ($user) {
                    $user->delete(); // Eliminamos también el acceso al sistema
                }
            });

            return response()->json(["message" => "Personal y usuario eliminados correctamente"]);
        } catch (\Exception $e) {
            return response()->json(["message" => "Error al eliminar", "error" => $e->getMessage()], 500);
        }
    }


         public function exportarPdf(Request $request)
    {
    // 1. Obtenemos el ID de la categoría del request (si existe)
    $categoriaId = $request->query('categoria_id');

    // 2. Consultamos con filtro opcional usando una relación
    $personal = Persona::with(['user.categoria'])
        ->when($categoriaId, function ($query) use ($categoriaId) {
            $query->whereHas('user', function ($q) use ($categoriaId) {
                $q->where('categoria_id', $categoriaId);
            });
        })
        ->get();

    // 3. Definimos un título dinámico para la vista Blade
    $titulo = "Reporte General de Personal";
    if ($categoriaId) {
        $cat = \App\Models\Categoria::find($categoriaId);
        $titulo = "Reporte de Personal: " . ($cat ? $cat->nombre : 'Categoría Desconocida');
    }

    // 4. Cargamos la vista con los datos
   $pdf = Pdf::loadView('pdf.reporte_por_categoria', compact('personal', 'titulo'));
   return $pdf->stream('reporte_personal.pdf');
    }
}