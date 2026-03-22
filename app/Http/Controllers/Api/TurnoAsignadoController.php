<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TurnoAsignado;
use App\Models\Semana;
use App\Models\Turno;
use App\Models\Servicio;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TurnoAsignadoController extends Controller
{
    /**
     * 1. REGISTRAR TURNO
     * Validamos que el usuario solo use turnos de SU categoría y en servicios permitidos.
     */
    public function store(Request $request)
{
    // El usuario que está operando (ej. un Jefe)
    $userAutenticado = auth()->user();

    $request->validate([
        'usuario_id'  => 'required|exists:users,id', // Personal al que se le asigna el turno
        'turno_id'    => 'required|exists:turnos,id',
        'fecha'       => 'required|date',
        'observacion' => 'nullable|string|max:500'
    ]);

    $fechaFormateada = Carbon::parse($request->fecha)->format('Y-m-d');

    // 1. OBTENER EL SERVICIO AUTOMÁTICAMENTE
    // Buscamos en qué servicio está activo el usuario destino
    $asignacionServicio = \App\Models\UsuarioServicio::where('usuario_id', $request->usuario_id)
        ->where('estado', true)
        ->first();

    if (!$asignacionServicio) {
        return response()->json([
            'message' => 'El usuario no tiene un servicio activo asignado. Asígnalo a un servicio primero.'
        ], 422);
    }

    $servicioId = $asignacionServicio->servicio_id;

    // 2. VALIDACIÓN DE CATEGORÍA Y TURNO
    $usuarioDestino = \App\Models\User::with('categoria')->findOrFail($request->usuario_id);
    $turnoNuevo = Turno::findOrFail($request->turno_id);

    // Verificamos que el turno corresponda a la categoría del usuario destino
    if ($turnoNuevo->categoria_id !== $usuarioDestino->categoria_id) {
        return response()->json([
            'message' => "Acceso denegado: La categoría de {$usuarioDestino->name} no coincide con este turno."
        ], 403);
    }

    // 3. LÓGICA DE CALENDARIO (Semanas/Mes/Gestión)
    $semana = Semana::where('fecha_inicio', '<=', $fechaFormateada)
        ->where('fecha_fin', '>=', $fechaFormateada)
        ->with('mes.gestion')
        ->first();

    if (!$semana) {
        return response()->json(['message' => 'La fecha no existe en el calendario configurado.'], 422);
    }

    // 4. VALIDACIÓN DE CHOQUE DE HORARIOS (Evitar solapamientos)
    $turnosDelDia = TurnoAsignado::where('usuario_id', $usuarioDestino->id)
        ->where('fecha', $fechaFormateada)
        ->with('turno')
        ->get();

    foreach ($turnosDelDia as $asignacionExistente) {
        $existente = $asignacionExistente->turno;

        // Lógica de choque: (InicioA < FinB) Y (FinA > InicioB)
        $choque = ($turnoNuevo->hora_inicio < $existente->hora_fin) && 
                  ($turnoNuevo->hora_fin > $existente->hora_inicio);

        if ($choque) {
            return response()->json([
                'message' => "Conflicto: El turno '{$turnoNuevo->nombre_turno}' choca con '{$existente->nombre_turno}' ya asignado."
            ], 422);
        }
    }

    // 5. CREAR LA ASIGNACIÓN CON EL SERVICIO DETECTADO
    $asignacion = TurnoAsignado::create([
        'usuario_id'  => $usuarioDestino->id,
        'servicio_id' => $servicioId, // Se asigna automáticamente
        'turno_id'    => $request->turno_id,
        'semana_id'   => $semana->id,
        'mes_id'      => $semana->mes_id,
        'gestion_id'  => $semana->mes->gestion_id,
        'fecha'       => $fechaFormateada,
        'estado'      => 'programado',
        'observacion' => $request->observacion
    ]);

    $asignacion->load(['usuario.persona', 'usuario.categoria', 'servicio', 'turno']);
    
    return response()->json([
        'message' => 'Turno asignado correctamente en ' . $asignacion->servicio->nombre, 
        'data' => $asignacion
    ], 201);
}
// En Laravel: TurnoAsignadoController.php o similar

public function getEquipoPorJerarquia($servicio_id)
{
    // 1. Buscamos los usuarios vinculados a este servicio
    // Usamos 'whereHas' para filtrar por la tabla intermedia usuario_servicios
    $equipo = \App\Models\User::whereHas('servicios', function($query) use ($servicio_id) {
        $query->where('servicios.id', $servicio_id)
              ->where('usuario_servicios.estado', true); // Solo activos
    })
    ->with(['categoria', 'persona']) // Cargamos la categoría
    ->get();

    // 2. Agrupamos los resultados por el nombre de la categoría para el frontend
    $agrupado = $equipo->groupBy(function($user) {
        return $user->categoria ? $user->categoria->nombre_categoria : 'Sin Categoría';
    })->map(function($personal, $categoriaNombre) {
        return [
            'categoria' => $categoriaNombre,
            'personal' => $personal->map(function($p) {
                return [
                    'id' => $p->id,
                    'nombre' => $p->persona ? $p->persona->nombre_completo : $p->name,
                ];
            })
        ];
    })->values();

    // 3. Retornamos la estructura que Angular espera
    return response()->json([
        'equipo_visible' => $agrupado
    ]);
}    
public function verTurnosPorJerarquia(Request $request, $servicioId)
    {
        $user = auth()->user();
        $nivelUsuario = $user->categoria->nivel; 
        
        $fechaConsulta = $request->query('fecha', now()->format('Y-m-d'));

        // Verificamos si el servicio existe
        if(!Servicio::find($servicioId)) {
            return response()->json(['message' => 'Servicio no encontrado.'], 404);
        }

        $turnos = TurnoAsignado::with(['usuario.persona', 'usuario.categoria', 'turno'])
            ->where('servicio_id', $servicioId)
            ->where('fecha', $fechaConsulta)
            ->whereHas('usuario.categoria', function($query) use ($nivelUsuario) {
                $query->where('nivel', '>=', $nivelUsuario);
            })
            ->get()
            ->groupBy('usuario.categoria.nombre');

        return response()->json([
            'fecha' => $fechaConsulta,
            'mi_nivel' => $nivelUsuario,
            'equipo_visible' => $turnos
        ]);
    }

   public function intercambiarTurno(Request $request)
{
    try {
        $request->validate([
            'asignacion_id_1' => 'required|integer',
            'asignacion_id_2' => 'required|integer',
        ]);

        // Usamos find para controlar manualmente si no existen
        $a1 = TurnoAsignado::find($request->asignacion_id_1);
        $a2 = TurnoAsignado::find($request->asignacion_id_2);

        if (!$a1 || !$a2) {
            return response()->json(['message' => 'Una o ambas asignaciones no existen.'], 404);
        }

        // Validar mismo servicio y semana
        if ($a1->servicio_id != $a2->servicio_id || $a1->semana_id != $a2->semana_id) {
            return response()->json(['message' => 'Los turnos deben ser del mismo servicio y semana.'], 422);
        }

        \DB::transaction(function () use ($a1, $a2) {
            $idOriginal1 = $a1->usuario_id;
            $idOriginal2 = $a2->usuario_id;

            // Intercambio
            $a1->usuario_id = $idOriginal2;
            $a1->observacion = "Intercambiado (era de usuario {$idOriginal1})";
            $a1->save();

            $a2->usuario_id = $idOriginal1;
            $a2->observacion = "Intercambiado (era de usuario {$idOriginal2})";
            $a2->save();
        });

        return response()->json(['message' => '¡Intercambio realizado con éxito!']);

    } catch (\Exception $e) {
        // Esto te dirá qué pasó exactamente en el JSON en lugar de dar error 500
        return response()->json([
            'message' => 'Error interno',
            'error' => $e->getMessage()
        ], 500);
    }
    }

    /**
     * 3. REPORTE MENSUAL
     */
    public function reporteMensual($mes_id)
    {
        $user = auth()->user();
        $nivelMinimo = $user->categoria->nivel;

        $turnos = TurnoAsignado::with(['usuario.persona', 'usuario.categoria', 'servicio', 'turno'])
            ->where('mes_id', $mes_id)
            ->whereHas('usuario.categoria', function($q) use ($nivelMinimo) {
                $q->where('nivel', '>=', $nivelMinimo);
            })
            ->orderBy('fecha', 'asc')
            ->get();

        return response()->json($turnos);
    }

    /**
     * 4. MIS TURNOS
     */
   public function misTurnos()
{
    $turnos = TurnoAsignado::with(['servicio', 'turno', 'usuario.persona', 'usuario.categoria'])
        ->where('usuario_id', auth()->id())
        ->orderBy('fecha', 'asc')
        ->get();

    return \App\Http\Resources\TurnoAsignadoResource::collection($turnos);
}
public function reporteHorasSemana(Request $request, $semana_id, $usuario_id = null)
{
    $userAutenticado = auth()->user();
    $nivelMinimo = $userAutenticado->categoria->nivel;

    // 1. Iniciamos la consulta base
    $query = TurnoAsignado::with(['usuario.persona', 'turno'])
        ->where('semana_id', $semana_id);

    // 2. Si se pasa un usuario_id, filtramos por él. Si no, traemos a todos los visibles por jerarquía.
    if ($usuario_id) {
        $query->where('usuario_id', $usuario_id);
    } else {
        $query->whereHas('usuario.categoria', function($q) use ($nivelMinimo) {
            $q->where('nivel', '>=', $nivelMinimo);
        });
    }

    $turnos = $query->get();

    // 3. Procesamos los datos agrupando por usuario
    $reporte = $turnos->groupBy('usuario_id')->map(function ($asignaciones) {
        $usuario = $asignaciones->first()->usuario;
        
        $totalMinutos = $asignaciones->sum(function ($a) {
            $inicio = Carbon::parse($a->turno->hora_inicio);
            $fin = Carbon::parse($a->turno->hora_fin);

            // Si el turno termina al día siguiente (ej. 22:00 a 06:00)
            if ($fin->lessThan($inicio)) {
                $fin->addDay();
            }
            return $inicio->diffInMinutes($fin);
        });

        return [
            'id' => $usuario->id,
            'nombre' => $usuario->persona->nombre_completo ?? $usuario->name,
            'total_horas' => round($totalMinutos / 60, 2),
            'conteo_turnos' => $asignaciones->count(),
            'detalle' => $asignaciones->map(fn($a) => [
                'fecha' => $a->fecha,
                'turno' => $a->turno->nombre_turno,
                'rango' => "{$a->turno->hora_inicio} - {$a->turno->hora_fin}"
            ])
        ];
    })->values();

    return response()->json([
        'semana_id' => $semana_id,
        'data' => $reporte
    ]);
}
/**
 * 5. OBTENER EQUIPO FILTRADO (Para el frontend de Angular)
 * Filtra el personal de un servicio y opcionalmente por su categoría.
 */
public function getEquipoFiltrado(Request $request)
{
    try {
        // Obtenemos los IDs desde la URL (?servicio_id=X&categoria_id=Y)
        $servicio_id = $request->query('servicio_id');
        $categoria_id = $request->query('categoria_id');

        // 1. Empezamos la consulta con el modelo User
        // Filtramos por la relación 'servicios' (debe estar definida en tu modelo User)
        $query = \App\Models\User::whereHas('servicios', function($q) use ($servicio_id) {
            $q->where('servicios.id', $servicio_id);
        });

        // 2. Filtramos por categoría si no se seleccionó "Todas"
        // En tu HeidiSQL vimos que la columna se llama 'categoria_id'
        if ($categoria_id && $categoria_id !== 'Todas' && $categoria_id !== 'null') {
            $query->where('categoria_id', $categoria_id);
        }

        // 3. Obtenemos los datos con sus relaciones para evitar errores
        $equipo = $query->with(['persona', 'categoria'])->get();

        // 4. Mapeamos el resultado para que coincida con lo que espera tu Angular
        $resultado = $equipo->map(function($user) {
            return [
                'usuario_id'     => $user->id,
                'usuario_nombre' => $user->persona ? $user->persona->nombre_completo : $user->name,
                'categoria_nombre' => $user->categoria ? $user->categoria->nombre : 'Sin categoría',
                'turnos'         => [] // Esto evitará errores en el *ngFor de tu HTML
            ];
        });

        return response()->json([
            'status' => 'success',
            'equipo_visible' => $resultado
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error en el servidor: ' . $e->getMessage()
        ], 500);
    }
}
// Agrega esto al final de tu TurnoAsignadoController
public function listaCategorias()
{
    try {
        // Usamos 'nombre' porque es como se llama en tu HeidiSQL
        $categorias = \App\Models\Categoria::select('id', 'nombre')->get();
        return response()->json([
            'status' => 'success',
            'data' => $categorias
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
}

}