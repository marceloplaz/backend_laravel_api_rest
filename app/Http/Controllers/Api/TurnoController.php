<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turno;
use App\Models\Categoria;
use App\Models\User;

use App\Models\Servicio;
use Illuminate\Http\Request;
use App\Models\TurnoAsignado;

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
        'categoria_id'   => 'required|integer' 
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


public function reporteMensual(Request $request)
{
    $mesId = $request->query('mes_id');
    $anioTexto = $request->query('gestion'); // Ejemplo: "2026"
    $servicioId = $request->query('servicio_id');

    // 1. Buscamos el ID de la gestión que coincida con el año enviado
    $gestion = \DB::table('gestiones')->where('año', $anioTexto)->first();

    if (!$gestion) {
        return response()->json(['error' => 'Gestión no encontrada'], 404);
    }

    // 2. Ahora filtramos usando gestion_id
   $turnos = \App\Models\TurnoAsignado::with([
        'usuario.persona', // <--- Debe ser así para entrar a la tabla personas
        'turno', 
        'area'
    ])
        ->where('mes_id', $mesId)
        ->where('servicio_id', $servicioId)
        ->where('gestion_id', $gestion->id) // <--- Usamos el ID de la tabla gestiones
        ->get();

    return response()->json($turnos);
}


public function misTurnosMes(Request $request)
{
    // Si viene usuario_id lo usamos (Modo Admin), si no, usamos el del token
    $usuarioId  = $request->query('usuario_id') ?? auth()->id();
    $servicioId = $request->query('servicio_id');
    $mes        = $request->query('mes');
    $anio       = $request->query('anio');

    // Cargamos los turnos con sus relaciones necesarias de manera eficiente
    $turnos = \App\Models\TurnoAsignado::with(['turno', 'area', 'servicio'])
        ->where('usuario_id', $usuarioId)
        ->where('servicio_id', $servicioId)
        ->whereMonth('fecha', $mes)
        ->whereYear('fecha', $anio)
        ->get();

    $dataFormateada = $turnos->map(function ($t) {
        // Formateamos las horas limpiamente si el turno existe
        $horarioTexto = $t->turno 
            ? \Carbon\Carbon::parse($t->turno->hora_inicio)->format('H:i') . ' - ' . \Carbon\Carbon::parse($t->turno->hora_fin)->format('H:i')
            : 'N/A';

        return [
            'id_asignacion' => $t->id,
            'nombre_turno'  => $t->turno?->nombre_turno ?? 'Sin Turno',
            'horario'       => $horarioTexto,
            'fecha'         => $t->fecha,
            'area_nombre'   => $t->area?->nombre ?? ($t->servicio?->nombre ?? 'Servicio General'),
            'color'         => $t->turno?->color ?? '#28a745',
            
            // 🌟 LA SOLUCIÓN DIRECTA: Duración inyectada en la raíz del objeto plano
            'duracion_horas' => $t->turno ? (float)$t->turno->duracion_horas : 0,
        ];
    });

    return response()->json([
        'status' => 'success', 
        'data'   => $dataFormateada
    ]);
}
public function reporteHorasSemana(Request $request, $semana_id, $usuario_id = null)
{
    $userAutenticado = auth()->user();
    $nivelMinimo = $userAutenticado->categoria->nivel;

    // 1. Obtener los límites y fechas reales de la semana seleccionada
    $semana = \App\Models\Semana::findOrFail($semana_id);
    $fechaInicio = $semana->fecha_inicio;
    $fechaFin = $semana->fecha_fin;

    // 2. Filtrar los usuarios que analizaremos (por ID específico o por jerarquía visible)
    $usuariosQuery = \App\Models\User::with(['persona', 'categoria']);

    if ($usuario_id) {
        $usuariosQuery->where('id', $usuario_id);
    } else {
        $usuariosQuery->whereHas('categoria', function($q) use ($nivelMinimo) {
            $q->where('nivel', '>=', $nivelMinimo);
        });
    }

    $usuarios = $usuariosQuery->get();

    // 3. Procesar las horas de cada usuario basándonos estrictamente en sus turnos de la semana
    $resultado = $usuarios->map(function ($user) use ($semana_id, $fechaInicio, $fechaFin) {
        
        // A. Turnos donde es el TITULAR original en esta semana
        $turnosDirectos = \App\Models\TurnoAsignado::where('usuario_id', $user->id)
            ->where('semana_id', $semana_id)
            ->with(['turno', 'novedad'])
            ->get();

        // B. Turnos virtuales donde actúa como REEMPLAZO en esta semana
        $novedadesComoReemplazo = \App\Models\NovedadLaboral::where('usuario_reemplazo_id', $user->id)
            ->whereHas('asignacion', function($q) use ($semana_id) {
                $q->where('semana_id', $semana_id);
            })
            ->with(['asignacion.turno'])
            ->get();

        $turnosVirtuales = $novedadesComoReemplazo->map(function($nov) {
            return $nov->asignacion;
        });

        // C. Unificar turnos evitando duplicados por ID de asignación
        $todosLosTurnos = $turnosDirectos->concat($turnosVirtuales)
            ->unique('id')
            ->values();

        // D. Calcular horas totales acumulando directamente el campo duracion_horas de la BD
        $totalHorasAcumuladas = 0;
        $detalleTurnos = [];

        foreach ($todosLosTurnos as $ta) {
            // Si el usuario cedió su turno (tiene novedad y él es el solicitante), no suma horas para él
            if ($ta->novedad && $ta->novedad->usuario_solicitante_id == $user->id) {
                continue; 
            }

            if ($ta->turno) {
                // 🌟 OPTIMIZACIÓN CLAVE: Usamos la columna directa de HeidiSQL
                $horasTurno = (float)($ta->turno->duracion_horas ?? 0);
                $totalHorasAcumuladas += $horasTurno;

                $detalleTurnos[] = [
                    'id_asignacion' => $ta->id,
                    'fecha' => $ta->fecha,
                    'nombre_turno' => $ta->turno->nombre_turno,
                    'horario' => "{$ta->turno->hora_inicio} - {$ta->turno->hora_fin}",
                    'duracion_horas' => $horasTurno
                ];
            }
        }

        return [
            'id' => $user->id,
            'nombre' => $user->persona->nombre_completo ?? $user->name,
            'tipo_salario' => $user->persona->tipo_salario ?? 'No definido',
            'total_horas' => round($totalHorasAcumuladas, 2), 
            'conteo_turnos' => count($detalleTurnos),
            'detalle' => $detalleTurnos
        ];
    });
$idBuscado = $usuario_id ?? $userAutenticado->id;
    $usuarioEncontrado = $resultado->firstWhere('id', $idBuscado);
    $totalHorasEspecifico = $usuarioEncontrado ? $usuarioEncontrado['total_horas'] : 0;
    // 4. Retornar la respuesta con el formato limpio para Angular
return response()->json([
        'status' => 'success',
        'total_horas_semana' => $totalHorasEspecifico, // 🌟 Atajo directo si consultas un ID
        'data' => $resultado
    ], 200);
}


public function buscarProfesionales(Request $request)
{
    $termino = $request->query('buscar');
    $servicioId = $request->query('servicio_id');

    // Agregamos 'servicios' a la carga para acceder al pivot
    $query = \App\Models\User::query()->with(['persona', 'servicios']);

    $query->whereHas('persona', function($q) use ($termino) {
        $q->where('nombre', 'LIKE', "%{$termino}%")
          ->orWhere('apellido_paterno', 'LIKE', "%{$termino}%");
    });

    if ($servicioId) {
        $query->whereHas('servicios', function($q) use ($servicioId) {
            $q->where('servicio_id', $servicioId);
            // Si quieres que la búsqueda SOLO devuelva activos, deja la siguiente línea.
            // Si quieres ver a todos (inactivos bloqueados), quítala.
            $q->where('usuario_servicios.estado', 1); 
        });
    }

    return $query->limit(5)->get()->map(function($user) use ($servicioId) {
        // Buscamos el servicio específico en la colección del usuario para obtener su estado
        $servicioPivot = $user->servicios->where('id', $servicioId)->first();

        return [
            'id' => $user->persona->id ?? null,
            'nombre' => $user->persona->nombre ?? '',
            'apellido_paterno' => $user->persona->apellido_paterno ?? '',
            'usuario_id' => $user->id,
            // Extraemos el estado directamente del pivot
            'estado' => $servicioPivot ? $servicioPivot->pivot->estado : 0 
        ];
    });
}

public function getServiciosUsuario($id)
{
    // 1. Cargamos al usuario con sus asignaciones, servicios y la configuración del turno (horas)
    $usuario = User::with([
        'turnosAsignados.servicio', 
        'turnosAsignados.turno' // Esta relación usa la función turno() de tu modelo
    ])->findOrFail($id);

    // 2. Extraemos los servicios únicos (Lógica que ya tenías funcionando)
    $servicios = $usuario->turnosAsignados->map(function ($ta) {
        return $ta->servicio;
    })->unique('id')->values();

    

    $turnosCalendario = $usuario->turnosAsignados->map(function ($ta) {
    // Forzamos el formato H:i:s para limpiar cualquier fecha residual de la base de datos
    $horaInicio = $ta->turno ? date('H:i:s', strtotime($ta->turno->hora_inicio)) : '00:00:00';
    $horaFin = $ta->turno ? date('H:i:s', strtotime($ta->turno->hora_fin)) : '00:00:00';
    
    // Lógica para turnos que pasan de medianoche (Noche/Tarde-Noche)
    $fechaFin = $ta->fecha;
    if ($horaFin < $horaInicio) {
        $fechaFin = date('Y-m-d', strtotime($ta->fecha . ' +1 day'));
    }

    return [
        'id'    => $ta->id,
        'title' => ($ta->turno->nombre_turno ?? 'Turno') . ' (' . $ta->servicio->nombre . ')',
        'start' => $ta->fecha . 'T' . $horaInicio, // Ahora será: 2026-05-05T19:00:00
        'end'   => $fechaFin . 'T' . $horaFin,
        'color' => $ta->estado === 'programado' ? '#28a745' : '#ffc107'
    ];
});
    
    return response()->json([
        'status'    => 'success',
        'servicios' => $servicios,
        'turnos'    => $turnosCalendario
    ]);
}

/**
 * Función auxiliar para dar color a los eventos según su estado
 */
private function getColorPorEstado($estado) {
    return $estado === 'programado' ? '#28a745' : '#ffc107';
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