<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TurnoAsignado;
use App\Models\Semana;
use App\Models\Turno;
use App\Models\Servicio;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB; // IMPORTANTE: Para corregir el error "Class DB not found"

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

// En Laravel: TurnoAsignadoController.php
return response()->json([
    'status' => 'success',
    'data' => $resultado  // Cambia 'equipo_visible' por 'data'
], 200);
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
            $turnosModelo = TurnoAsignado::where('semana_id', $request->semana_id)
                ->where('servicio_id', $request->servicio_id)
                ->get();

            if ($turnosModelo->isEmpty()) {
                return response()->json(['message' => 'La semana seleccionada no tiene turnos.'], 422);
            }

            $otrasSemanas = Semana::where('mes_id', $request->mes_id)
                ->where('id', '!=', $request->semana_id)
                ->get();

            // IMPORTANTE: Limpiar turnos existentes en las semanas destino antes de replicar
            // Esto evita que las horas se dupliquen (el error de las 24h)
            TurnoAsignado::whereIn('semana_id', $otrasSemanas->pluck('id'))
                ->where('servicio_id', $request->servicio_id)
                ->delete();

            $conteo = 0;

            foreach ($otrasSemanas as $semanaDestino) {
                $inicioDestino = Carbon::parse($semanaDestino->fecha_inicio);

                foreach ($turnosModelo as $t) {
                    $fechaOriginal = Carbon::parse($t->fecha);
                    
                    // Calculamos la diferencia de días respecto al inicio de SU propia semana
                    // Si el turno era Lunes y la semana empezó Lunes, la diferencia es 0.
                    $semanaOriginal = Semana::find($t->semana_id);
                    $diferenciaDias = Carbon::parse($semanaOriginal->fecha_inicio)->diffInDays($fechaOriginal);

                    $nuevoTurno = $t->replicate();
                    $nuevoTurno->semana_id = $semanaDestino->id;
                    
                    // La nueva fecha es: Inicio de semana destino + los mismos días de diferencia
                    $nuevoTurno->fecha = $inicioDestino->copy()->addDays($diferenciaDias)->format('Y-m-d');
                    
                    $nuevoTurno->save();
                    $conteo++;
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => "¡Proceso completado! Se han replicado {$conteo} turnos."
            ]);
        });
    } catch (\Exception $e) {
        return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
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

public function rotarPersonalPorMes(Request $request)
    {
        $servicioId = $request->servicio_id;
        $mesOrigenId = $request->mes_id;
        $mesDestinoId = $request->mes_destino;

        // 1. Obtener personal único para el mapa circular
        $usuarios = DB::table('turnos_asignados')
            ->where('servicio_id', $servicioId)
            ->where('mes_id', $mesOrigenId)
            ->distinct()
            ->pluck('usuario_id')
            ->toArray();

        if (count($usuarios) < 2) {
            return response()->json(['status' => 'error', 'message' => 'Mínimo 2 personas.'], 400);
        }

        $mapaRotacion = [];
        for ($i = 0; $i < count($usuarios); $i++) {
            $indiceSiguiente = ($i + 1) % count($usuarios);
            $mapaRotacion[$usuarios[$i]] = $usuarios[$indiceSiguiente];
        }

        DB::beginTransaction();
        try {
            // 2. Limpiar mes destino
            DB::table('turnos_asignados')
                ->where('servicio_id', $servicioId)
                ->where('mes_id', $mesDestinoId)
                ->delete();

            // 3. Obtener turnos de origen
            $turnosOrigen = DB::table('turnos_asignados')
                ->where('servicio_id', $servicioId)
                ->where('mes_id', $mesOrigenId)
                ->get();

            foreach ($turnosOrigen as $turno) {
                $nuevoUsuarioId = $mapaRotacion[$turno->usuario_id];
                
                // --- LÓGICA DE COINCIDENCIA SEMANAL ---
                // Obtenemos qué número de semana (1, 2, 3...) y qué día (lunes, martes...) es
                $infoSemanaOrigen = DB::table('semanas')->where('id', $turno->semana_id)->first();
                $diaDeLaSemana = Carbon::parse($turno->fecha)->dayOfWeek; // 0 (dom) a 6 (sab)

                // Buscamos la semana equivalente en el mes de destino
                // Ejemplo: Si era Semana 2 de Marzo, buscamos Semana 2 de Abril
                $semanaDestino = DB::table('semanas')
                    ->where('mes_id', $mesDestinoId)
                    ->where('numero_semana', $infoSemanaOrigen->numero_semana)
                    ->first();

                if ($semanaDestino) {
                    // Calculamos la fecha exacta del mismo día en la nueva semana
                    $nuevaFecha = Carbon::parse($semanaDestino->fecha_inicio)->addDays(
                        ($diaDeLaSemana == 0 ? 6 : $diaDeLaSemana - 1) // Ajuste para que Lunes sea 0
                    );

                    DB::table('turnos_asignados')->insert([
                        'usuario_id'  => $nuevoUsuarioId,
                        'servicio_id' => $servicioId,
                        'turno_id'    => $turno->turno_id,
                        'mes_id'      => $mesDestinoId,
                        'semana_id'   => $semanaDestino->id,
                        'gestion_id'  => $turno->gestion_id,
                        'fecha'       => $nuevaFecha->toDateString(),
                        'estado'      => 'programado',
                        'created_at'  => now(),
                        'updated_at'  => now()
                    ]);
                }
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Rotación lógica completada.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}


public function actualizarPosicion(Request $request)
{
    $request->validate([
        'turno_id'         => 'required|exists:turnos_asignados,id',
        'nuevo_usuario_id' => 'required|exists:users,id',
        'nueva_fecha'      => 'required|date',
    ]);

    try {
        return DB::transaction(function () use ($request) {
            // Cargamos el origen con sus relaciones
            $asigOrigen = TurnoAsignado::with(['usuario.persona'])->findOrFail($request->turno_id);
            
            $fechaAntigua = $asigOrigen->fecha;
            $userAntiguo  = $asigOrigen->usuario_id;
            $semanaAntigua = $asigOrigen->semana_id;

            // Evitar el error de "nombre_completo on null"
            $nombreOrigen = $asigOrigen->usuario?->persona?->nombre_completo ?? 'Usuario Origen';

            $fechaNueva = Carbon::parse($request->nueva_fecha)->format('Y-m-d');

            // Buscamos si hay alguien en el destino
            $asigDestino = TurnoAsignado::with(['usuario.persona'])
                ->where('usuario_id', $request->nuevo_usuario_id)
                ->where('fecha', $fechaNueva)
                ->where('servicio_id', $asigOrigen->servicio_id)
                ->first();

            if ($asigDestino) {
                $nombreDestino = $asigDestino->usuario?->persona?->nombre_completo ?? 'Usuario Destino';

                // Intercambio Físico: El de destino se va a la posición de origen
                $asigDestino->update([
                    'usuario_id'  => $userAntiguo,
                    'fecha'       => $fechaAntigua,
                    'semana_id'   => $semanaAntigua,
                    'observacion' => "Intercambio: Cedió lugar a $nombreOrigen"
                ]);

                // El de origen se va a la posición de destino
                $asigOrigen->update([
                    'usuario_id'  => $request->nuevo_usuario_id,
                    'fecha'       => $fechaNueva,
                    'semana_id'   => $this->obtenerSemanaIdPorFecha($fechaNueva),
                    'observacion' => "Intercambio: Tomó lugar de $nombreDestino"
                ]);

                $mensaje = "Intercambio realizado exitosamente.";
            } else {
                // Movimiento simple
                $asigOrigen->update([
                    'usuario_id' => $request->nuevo_usuario_id,
                    'fecha'      => $fechaNueva,
                    'semana_id'  => $this->obtenerSemanaIdPorFecha($fechaNueva),
                    'observacion' => "Desplazamiento a $fechaNueva"
                ]);
                $mensaje = "Turno movido correctamente.";
            }

            return response()->json(['status' => 'success', 'message' => $mensaje]);
        });
    } catch (\Exception $e) {
        // Esto te dirá exactamente qué falló en la consola si vuelve a dar error
        return response()->json([
            'status' => 'error', 
            // Usamos $e->getLine() para saber dónde falló exactamente
            'message' => 'Error en línea ' . $e->getLine() . ': ' . $e->getMessage() 
        ], 500);
    }
}


// Función auxiliar para no romper el calendario
private function obtenerSemanaIdPorFecha($fecha) {
    return DB::table('semanas')
        ->where('fecha_inicio', '<=', $fecha)
        ->where('fecha_fin', '>=', $fecha)
        ->value('id');
}

public function update(Request $request, $id)
{
    try {
        $request->validate([
            'turno_id'    => 'required|exists:turnos,id',
            'observacion' => 'nullable|string|max:500',
            'estado'      => 'nullable|string'
        ]);

        $asignacion = TurnoAsignado::findOrFail($id);

        $asignacion->update([
            'turno_id'    => $request->turno_id,
            'observacion' => $request->observacion ?? $asignacion->observacion,
            'estado'      => $request->estado ?? $asignacion->estado,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Turno modificado correctamente',
            'data'    => $asignacion->load(['turno', 'usuario.persona'])
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Error al actualizar: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * ELIMINAR UN TURNO ESPECÍFICO
 */
public function destroy($id)
{
    try {
        $asignacion = TurnoAsignado::findOrFail($id);
        $asignacion->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Asignación eliminada correctamente'
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

}