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
use Maatwebsite\Excel\Facades\Excel; 
use App\Imports\PersonalImport;
use Carbon\Carbon;      
use App\Models\Categoria;

class PersonaController extends Controller 
{

public function ejecutarSincronizacion(SincronizacionService $service) {
    try {
        $service->sincronizarDesdeSqlServer();
        return response()->json([
            'status' => 'success',
            'message' => 'Sincronización completada con éxito.'
        ], 200);
    } catch (\Exception $e) {
        
        \Log::error("Error en sincronización: " . $e->getMessage());
            return response()->json([
            'status' => 'error',
            'message' => 'Hubo un error al sincronizar. Por favor, revise los logs.'
        ], 500);
    }
}

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
        'roles' => \App\Models\Role::all(['id', 'name']), 
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

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        try {
            Excel::import(new PersonalImport, $request->file('file'));
            return response()->json(['message' => '¡Importación exitosa!'], 200);
        } 
        catch (\Exception $e) {
    // Esto te devolverá el mensaje real del error (ej: "Field 'direccion' doesn't have a default value")
    return response()->json(['error' => $e->getMessage()], 500);
}
    }
public function generarMatrizTurnos(Request $request)
{
    $mes = $request->input('mes_id', date('m'));
    $gestion = $request->input('gestion', date('Y'));  
    $tipoSalario = $request->input('tipo_salario');
    $categoriaId = $request->input('categoria_id');
    $categoriaNombre = $request->input('categoria_nombre');
    
    //$fechaInicio = Carbon::createFromDate($gestion, $mes, 1)->startOfMonth()->toDateString();
    //$fechaFin = Carbon::createFromDate($gestion, $mes, 1)->endOfMonth()->toDateString();
    $fechaInicio = $request->input('fecha_inicio') ?: Carbon::createFromDate($gestion, $mes, 1)->startOfMonth()->toDateString();
    $fechaFin = $request->input('fecha_fin') ?: Carbon::createFromDate($gestion, $mes, 1)->endOfMonth()->toDateString();

    $query = User::with([
        'categoria', 
        'persona', 
        'turnos' => function($q) use ($fechaInicio, $fechaFin) {
            // SOLO filtramos por fecha en el pivote. No uses ->with('servicio') aquí.
            $q->wherePivotBetween('fecha', [$fechaInicio, $fechaFin]);
        }
    ]);

    $nombreFiltroPartes = [];

    if ($tipoSalario && strtolower($tipoSalario) !== 'todos') {
        $nombreFiltroPartes[] = strtoupper($tipoSalario);
        $query->whereHas('persona', function($q) use ($tipoSalario) {
            $q->where('tipo_salario', $tipoSalario);
        });
    }

    if ($categoriaId) {
        $query->where('categoria_id', $categoriaId);
        $cat = Categoria::find($categoriaId);
        if ($cat) { $nombreFiltroPartes[] = strtoupper($cat->nombre); }
    } elseif ($categoriaNombre && strtolower($categoriaNombre) !== 'todos' && strtolower($categoriaNombre) !== 'todas') {
        $nombreFiltroPartes[] = strtoupper($categoriaNombre);
        $query->whereHas('categoria', function($q) use ($categoriaNombre) {
            $q->where('nombre', 'LIKE', '%' . $categoriaNombre . '%');
        });
    }
    
    $nombreFiltro = count($nombreFiltroPartes) > 0 ? implode(' - ', $nombreFiltroPartes) : 'TODAS LAS CATEGORÍAS';

    $personal = $query->get();

    
    $personal->each(function($user) {
        $user->turnos->each(function($turno) {
            if ($turno->pivot && $turno->pivot->servicio_id) {
                $servicio = \App\Models\Servicio::find($turno->pivot->servicio_id);
                $turno->pivot->nombre_servicio = $servicio ? $servicio->nombre : '';
            } else {
                $turno->pivot->nombre_servicio = '';
            }
        });
    });

    return response()->json([
        'data' => $personal,
        'fecha_inicio' => $fechaInicio,
        'fecha_fin' => $fechaFin,
        'nombre_categoria' => $nombreFiltro
    ]);
}
}