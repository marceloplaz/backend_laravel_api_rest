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
    $userAutenticado = auth()->user();

    $request->validate([
        'usuario_id'  => 'required|exists:users,id',
        'turno_id'    => 'required|exists:turnos,id',
        'fecha'       => 'required|date',
        'observacion' => 'nullable|string|max:500'
    ]);

    $fechaFormateada = Carbon::parse($request->fecha)->format('Y-m-d');

    // 1. OBTENER EL SERVICIO AUTOMÁTICAMENTE
    $asignacionServicio = \App\Models\UsuarioServicio::where('usuario_id', $request->usuario_id)
        ->where('estado', true)
        ->first();

    if (!$asignacionServicio) {
        return response()->json([
            'message' => 'El usuario no tiene un servicio activo asignado.'
        ], 422);
    }

    $servicioId = $asignacionServicio->servicio_id;

    $semana = Semana::where('fecha_inicio', '<=', $fechaFormateada)
        ->where('fecha_fin', '>=', $fechaFormateada)
        ->with('mes.gestion')
        ->first();

    if (!$semana) {
        return response()->json(['message' => 'La fecha no existe en el calendario.'], 422);
    }

    // 3. VALIDACIÓN DE CHOQUE (Opcional: déjala si no quieres que una persona tenga 2 turnos a la misma hora)
    // ... (código de choque de horarios) ...

    // 4. CREAR LA ASIGNACIÓN
    $asignacion = TurnoAsignado::create([
        'usuario_id'  => $request->usuario_id,
        'servicio_id' => $servicioId,
        'turno_id'    => $request->turno_id,
        'semana_id'   => $semana->id,
        'mes_id'      => $semana->mes_id,
        'gestion_id'  => $semana->mes->gestion_id,
        'fecha'       => $fechaFormateada,
        'estado'      => 'programado',
        'observacion' => $request->observacion
    ]);

    return response()->json([
        'message' => 'Turno asignado correctamente', 
        'data' => $asignacion->load(['usuario.persona', 'turno'])
    ], 201);
}
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
        $servicio_id = $request->query('servicio_id');
        $categoria_id = $request->query('categoria_id');
        $semana_id = $request->query('semana_id');

        // 1. Consulta base de usuarios en el servicio
        $query = \App\Models\User::whereHas('servicios', function($q) use ($servicio_id) {
            $q->where('servicios.id', $servicio_id);
        });

        // 2. FILTRO SEGURO: Solo filtramos si categoria_id es un número
        // Esto evita el Error 500 cuando llega "Todas" o "Médicos" como string
        if ($categoria_id && is_numeric($categoria_id)) {
            $query->where('categoria_id', $categoria_id);
        }

        // 3. Carga de datos y turnos de la semana
        $equipo = $query->with(['persona', 'categoria', 'turnosAsignados' => function($q) use ($semana_id) {
            $q->where('semana_id', $semana_id)->with('turno');
        }])->get();

        // 4. Mapeo para el formato que espera tu Angular
        $resultado = $equipo->map(function($user) {
            return [
                'usuario_id'       => $user->id,
                'usuario_nombre'   => $user->persona ? $user->persona->nombre_completo : $user->name,
                'categoria_nombre' => $user->categoria ? $user->categoria->nombre : 'Sin categoría',
                'turnos' => $user->turnosAsignados->map(function($ta) {
                    return [
                        'id_asignacion' => $ta->id,
                        'nombre_turno'  => $ta->turno->nombre_turno,
                        'horario'       => $ta->turno->hora_inicio . ' - ' . $ta->turno->hora_fin,
                        'fecha'         => $ta->fecha,
                        'color'         => $ta->turno->color ?? '#52600c'
                    ];
                })       
            ];
        });

        return response()->json([
            'status' => 'success',
            'equipo_visible' => $resultado
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage()
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

/**
 * REPLICAR SEMANA EN EL MES
 * Toma todos los turnos de una semana específica y los copia a las demás semanas del mismo mes.
 */
public function replicarMes(Request $request)
{
    $request->validate([
        'servicio_id' => 'required|exists:servicios,id',
        'mes_id'      => 'required|exists:meses,id',
        'semana_id'   => 'required|exists:semanas,id',
    ]);

    try {
        return \DB::transaction(function () use ($request) {
            // 1. Obtener los turnos de la semana "modelo" que queremos copiar
            $turnosModelo = TurnoAsignado::where('semana_id', $request->semana_id)
                ->where('servicio_id', $request->servicio_id)
                ->get();

            if ($turnosModelo->isEmpty()) {
                return response()->json(['message' => 'La semana seleccionada no tiene turnos para replicar.'], 422);
            }

            // 2. Obtener las demás semanas que pertenecen al mismo mes
            $otrasSemanas = Semana::where('mes_id', $request->mes_id)
                ->where('id', '!=', $request->semana_id)
                ->get();

            $conteo = 0;

            foreach ($otrasSemanas as $semanaDestino) {
                foreach ($turnosModelo as $t) {
                    // Replicamos el registro. replicate() crea una copia del modelo sin ID.
                    $nuevoTurno = $t->replicate();
                    
                    // Ajustamos la semana_id a la nueva semana de destino
                    $nuevoTurno->semana_id = $semanaDestino->id;
                    
                    /** * IMPORTANTE: La fecha exacta debe ajustarse al día de la semana.
                     * Si el original fue un Lunes, el nuevo debe ser el Lunes de la semana destino.
                     */
                    $diaOriginal = Carbon::parse($t->fecha)->dayOfWeek;
                    $nuevaFecha = Carbon::parse($semanaDestino->fecha_inicio)->addDays($diaOriginal - 1);
                    
                    $nuevoTurno->fecha = $nuevaFecha->format('Y-m-d');
                    $nuevoTurno->save();
                    $conteo++;
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => "¡Proceso completado! Se han replicado {$conteo} turnos en el mes."
            ]);
        });
    } catch (\Exception $e) {
        return response()->json(['error' => 'Error al replicar: ' . $e->getMessage()], 500);
    }
}

/**
 * VACIAR TURNOS DEL MES
 * Elimina todas las asignaciones de un servicio específico en un mes completo.
 */
public function vaciarMes(Request $request)
{
    $request->validate([
        'servicio_id' => 'required|exists:servicios,id',
        'mes_id'      => 'required|exists:meses,id',
    ]);

    try {
        $eliminados = TurnoAsignado::where('mes_id', $request->mes_id)
            ->where('servicio_id', $request->servicio_id)
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Se han eliminado {$eliminados} turnos del mes seleccionado."
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Error al vaciar: ' . $e->getMessage()], 500);
    }
}
/**
 * ROTACIÓN MENSUAL
 * Toma todos los turnos de un mes base y los replica en el mes siguiente,
 * pero rotando al personal una posición.
 */
public function rotarPersonalPorMes(Request $request)
{
    $request->validate([
        'servicio_id' => 'required|exists:servicios,id',
        'mes_base_id' => 'required|exists:meses,id', // Mes de donde copiamos
        'mes_dest_id' => 'required|exists:meses,id', // Mes a donde rotamos
    ]);

    try {
        return \DB::transaction(function () use ($request) {
            // 1. Obtener el equipo del servicio (el orden define la rotación)
            $equipoIds = \App\Models\UsuarioServicio::where('servicio_id', $request->servicio_id)
                ->where('estado', true)
                ->pluck('usuario_id')
                ->toArray();
            
            $totalPersonal = count($equipoIds);

            if ($totalPersonal === 0) {
                return response()->json(['message' => 'No hay personal para rotar.'], 422);
            }

            // 2. Obtener todos los turnos del mes base
            $turnosBase = TurnoAsignado::where('mes_id', $request->mes_base_id)
                ->where('servicio_id', $request->servicio_id)
                ->get();

            // 3. Obtener las semanas del mes destino para mapear las fechas
            $semanasDestino = \App\Models\Semana::where('mes_id', $request->mes_dest_id)
                ->orderBy('id', 'asc')
                ->get();

            $conteo = 0;

            foreach ($turnosBase as $t) {
                $nuevoTurno = $t->replicate();

                // --- LÓGICA DE ROTACIÓN MENSUAL (+1 posición) ---
                $posicionActual = array_search($t->usuario_id, $equipoIds);
                if ($posicionActual !== false) {
                    $nuevaPosicion = ($posicionActual + 1) % $totalPersonal;
                    $nuevoTurno->usuario_id = $equipoIds[$nuevaPosicion];
                }

                // --- MAPEO DE FECHAS AL NUEVO MES ---
                // Buscamos a qué número de semana pertenecía (1, 2, 3...)
                $numSemanaOriginal = \App\Models\Semana::where('mes_id', $request->mes_base_id)
                    ->where('id', '<=', $t->semana_id)
                    ->count();

                // Asignamos la semana correspondiente en el mes destino
                $semanaDestino = $semanasDestino[$numSemanaOriginal - 1] ?? $semanasDestino->last();
                
                $nuevoTurno->semana_id = $semanaDestino->id;
                $nuevoTurno->mes_id = $request->mes_dest_id;

                // Ajustar la fecha manteniendo el día de la semana (ej: el primer lunes del mes)
                $diaSemana = Carbon::parse($t->fecha)->dayOfWeek;
                $nuevoTurno->fecha = Carbon::parse($semanaDestino->fecha_inicio)
                    ->addDays($diaSemana - 1)
                    ->format('Y-m-d');

                $nuevoTurno->observacion = "Rotación mensual desde mes ID: {$request->mes_base_id}";
                $nuevoTurno->save();
                $conteo++;
            }

            return response()->json([
                'status' => 'success',
                'message' => "Rotación mensual completada. Se generaron {$conteo} turnos para el nuevo mes."
            ]);
        });
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

public function actualizarPosicion(Request $request)
{
    try {
        $request->validate([
            'turno_id'         => 'required|exists:turnos_asignados,id',
            'nuevo_usuario_id' => 'required|exists:users,id',
            'nueva_fecha'      => 'required|date',
        ]);

        $asignacionOrigen = TurnoAsignado::findOrFail($request->turno_id);
        
        // Guardamos los datos originales para el posible intercambio
        $antiguoUsuarioId = $asignacionOrigen->usuario_id;
        $antiguaFecha = $asignacionOrigen->fecha;
        $antiguaSemanaId = $asignacionOrigen->semana_id;
        $antiguoMesId = $asignacionOrigen->mes_id;

        // 1. Buscamos a qué semana pertenece la nueva fecha
        $fechaFormateada = \Carbon\Carbon::parse($request->nueva_fecha)->format('Y-m-d');
        $semanaDestino = Semana::where('fecha_inicio', '<=', $fechaFormateada)
            ->where('fecha_fin', '>=', $fechaFormateada)->first();

        // 2. ¿HAY UN TURNO EN EL DESTINO? (Intercambio)
        $asignacionDestino = TurnoAsignado::where('usuario_id', $request->nuevo_usuario_id)
            ->where('fecha', $fechaFormateada)
            ->first();

        if ($asignacionDestino) {
            // El turno que estaba en el destino se va al origen
            $asignacionDestino->update([
                'usuario_id' => $antiguoUsuarioId,
                'fecha'      => $antiguaFecha,
                'semana_id'  => $antiguaSemanaId,
                'mes_id'     => $antiguoMesId
            ]);
        }

        // 3. El turno que arrastramos se va al destino
        $asignacionOrigen->update([
            'usuario_id' => $request->nuevo_usuario_id,
            'fecha'      => $fechaFormateada,
            'semana_id'  => $semanaDestino->id,
            'mes_id'     => $semanaDestino->mes_id
        ]);

   return response()->json([
    'status'      => 'success',
    'message'     => $asignacionDestino ? 'Intercambio realizado' : 'Turno movido',
    'intercambio' => (bool)$asignacionDestino,
    'detalles'    => [
        'origen_nombre'  => $asignacionOrigen->usuario->name,
        'destino_nombre' => $asignacionDestino ? $asignacionDestino->usuario->name : 'Nadie',
    ],
    'data'        => $asignacionOrigen->load('usuario', 'turno', 'semana')
]);

    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}

}