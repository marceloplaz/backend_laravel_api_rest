<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TurnoAsignado;
use App\Models\Semana;
use App\Models\Turno;
use App\Models\Servicio;
use App\Models\Categoria;
use App\Models\User;          
use App\Models\NovedadLaboral;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB; // IMPORTANTE: Para corregir el error "Class DB not found"
use Barryvdh\DomPDF\Facade\Pdf;

class TurnoAsignadoController extends Controller
{
    /**
     * 1. REGISTRAR TURNO
     * Validamos que el usuario solo use turnos de SU categoría y en servicios permitidos.
     */
public function store(Request $request)
{
    $request->validate([
        'usuario_id'       => 'required|exists:users,id',
        'turno_id'         => 'required|exists:turnos,id',
        'area_id'          => 'nullable|exists:areas,id',
        'fecha'            => 'nullable|date',
        'fechas_multiples' => 'nullable|array',
        'observacion'      => 'nullable|string|max:500'
    ]);

    // Obtener servicio activo
    $asignacionServicio = \App\Models\UsuarioServicio::where('usuario_id', $request->usuario_id)
        ->where('estado', true)
        ->first();

    if (!$asignacionServicio) {
        return response()->json(['message' => 'El usuario no tiene un servicio activo.'], 422);
    }

    // Limpiamos fechas (por si llega un array vacío)
    $fechasAProcesar = $request->fechas_multiples ?? ($request->fecha ? [$request->fecha] : []);

    if (empty($fechasAProcesar)) {
        return response()->json(['message' => 'No se han seleccionado fechas para asignar.'], 400);
    }

    try {
        \DB::transaction(function () use ($request, $fechasAProcesar, $asignacionServicio) {
            foreach ($fechasAProcesar as $fecha) {
                $fechaFormateada = Carbon::parse($fecha)->format('Y-m-d');

               $semana = \App\Models\Semana::where('fecha_inicio', '<=', $fechaFormateada)
                    ->where('fecha_fin', '>=', $fechaFormateada)
                    ->with('mes') // Cargamos el mes para evitar N+1
                    ->first();

                if (!$semana) continue;

                // Usamos updateOrCreate para evitar duplicados en la misma fecha
                TurnoAsignado::updateOrCreate(
                    [
                        'usuario_id' => $request->usuario_id,
                        'fecha'      => $fechaFormateada,
                        'turno_id'   => $request->turno_id
                    ],
                    [
                        'servicio_id' => $asignacionServicio->servicio_id,
                        'area_id'     => $request->area_id,
                        'turno_id'    => $request->turno_id,
                        'semana_id'   => $semana->id,
                        'mes_id'      => $semana->mes_id,
                        'gestion_id'  => $semana->mes->gestion_id,
                        'estado'      => 'programado',
                        'observacion' => $request->observacion
                    ]
                );
            }
        });

        return response()->json(['message' => 'Turnos procesados correctamente'], 201);

    } catch (\Exception $e) {
        return response()->json(['message' => 'Error al guardar: ' . $e->getMessage()], 500);
    }
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



public function reporteSemanal(Request $request, $semana_id)
{
    $servicio_id = $request->query('servicio_id');
    $categoria_id = $request->query('categoria_id');

    $semana = \App\Models\Semana::with('mes')->findOrFail($semana_id);
    $servicio = \App\Models\Servicio::findOrFail($servicio_id);
    $categoria = \App\Models\Categoria::findOrFail($categoria_id);

    $usuarios = \App\Models\User::with([
    'persona', 
    'turnosAsignados' => function($q) use ($semana_id) {
        // Traemos todos los turnos de la semana, sin filtrar por servicio aquí
        $q->where('semana_id', $semana_id)
          ->with(['turno', 'area', 'novedad', 'servicio']); 
    }
])
// El filtro de abajo asegura que solo salgan los usuarios que pertenecen a este servicio
->whereHas('servicios', function($q) use ($servicio_id) {
    $q->where('servicios.id', $servicio_id);
})
->where('categoria_id', $categoria_id)
->get();
    $data = [
        'servicio'  => $servicio,
        'categoria' => $categoria,
        'mes'       => $semana->mes->nombre,
        'periodo'   => "Semana {$semana->numero_semana} ({$semana->fecha_inicio} a {$semana->fecha_fin})",
        'fecha_inicio_limpia' => $semana->fecha_inicio,
        'usuarios'  => $usuarios 
    ];

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reporteSemanal', $data)
                ->setPaper('a4', 'landscape');
    
    return $pdf->stream("Reporte_Semanal_{$servicio->nombre}.pdf");
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
    // 1. Cargamos los turnos con las relaciones exactas que mapea tu interfaz
    $turnos = TurnoAsignado::with(['servicio', 'turno', 'area', 'usuario.persona'])
        ->where('usuario_id', auth()->id())
        ->orderBy('fecha', 'asc')
        ->get();

    // 2. Formateamos la colección respetando el estándar plano que ya lee tu UI
    $coleccionFormateada = $turnos->map(function($ta) {
        return [
            'id_asignacion' => $ta->id,
            'nombre_turno'  => $ta->turno?->nombre_turno ?? 'Sin Nombre',
            'fecha'         => $ta->fecha,
            'horario'       => $ta->turno ? "{$ta->turno->hora_inicio} - {$ta->turno->hora_fin}" : 'N/A',
            'color'         => $ta->turno?->color ?? '#28a745',
            'area_nombre'   => $ta->area ? $ta->area->nombre : ($ta->servicio ? $ta->servicio->nombre : 'GENERAL'),
            
            // 🌟 ANCLAJE DE RELACIONES PARA TU INTERFAZ 'TurnoAsignado' de Angular
            'turno' => $ta->turno ? [
                'nombre_turno'   => $ta->turno->nombre_turno,
                'hora_inicio'    => $ta->turno->hora_inicio,
                'hora_fin'       => $ta->turno->hora_fin,
                'duracion_horas' => (float)($ta->turno->duracion_horas ?? 0) // Viaja seguro dentro del objeto turno
            ] : null,
            
            'area' => [
                'nombre' => $ta->area ? $ta->area->nombre : ($ta->servicio ? $ta->servicio->nombre : 'GENERAL')
            ]
        ];
    });

    // 3. Retornamos directamente la colección como un Array Plano para mantener compatibilidad
    return response()->json($coleccionFormateada);
}






public function getEquipoFiltrado(Request $request)
    {
        try {
            $servicio_id = $request->query('servicio_id');
            $categoria_id = $request->query('categoria_id');
            $semana_id = $request->query('semana_id');

            // 1. Obtener los usuarios del servicio/categoría
            $query = User::whereHas('servicios', function($q) use ($servicio_id) {
            $q->where('servicios.id', $servicio_id)
              ->where('usuario_servicios.estado', 1); // Solo personal ACTIVO en este servicio
        });

            if ($categoria_id && is_numeric($categoria_id)) {
                $query->where('categoria_id', $categoria_id);
            }

            $equipo = $query->with(['persona', 'categoria', 'turnosAsignados' => function($q) use ($semana_id) {
                $q->where('semana_id', $semana_id)
                  ->with(['turno', 'area', 'novedad.solicitante.persona', 'novedad.reemplazo.persona']); 
            }])->get();

            // 2. Procesar cada usuario para incluir turnos donde es titular y donde es reemplazo
            // ... después de obtener la variable $equipo ...

$resultado = $equipo->map(function($user) use ($semana_id) {
    
    // 1. FORZAR REFRESCO: Obligamos a recargar las novedades del titular
    // Esto asegura que Melisa vea su cuadro naranja inmediatamente después del primer cambio.
    $user->loadMissing(['turnosAsignados.novedad.solicitante.persona', 'turnosAsignados.novedad.reemplazo.persona']);

    // A. Turnos donde el usuario es el titular original (Melisa)
    $turnosDirectos = $user->turnosAsignados->map(function($ta) {
        // Si el usuario cambió su turno, $ta->novedad ahora tendrá datos reales
        return $this->formatearTurno($ta, $ta->novedad);
    });

    // B. Turnos donde el usuario es el REEMPLAZO (Leni - Turnos virtuales)
    $novedadesComoReemplazo = NovedadLaboral::where('usuario_reemplazo_id', $user->id)
        ->whereHas('asignacion', function($q) use ($semana_id) {
            $q->where('semana_id', $semana_id);
        })
        ->with(['asignacion.turno', 'asignacion.area', 'solicitante.persona', 'reemplazo.persona'])
        ->get();

    $turnosVirtuales = $novedadesComoReemplazo->map(function($nov) {
        // Vinculamos manualmente la novedad a la asignación para el formateador
        $asignacion = $nov->asignacion;
        $asignacion->setRelation('novedad', $nov);
        return $this->formatearTurno($asignacion, $nov);
    });

    // C. Combinar y priorizar novedades
    // Usamos el ID de asignación para que, si Melisa cedió un turno, 
    // no aparezca el original verde y el nuevo naranja al mismo tiempo.
    $todosLosTurnos = $turnosDirectos->concat($turnosVirtuales)
        ->unique('id_asignacion')
        ->values();

    return [
        'usuario_id'       => $user->id,
        'usuario_nombre'   => $user->persona ? $user->persona->nombre_completo : $user->name,
        'categoria_nombre' => $user->categoria ? $user->categoria->nombre : 'Sin categoría',
        'tipo_salario'  => $user->persona ? $user->persona->tipo_salario : 'No definido',
        'turnos'           => $todosLosTurnos
    ];
});
            return response()->json([
                'status' => 'success',
                'equipo_visible' => $resultado
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper para unificar el formato del JSON
     */
private function formatearTurno($ta, $novedad) {
    // Extraemos los nombres de la columna 'nombre_completo'
    $nombreSolicitante = $novedad?->solicitante?->persona?->nombre_completo ?? 'N/A';
    $nombreReemplazo   = $novedad?->reemplazo?->persona?->nombre_completo ?? 'N/A';

    $horaInicioFormateada = $ta->turno ? Carbon::parse($ta->turno->hora_inicio)->format('H:i') : '00:00';
    $horaFinFormateada    = $ta->turno ? Carbon::parse($ta->turno->hora_fin)->format('H:i') : '00:00';

    return [
        'id_asignacion'  => $ta->id,
        'nombre_turno'   => $ta->turno?->nombre_turno ?? 'Sin Turno',
        'hora_inicio'    => $horaInicioFormateada, 
        'hora_fin'       => $horaFinFormateada,
        'horario'        => $ta->turno ? "{$ta->turno->hora_inicio} - {$ta->turno->hora_fin}" : 'N/A',
        
        // 🌟 ¡AQUÍ MISMO EN LA RAÍZ! Directo y sin vueltas
        'duracion_horas' => $ta->turno ? (float)$ta->turno->duracion_horas : 0,
        
        'fecha'          => $ta->fecha,
        'color'          => $novedad ? '#fd7e14' : ($ta->turno->color ?? '#52600c'),
        'area_nombre'    => $ta->area ? $ta->area->nombre : ($ta->servicio ? $ta->servicio->nombre : 'GENERAL'),

        'novedad' => $novedad ? [
            'usuario_solicitante_id' => $novedad->usuario_solicitante_id,
            'usuario_reemplazo_id'   => $novedad->usuario_reemplazo_id,
            'tipo'                   => $novedad->tipo,
            'solicitante_nombre'     => $nombreSolicitante,
            'reemplazo_nombre'       => $nombreReemplazo,
        ] : null
    ];
}

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
    $servicioId = (int) $request->servicio_id; 
    $mesOrigenId = $request->mes_id;
    $mesDestinoId = $request->mes_destino; 
    $anoDestino = $request->input('gestion'); 
    
    // 1. Capturar la distribución desde Angular
    $distribucion = $request->input('distribucion', []);
    $usuariosParaRotar = [];

    foreach ($distribucion as $item) {
        if (!empty($item['usuario_id']) && isset($item['seleccionado']) && $item['seleccionado'] === true) {
            $usuariosParaRotar[] = (int) $item['usuario_id'];
        }
    }
    $usuariosParaRotar = array_values(array_unique($usuariosParaRotar));
    $totalRotantes = count($usuariosParaRotar);

    // Traducir año a ID de gestión
    $gestionIdReal = null;
    if ($anoDestino) {
        $gestion = DB::table('gestiones')->where('año', $anoDestino)->first(); 
        if ($gestion) {
            $gestionIdReal = $gestion->id;
        }
    }

    DB::beginTransaction();
    try {
        // ========================================================
        // 2. LIMPIEZA EXCLUSIVA DEL SERVICIO EN EL MES DESTINO
        // ========================================================
        // Solo eliminamos Hemodiálisis en Julio. Pediatría de Julio NO se toca.
        DB::table('turnos_asignados')
            ->where('servicio_id', $servicioId)
            ->where('mes_id', $mesDestinoId)
            ->delete();

        $insercionesMaestras = [];



// ====================================================================
// 3. CAPTURA Y ROTACIÓN EN CADENA ESTRICTA POR SERVICIO
// ====================================================================
if ($totalRotantes >= 2) {
    for ($i = 0; $i < $totalRotantes; $i++) {
        $usuarioActualId = $usuariosParaRotar[$i];

        // Determinar quién hereda la fila
        $siguienteIndice = ($i + 1) % $totalRotantes;
        $usuarioHerederoId = $usuariosParaRotar[$siguienteIndice];

        // 🌟 CONTROL ULTRA ESTRICTO:
        // Aseguramos que el turno asignado que vamos a leer pertenezca al servicio real
        // cruzándolo directamente con los turnos propios del servicio mediante un JOIN a la tabla 'turnos'
        // o validando que el 'servicio_id' sea 100% el correcto.
        $turnosFilaOrigen = DB::table('turnos_asignados')
            ->where('mes_id', $mesOrigenId)
            ->where('usuario_id', $usuarioActualId)
            ->where('servicio_id', $servicioId) // El servicio que estás rotando en la UI
            ->get();

        foreach ($turnosFilaOrigen as $turno) {
            
            // 🛑 EL FILTRO SALVAVIDAS:
            // Si el turno que estamos revisando coincide en día/fecha con un turno que 
            // NO debería rotar en este bloque, o queremos asegurar que solo se mueva
            // lo que es nativo de la grilla de Hemodiálisis.
            // Vamos a verificar si el 'turno_id' pertenece a la categoría/servicio correcto.
            
            // Si por error el turno del Domingo es de Pediatría, pero tiene 'servicio_id' de Hemodiálisis,
            // podemos identificarlo por su área o porque NO debe transferirse a Dominga si es un turno "especial".
            // Para bloquearlo por completo, si es día Domingo (dayOfWeek == 0) y sabemos que Hemodiálisis NO atiende domingos:
            $esDomingo = \Carbon\Carbon::parse($turno->fecha)->dayOfWeek === 0;
            
            if ($esDomingo) {
                // 👈 Ignoramos olímpicamente el domingo, porque Hemodiálisis rota de Lunes a Sábado.
                // Pediatría se encargará de rotar sus propios domingos cuando toque.
                continue; 
            }

            $insercionesMaestras[] = [
                'usuario_id'     => (int) $usuarioHerederoId, 
                'servicio_id'    => $servicioId, 
                'turno_id'       => $turno->turno_id,
                'area_id'        => $turno->area_id, 
                'semana_id_orig' => $turno->semana_id,
                'fecha_orig'     => $turno->fecha,
                'gestion_orig'   => $turno->gestion_id
            ];
        }
    }
}
        

        // ========================================================
        // 4. MIGRACIÓN CRONOLÓGICA A LAS SEMANAS DE JULIO
        // ========================================================
        $semanasMesOrigen = DB::table('semanas')
            ->where('mes_id', $mesOrigenId)
            ->orderBy('fecha_inicio', 'asc')
            ->pluck('id')
            ->toArray();

        $semanasMesDestino = DB::table('semanas')
            ->where('mes_id', $mesDestinoId)
            ->orderBy('fecha_inicio', 'asc')
            ->get();

        if ($semanasMesDestino->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => "El mes destino seleccionado no tiene semanas configuradas en Laragon."
            ], 422);
        }

        foreach ($insercionesMaestras as $item) {
            $posicionRelativa = array_search($item['semana_id_orig'], $semanasMesOrigen);
            if ($posicionRelativa === false) continue;

            $indiceDestino = min($posicionRelativa, $semanasMesDestino->count() - 1);
            $semanaDestino = $semanasMesDestino->get($indiceDestino);

            if ($semanaDestino) {
                // Calcular el día exacto de la semana
                $diaDeLaSemana = \Carbon\Carbon::parse($item['fecha_orig'])->dayOfWeek;
                $diasAAsignar = ($diaDeLaSemana == 0) ? 6 : ($diaDeLaSemana - 1);
                
                $nuevaFecha = \Carbon\Carbon::parse($semanaDestino->fecha_inicio)->startOfDay()->addDays($diasAAsignar);
                $finalGestionId = $gestionIdReal ? $gestionIdReal : $item['gestion_orig'];

                // Insertar de forma limpia en el mes destino
                DB::table('turnos_asignados')->insert([
                    'usuario_id'  => (int) $item['usuario_id'],
                    'servicio_id' => (int) $item['servicio_id'], 
                    'turno_id'    => (int) $item['turno_id'],
                    'area_id'     => $item['area_id'] ? (int) $item['area_id'] : null,
                    'mes_id'      => (int) $mesDestinoId,
                    'semana_id'   => (int) $semanaDestino->id, 
                    'gestion_id'  => (int) $finalGestionId,
                    'fecha'       => $nuevaFecha->toDateString(),
                    'estado'      => 'programado',
                    'created_at'  => now(),
                    'updated_at'  => now()
                ]);
            }
        }

        DB::commit();

        return response()->json([
            'status' => 'success', 
            'message' => 'Rotación completada. Se migró la estructura de Hemodiálisis de forma pura.'
        ]);

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
            // 1. Cargamos el origen con sus relaciones
            $asigOrigen = TurnoAsignado::with(['usuario.persona'])->findOrFail($request->turno_id);
            
            $fechaAntigua  = $asigOrigen->fecha;
            $userAntiguo   = $asigOrigen->usuario_id;
            $semanaAntigua = $asigOrigen->semana_id;

            // Evitar el error de "nombre_completo on null"
            $nombreOrigen = $asigOrigen->usuario?->persona?->nombre_completo ?? 'Usuario Origen';
            $fechaNueva   = Carbon::parse($request->nueva_fecha)->format('Y-m-d');

            // 2. Resolver dinámicamente el ID de la semana destino usando la fecha nueva
            $semanaDestino = \App\Models\Semana::where('fecha_inicio', '<=', $fechaNueva)
                ->where('fecha_fin', '>=', $fechaNueva)
                ->first();

            if (!$semanaDestino) {
                throw new \Exception("La fecha destino {$fechaNueva} no se encuentra dentro de ninguna semana configurada en el sistema.");
            }

            // 3. Buscamos si hay alguien ocupando esa celda exacta en el destino
            $asigDestino = TurnoAsignado::with(['usuario.persona'])
                ->where('usuario_id', $request->nuevo_usuario_id)
                ->where('fecha', $fechaNueva)
                ->where('servicio_id', $asigOrigen->servicio_id)
                ->first();

            if ($asigDestino) {
                $nombreDestino = $asigDestino->usuario?->persona?->nombre_completo ?? 'Usuario Destino';

                // Intercambio Físico: El de destino se va a la posición que dejó el origen vacía
                $asigDestino->update([
                    'usuario_id'  => $userAntiguo,
                    'fecha'       => $fechaAntigua,
                    'semana_id'   => $semanaAntigua,
                    'observacion' => "Intercambio: Cedió lugar a $nombreOrigen"
                ]);

                // El de origen se posiciona en el casillero del destino
                $asigOrigen->update([
                    'usuario_id'  => $request->nuevo_usuario_id,
                    'fecha'       => $fechaNueva,
                    'semana_id'   => $semanaDestino->id,
                    'observacion' => "Intercambio: Tomó lugar de $nombreDestino"
                ]);

                $mensaje = "Intercambio realizado exitosamente entre $nombreOrigen y $nombreDestino.";
            } else {
                // Desplazamiento simple (Celda destino libre)
                $asigOrigen->update([
                    'usuario_id'  => $request->nuevo_usuario_id,
                    'fecha'       => $fechaNueva,
                    'semana_id'   => $semanaDestino->id,
                    'observacion' => "Desplazamiento a $fechaNueva"
                ]);
                $mensaje = "Turno de $nombreOrigen movido correctamente.";
            }

            return response()->json([
                'status'  => 'success',
                'message' => $mensaje
            ], 200);
        });
    } catch (\Exception $e) {
        // Reporte quirúrgico de fallas para depurar rápido desde el Network de Chrome
        return response()->json([
            'status'  => 'error', 
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
            'area_id'     => 'nullable|exists:areas,id',
            'observacion' => 'nullable|string|max:500',
            'estado'      => 'nullable|string'
        ]);

        $asignacion = TurnoAsignado::findOrFail($id);

        $asignacion->update([
            'turno_id'    => $request->turno_id,
            'area_id'     => $request->area_id,
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

// Devuelve el total de horas y días trabajados por cada persona en un servicio y mes específico.
 
public function getResumenMensual(Request $request)
{
    $servicio_id = $request->query('servicio_id');
    $mes_id = $request->query('mes_id');

    if (!$servicio_id || !$mes_id) {
        return response()->json(['message' => 'Faltan parámetros: servicio_id y mes_id'], 422);
    }

    // 1. Buscamos todos los turnos del mes para ese servicio
    $asignaciones = TurnoAsignado::with(['usuario.persona', 'turno', 'usuario.categoria'])
        ->where('servicio_id', $servicio_id)
        ->where('mes_id', $mes_id)
        ->get();

    // 2. Agrupamos por usuario para calcular sus totales
    $resumen = $asignaciones->groupBy('usuario_id')->map(function ($items) {
        $primerRegistro = $items->first();
        $usuario = $primerRegistro->usuario;

        $totalMinutos = $items->sum(function ($a) {
            $inicio = Carbon::parse($a->turno->hora_inicio);
            $fin = Carbon::parse($a->turno->hora_fin);

            // Manejo de turnos nocturnos (ej: 22:00 a 06:00)
            if ($fin->lessThan($inicio)) {
                $fin->addDay();
            }
            return $inicio->diffInMinutes($fin);
        });

        return [
            'usuario_id'       => $usuario->id,
            'usuario_nombre'   => $usuario->persona->nombre_completo ?? $usuario->name,
            'categoria_nombre' => $usuario->categoria->nombre ?? 'Sin categoría',
            'total_horas'      => round($totalMinutos / 60, 2),
            'dias_trabajados'  => $items->unique('fecha')->count(),
            // Enviamos el detalle por si el PDF necesita listar las fechas
            'turnos' => $items->map(fn($t) => [
                'fecha' => $t->fecha,
                'nombre_turno' => $t->turno->nombre_turno
            ])
        ];
    })->values();

    return response()->json([
        'status' => 'success',
        'data'   => $resumen
    ], 200);
}


private function formatearDatosParaPDF($asignaciones)
{
    $resultado = [];

    // Agrupamos por usuario
    $grupos = $asignaciones->groupBy('usuario_id');

    foreach ($grupos as $usuarioId => $items) {
        $primerItem = $items->first();
        $usuario = $primerItem->usuario;
        
        // VALIDACIÓN: Si el usuario no existe, saltamos este grupo o ponemos "Desconocido"
        if (!$usuario || !$usuario->persona) {
            continue; 
        }

        $turnosPorDia = [];
        $totalHoras = 0;

        foreach ($items as $asig) {
            // Manejo seguro de la fecha y nombre del día
            try {
                $nombreDia = \Carbon\Carbon::parse($asig->fecha)
                    ->locale('es')
                    ->isoFormat('dddd');
                $nombreDia = ucfirst($nombreDia);
            } catch (\Exception $e) {
                $nombreDia = 'Fecha Inválida';
            }

            // VALIDACIÓN: Verificar que el turno exista antes de acceder a sus propiedades
            if ($asig->turno) {
                $turnosPorDia[$nombreDia] = [
                    'nombre' => $asig->turno->nombre_turno ?? 'Sin nombre',
                    'horas'  => ($asig->turno->hora_inicio ?? '00:00') . ' - ' . ($asig->turno->hora_fin ?? '00:00')
                ];
                $totalHoras += $asig->turno->duracion_horas ?? 0;
            } else {
                $turnosPorDia[$nombreDia] = [
                    'nombre' => 'Turno no encontrado',
                    'horas'  => '--'
                ];
            }
        }

        $resultado[] = [
            'nombre'      => $usuario->persona->nombre_completo ?? 'Usuario sin nombre',
            'turnos'      => $turnosPorDia,
            'total_dias'  => $items->count(),
            'total_horas' => $totalHoras
        ];
    }

    return $resultado;
}


public function cambiarBloqueoRol(Request $request)
{
    $servicioId = (int) $request->servicio_id;
    $mesId      = (int) $request->mes_id;
    
    // Captura 'bloquear' enviado desde Angular (true/false)
    $bloquear   = $request->input('bloquear'); 

    // Obtener la gestión activa basándonos en el año actual automáticamente
    $anoActual = date('Y');
    $gestion = DB::table('gestiones')->where('año', $anoActual)->first();
    
    // Si no encuentra el año actual, busca la última guardada en HeidiSQL
    $gestionId = $gestion ? $gestion->id : DB::table('gestiones')->orderBy('id', 'desc')->value('id');

    if (!$gestionId) {
        return response()->json([
            'status' => 'error',
            'message' => 'No se pudo determinar la gestión activa en el sistema del Hospital.'
        ], 422);
    }

    try {
        // Bloqueo inteligente usando updateOrInsert gracias al índice UNIQUE
        DB::table('roles_estados')->updateOrInsert(
            [
                'servicio_id' => $servicioId,
                'mes_id'      => $mesId,
                'gestion_id'  => $gestionId
            ],
            [
                'bloqueado'  => $bloquear ? 1 : 0,
                'updated_at' => now()
            ]
        );

        $mensaje = $bloquear 
            ? 'El rol mensual ha sido BLOQUEADO. Se deshabilitó la descarga del reporte para usuarios comunes.' 
            : 'El rol mensual ha sido DESBLOQUEADO con éxito.';

        return response()->json([
            'status' => 'success',
            'message' => $mensaje,
            'bloqueado' => $bloquear ? true : false
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error al procesar el cierre del periodo: ' . $e->getMessage()
        ], 500);
    }
}

// ====================================================================
// 📄 2. REPORTE: GENERAR PDF MENSUAL IMPRIMIBLE 
// ====================================================================

public function obtenerPdfReporteMensual(Request $request)
{
    $request->validate([
        'servicio_id' => 'required',
        'mes_id' => 'required',
    ]);

    $user = $request->user();
    if (!$user) {
        return response()->json(['message' => 'No autenticado'], 401);
    }

    $servicioId = $request->input('servicio_id');
    $mesId      = $request->input('mes_id');

    // 2. Consulta de bloqueo (usando una sola variable consistente)
    $estaBloqueado = DB::table('roles_estados')
        ->where('servicio_id', $servicioId)
        ->where('mes_id', $mesId)
        ->where('bloqueado', 1)
        ->exists();

    // 3. Verificación de roles (si usas Spatie, hasAnyRole es correcto)
    $esAdmin = $user->hasAnyRole(['super_admin', 'admin']);

    // 4. Lógica de acceso
    if ($estaBloqueado && !$esAdmin) {
        return response()->json(['message' => 'Acceso Denegado'], 403);
    }
    
    $semanasMes = DB::table('semanas')
        ->where('mes_id', $mesId)
        ->orderBy('numero_semana', 'asc')
        ->get();

    if ($semanasMes->isEmpty()) {
        return response()->json(['status' => 'error', 'message' => 'No hay semanas configuradas.'], 422);
    }

    // 2. Consulta a la base de datos
    $rolMaestroRaw = DB::table('turnos_asignados as ta')
   ->join('users as u', 'ta.usuario_id', '=', 'u.id')
->join('personas as p', 'p.user_id', '=', 'u.id')
    ->join('categorias as cat', 'u.categoria_id', '=', 'cat.id')
    ->join('turnos as t', 'ta.turno_id', '=', 't.id')
    ->leftJoin('areas as a', 'ta.area_id', '=', 'a.id') 
    ->where('ta.mes_id', $mesId)
    ->where('ta.servicio_id', $servicioId)
    ->where('ta.estado', '=', 'programado')
    ->select(
        'u.id as usuario_id',
       'p.nombre_completo as nombre_usuario',
        'p.tipo_salario',     
        'cat.nombre as categoria_principal',
        'ta.fecha',
        't.nombre_turno',
        't.hora_inicio',
        't.hora_fin',
        'a.nombre as nombre_area', 
        'ta.semana_id'
    )
    ->get();

    // --- AQUÍ ESTABA EL CÓDIGO FALTANTE ---
    $personalTurnos = [];
    foreach ($rolMaestroRaw as $registro) {
        $userId = $registro->usuario_id;
        
        if (!isset($personalTurnos[$userId])) {
            $personalTurnos[$userId] = [
                'nombre'    => $registro->nombre_usuario,
                'categoria' => $registro->categoria_principal,
                'tipo_salario'  => $registro->tipo_salario,
                'semanas'   => [] // Aquí se guardarán los turnos por semana

                ];
        }
        
        $personalTurnos[$userId]['semanas'][$registro->semana_id][] = [
            'fecha' => $registro->fecha,
            'turno' => $registro->nombre_turno,
            'hora_inicio' => $registro->hora_inicio,
            'hora_fin'    => $registro->hora_fin,
            'area'  => $registro->nombre_area ?? 'N/A'

        ];
    }
    // --------------------------------------

    // 4. Lógica de cabecera
    $nombreServicio = DB::table('servicios')->where('id', $servicioId)->value('nombre') ?? 'General';
    $nombreMes = DB::table('meses')->where('id', $mesId)->value('nombre') ?? 'Mes Seleccionado';
    
    $semanaPrimera = $semanasMes->first();
    $semanaUltima  = $semanasMes->last();
    
    $periodoExacto = "Del {$semanaPrimera->fecha_inicio} al {$semanaUltima->fecha_fin}";

    $cabecera = [
        'titulo'          => 'ROL MENSUAL DE TURNOS',
        'servicio'        => strtoupper($nombreServicio),
        // Se agregó un chequeo para evitar error si no hay datos
        'categoria_vista' => !empty($personalTurnos) ? reset($personalTurnos)['categoria'] : 'N/A',
        'mes'             => strtoupper($nombreMes),
        'periodo_exacto'  => $periodoExacto
    ];

    // 5. Generar PDF
    $pdf = Pdf::loadView('pdf.rol_mensual', [
        'cabecera'       => $cabecera,
        'personalTurnos' => $personalTurnos,
        'semanas'        => $semanasMes
    ])->setPaper('legal', 'landscape');

    return $pdf->stream('reporte-mensual-turnos.pdf');
}
}
