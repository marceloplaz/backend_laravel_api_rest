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

public function ejecutarSincronizacion(SincronizacionService $service) 
{
    try {
        // 1. Sincronizar Enfermería (Auxiliares y Licenciadas)
        $service->sincronizarDesdeSqlServer();

        // 2. Sincronizar Internos y Residentes
        $service->sincronizarInternosYResidentes();

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



    public function reporteAlimentacionPersona(Request $request, $personaId)
{
    $fechaInicio = $request->get('fecha_inicio', now()->startOfMonth()->toDateString());
    $fechaFin    = $request->get('fecha_fin', now()->endOfMonth()->toDateString());

    // 1. Buscar en la tabla personas (usando el id de la tabla personas)
    $persona = Persona::with(['user.turnosAsignados' => function ($query) use ($fechaInicio, $fechaFin) {
        $query->whereBetween('fecha', [$fechaInicio, $fechaFin])
              ->with(['turno.comidas', 'servicio']);
    }])->find($personaId);

    if (!$persona) {
        return response()->json([
            'status'  => 'error',
            'message' => "No se encontró la persona con ID: {$personaId}"
        ], 404);
    }

    // 2. Extraer los turnos asignados a través del objeto User relacionado
    $turnosAsignados = $persona->user ? $persona->user->turnosAsignados : collect();

    $detalleAlimentacion = [];

    foreach ($turnosAsignados as $asignacion) {
        if ($asignacion->turno && $asignacion->turno->comidas->isNotEmpty()) {
            foreach ($asignacion->turno->comidas as $comida) {
                $detalleAlimentacion[] = [
                    'id_asignacion' => $asignacion->id,
                    'fecha'         => $asignacion->fecha,
                    'servicio'      => $asignacion->servicio->nombre ?? 'N/A',
                    'turno'         => $asignacion->turno->nombre,
                    'hora_inicio'   => $asignacion->turno->hora_inicio,
                    'hora_fin'      => $asignacion->turno->hora_fin,
                    'comida'        => $comida->nombre,
                    'codigo'        => $comida->codigo
                ];
            }
        }
    }

    return response()->json([
        'status' => 'success',
        'persona' => [
            'id'       => $persona->id,
            'user_id'  => $persona->user_id,
            'nombre'   => $persona->nombre,
            'apellido' => $persona->apellido ?? '',
            'ci'       => $persona->ci ?? ''
        ],
        'periodo' => [
            'inicio' => $fechaInicio,
            'fin'    => $fechaFin
        ],
        'total_registros' => count($detalleAlimentacion),
        'alimentacion'    => $detalleAlimentacion
    ], 200);
}

    public function getFormDependencies()
{
    // 1. Consultar la definición de la columna 'tipo_salario' en MySQL
    $type = DB::select("SHOW COLUMNS FROM personas WHERE Field = 'tipo_salario'")[0]->Type;

    // 2. Extraer los valores definidos dentro de enum(...)
    preg_match('/enum\((.*)\)$/', $type, $matches);

    // 3. Formatear la lista de valores para la respuesta JSON
    $tiposSalario = collect(explode(',', $matches[1]))->map(function ($value) {
        $clean = trim($value, "'");
        return [
            'id'     => $clean,
            'nombre' => $clean
        ];
    })->values();

    return response()->json([
        'categorias' => \App\Models\Categoria::all(['id', 'nombre']),
        'roles' => \App\Models\Role::all(['id', 'name']), 
        'tipos_salario' => $tiposSalario, 
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
    
    $fechaInicio = $request->input('fecha_inicio') ?: \Carbon\Carbon::createFromDate($gestion, $mes, 1)->startOfMonth()->toDateString();
    $fechaFin = $request->input('fecha_fin') ?: \Carbon\Carbon::createFromDate($gestion, $mes, 1)->endOfMonth()->toDateString();

    $query = User::with([
        'categoria', 
        'persona', 
        'turnosAsignados' => function($q) use ($fechaInicio, $fechaFin) {
            $q->whereBetween('fecha', [$fechaInicio, $fechaFin])
              ->with(['servicio', 'turno.comidas'])
              ->orderBy('fecha', 'asc');
        }
    ]);

    // Filtros por Tipo de Salario y Categoría
    $nombreFiltroPartes = [];

    if ($tipoSalario && strtolower($tipoSalario) !== 'todos') {
        $nombreFiltroPartes[] = strtoupper($tipoSalario);
        $query->whereHas('persona', function($q) use ($tipoSalario) {
            $q->where('tipo_salario', $tipoSalario);
        });
    }

    $esTodasCategorias = (!$categoriaId || strtoupper((string)$categoriaId) === 'TODOS' || strtoupper((string)$categoriaId) === 'TODAS');

    if (!$esTodasCategorias) {
        if (is_numeric($categoriaId)) {
            $query->where('categoria_id', $categoriaId);
            $cat = Categoria::find($categoriaId);
            if ($cat) { 
                $nombreFiltroPartes[] = strtoupper($cat->nombre); 
            }
        } elseif ($categoriaNombre && strtolower($categoriaNombre) !== 'todos' && strtolower($categoriaNombre) !== 'todas') {
            $nombreFiltroPartes[] = strtoupper($categoriaNombre);
            $query->whereHas('categoria', function($q) use ($categoriaNombre) {
                $q->where('nombre', 'LIKE', '%' . $categoriaNombre . '%');
            });
        }
    }
    
    $nombreFiltro = count($nombreFiltroPartes) > 0 ? implode(' - ', $nombreFiltroPartes) : 'TODAS LAS CATEGORÍAS';

    $personal = $query->get();

    $personal->transform(function($user) {
    $turnosAsistencia = collect();
    $matrizComidasPorFecha = [];

    // Colección lineal de asignaciones ordenadas por fecha
    $asignaciones = $user->turnosAsignados->sortBy('fecha')->values();

    foreach ($asignaciones as $index => $asignacion) {
        // 🚫 OMITIR DÍAS BLOQUEADOS / POSGUARDIAS PARA ASISTENCIA Y COMIDAS
        if (($asignacion->estado ?? '') === 'bloqueado') {
            continue;
        }

        $turno = $asignacion->turno;
        if (!$turno) continue;

        $fechaAsignacion = $asignacion->fecha;

        // -------------------------------------------------------------
        // 1. REPORTE DE ASISTENCIA Y TURNOS (Verde)
        // -------------------------------------------------------------
        $esInicioDeBloque = true;
        if ($index > 0) {
            $fechaAnterior = \Carbon\Carbon::parse($asignaciones[$index - 1]->fecha);
            $fechaActual = \Carbon\Carbon::parse($fechaAsignacion);
            
            if ($fechaActual->diffInDays($fechaAnterior) == 1 && $asignaciones[$index - 1]->turno_id == $asignacion->turno_id) {
                $esInicioDeBloque = false; 
            }
        }

        if ($esInicioDeBloque) {
            $turnosAsistencia->push([
                'id'             => $turno->id,
                'nombre_turno'   => $turno->nombre_turno,
                'duracion_horas' => $turno->duracion_horas ?? $turno->horas ?? 0,
                'hora_inicio'    => $turno->hora_inicio,
                'hora_fin'       => $turno->hora_fin,
                'fecha'          => $fechaAsignacion,
                'pivot'          => [
                    'id_asignacion'   => $asignacion->id,
                    'usuario_id'      => $user->id,
                    'turno_id'        => $turno->id,
                    'fecha'           => $fechaAsignacion,
                    'estado'          => $asignacion->estado ?? 'programado',
                    'servicio_id'     => $asignacion->servicio_id,
                    'nombre_servicio' => $asignacion->servicio ? $asignacion->servicio->nombre : ''
                ]
            ]);
        }

        // -------------------------------------------------------------
        // 2. REPORTE DE COMIDAS (Naranja)
        // -------------------------------------------------------------
        foreach ($turno->comidas as $comida) {
            // Leemos el dia_relativo guardado en la BD (0 = Mismo día, 1 = Posguardia)
            $diaRelativoPivot = (int)($comida->pivot->dia_relativo ?? 0);

            // Sumamos los días a la fecha del turno (Día 6 + 0 = Día 6 | Día 6 + 1 = Día 7)
            $fechaComidaEfectiva = \Carbon\Carbon::parse($fechaAsignacion)
                                        ->addDays($diaRelativoPivot)
                                        ->toDateString();

            $codigoComida = $comida->pivot->sigla ?? $comida->sigla ?? strtoupper(substr(trim($comida->nombre), 0, 1));
            $ordenComida = $comida->pivot->orden ?? $comida->orden ?? $comida->id;

            if (!isset($matrizComidasPorFecha[$fechaComidaEfectiva])) {
                $matrizComidasPorFecha[$fechaComidaEfectiva] = [];
            }

            // Insertar la comida para la fecha efectiva correspondiente
            $yaExiste = collect($matrizComidasPorFecha[$fechaComidaEfectiva])->contains('id', $comida->id);
            if (!$yaExiste) {
                $matrizComidasPorFecha[$fechaComidaEfectiva][] = [
                    'id'           => $comida->id,
                    'nombre'       => $comida->nombre,
                    'codigo'       => $codigoComida,
                    'orden'        => $ordenComida,
                    'dia_relativo' => $diaRelativoPivot
                ];
            }
        }
    }

    // Formatear matriz final ordenada por fecha
    $comidasFormateadasPorFecha = [];
    foreach ($matrizComidasPorFecha as $fecha => $comidas) {
        $comidasOrdenadas = collect($comidas)->sortBy('orden')->values();
        $textoResumen = $comidasOrdenadas->pluck('codigo')->implode('');

        $comidasFormateadasPorFecha[] = [
            'fecha'         => $fecha,
            'texto_resumen' => $textoResumen,
            'detalles'      => $comidasOrdenadas->toArray()
        ];
    }

    $user->turnos = $turnosAsistencia->values();  
    $user->comidas_mes = array_values($comidasFormateadasPorFecha);

    unset($user->turnosAsignados);
    return $user;
});

    return response()->json([
        'data'             => $personal,
        'fecha_inicio'     => $fechaInicio,
        'fecha_fin'        => $fechaFin,
        'nombre_categoria' => $nombreFiltro
    ]);
}

}