<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Vacacion;
use App\Models\UsuarioServicio;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\User; 
use App\Models\Gestion; 
use App\Models\Categoria;

class VacacionController extends Controller
{  


/**
 * Obtiene el listado completo de vacaciones sin importar su estado.
 * Ideal para el historial general, búsquedas globales y reprocesamiento.
 */
public function indexGeneral()
{
    $vacaciones = Vacacion::query()
        ->with([
            'user' => function($query) {
                $query->select('id', 'name', 'categoria_id');
            },
            'user.persona:id,user_id,nombre_completo,fecha_ingreso_institucion', 
            'servicio:id,nombre',
            'user.categoria:id,nombre',
            'gestion:id,año'
        ])
        ->orderBy('created_at', 'desc') // Muestra los movimientos más recientes primero
        ->get();

    return response()->json([
        'success' => true,
        'data' => $vacaciones,
        'count' => $vacaciones->count()
    ]);
}


/**
 * Obtiene el historial de vacaciones y permisos de un usuario específico.
 */


public function indexByUsuario($id)
{
 
// Usamos Eager Loading para traer los nombres de la persona, servicio y gestión
    // Esto evita el problema de las N+1 consultas
    $vacaciones = Vacacion::with(['servicio', 'gestion', 'aprobador.persona'])
        ->where('usuario_id', $id)
        ->orderBy('fecha_inicio', 'desc')
        ->get();

    // Si quieres incluir los datos del solicitante una sola vez al principio:
    $usuario = User::with('persona')->find($id);

    return response()->json([
        'usuario' => [
            'nombre_completo' => $usuario->persona->nombres . ' ' . $usuario->persona->apellidos,
            'fecha_ingreso' => $usuario->servicios->first()->pivot->fecha_ingreso ?? null,
        ],
        'historial' => $vacaciones
    ]);
}


public function indexPendientes()
{
    $pendientes = Vacacion::query()
        ->with([
            'user' => function($query) {
                // DEBEMOS incluir 'id' para que persona pueda colgarse de aquí
                $query->select('id', 'name', 'categoria_id');
            },
            // DEBEMOS incluir 'user_id' para que Laravel sepa a qué usuario pertenece esta persona
            'user.persona:id,user_id,nombre_completo,fecha_ingreso_institucion', 
            'servicio:id,nombre',
            'user.categoria:id,nombre',
            'gestion:id,año'
        ])
        ->where('estado', Vacacion::ESTADO_SIN_ASIGNAR)
        ->orderBy('created_at', 'asc')
        ->get();

    return response()->json([
        'success' => true,
        'data' => $pendientes,
        'count' => $pendientes->count()
    ]);
}

/**
     * Registra una nueva solicitud de vacación o permiso.
     */
   
    public function store(Request $request)
{
    $request->validate([
        'usuario_id'    => 'required|exists:users,id',
        'servicio_id'   => 'required|exists:servicios,id',
        'gestion_id'    => 'required|exists:gestiones,id',
        'fecha_inicio'  => 'required|date',
        'fecha_fin'     => 'required|date|after_or_equal:fecha_inicio',
        'motivo_tipo'   => 'required|string',
        'periodo_desde' => 'required|date',
        'periodo_hasta' => 'required|date',
    ]);

    // 1. Obtener al usuario (IMPORTANTE: Esto faltaba)
    $user = User::findOrFail($request->usuario_id);

    // 2. Calcular días solicitados
    $inicio = Carbon::parse($request->fecha_inicio);
    $fin = Carbon::parse($request->fecha_fin);
    $diasPedidios = $inicio->diffInDays($fin) + 1;

    // 3. Obtener asignación del servicio
    $asignacion = UsuarioServicio::where('usuario_id', $request->usuario_id)
        ->where('servicio_id', $request->servicio_id)
        ->first();

    if (!$asignacion) {
        return response()->json(['message' => 'El usuario no está asignado a este servicio'], 422);
    }

$kardex = \DB::table('kardex_vacaciones')
    ->where('user_id', $request->usuario_id) // 'user_id' es el nombre real en la tabla
    ->where('saldo_dias', '>', 0)
    ->orderBy('gestiones_cumplidas', 'asc')
    ->first();
    // 4. Lógica de Saldo
    $ultimaVacacion = Vacacion::where('usuario_id', $request->usuario_id)
        ->where('gestion_id', $request->gestion_id)
        ->where('estado', Vacacion::ESTADO_ASIGNADO)
        ->orderBy('id', 'desc')
        ->first();

    // Si no hay previa, usamos el total_dias_derecho enviado o 15 por defecto
    $totalDerecho = $request->total_dias_derecho ?? 15; 
    $saldoAnterior = $ultimaVacacion ? $ultimaVacacion->saldo_restante : $totalDerecho;

    if ($diasPedidios > $saldoAnterior) {
        return response()->json(['message' => 'Saldo insuficiente. Días disponibles: ' . $saldoAnterior], 400);
    }

    // 5. Crear el registro (Ahora $user ya existe)
    $vacacion = Vacacion::create([
        'usuario_id'                => $request->usuario_id,
        'servicio_id'               => $request->servicio_id,
        'gestion_id'                => $request->gestion_id,
        'categoria_id'              => $user->categoria_id, // <--- Ahora funcionará
        'fecha_ingreso_institucion' => $asignacion->fecha_ingreso,
        'periodo_desde'             => $request->periodo_desde,
        'periodo_hasta'             => $request->periodo_hasta,
        'gestiones_cumplidas'       => $kardex ? $kardex->gestiones_cumplidas : 'Sin Gestión', 
        'total_dias_derecho'        => $totalDerecho,
        'dias_consumidos'           => $diasPedidios,
        'saldo_restante'            => $saldoAnterior - $diasPedidios,
        'fecha_inicio'              => $request->fecha_inicio,
        'fecha_fin'                 => $request->fecha_fin,
        'dias_solicitados'          => $diasPedidios,
        'permiso_cuenta'       => $request->permiso_cuenta ?? false,
        'motivo_tipo'               => $request->motivo_tipo,
        'motivo_detalle'            => $request->motivo_detalle,
        'estado'                    => Vacacion::ESTADO_SIN_ASIGNAR,
    ]);

    return response()->json([
        'message' => 'Solicitud registrada con éxito',
        'data' => $vacacion
    ], 201);
}
    /**
     * Aprobar una vacación y registrar quién lo hizo.
     */
    public function aprobar(Request $request, $id)
    {
        $vacacion = Vacacion::findOrFail($id);
        
        $vacacion->update([
            'estado'       => Vacacion::ESTADO_ASIGNADO,
            'aprobado_por' => auth()->id(), // El ID del usuario logueado
            'observaciones' => $request->observaciones
            
        ]);

        return response()->json(['message' => 'Vacación aprobada correctamente']);
    }
    /**
 
 */
/**
 * Actualiza el estado de una solicitud (Aprobar o Rechazar).
 * El saldo se calcula y descuenta ÚNICAMENTE si la solicitud es aprobada.
 /**
 * Actualiza el estado de una solicitud (Aprobar o Rechazar).
 * Ahora incluye la relación de categoría en la respuesta para Angular.
 */
/**
 * Actualiza el estado de una solicitud (0: Sin asignar, 1: Asignado, 2: Rechazado).
 * Habilita de manera dinámica la carga de motivos, observaciones y el aprobador.
 */
public function actualizarEstado(Request $request, $id)
{
    $request->validate([
        'estado'           => 'required|in:0,1,2', // 0: Sin asignar, 1: Asignado, 2: Rechazado
        'dias_solicitados' => 'required_if:estado,1|numeric|min:0',
        'motivo_tipo'      => 'required_if:estado,1|required_if:estado,2|in:VACACION_PROGRAMADA,SALUD,TRAMITE,FAMILIAR,PARTICULAR,OTRO',
        'observaciones'    => 'required_if:estado,1|required_if:estado,2|string|max:500',
    ]);

    $vacacion = Vacacion::findOrFail($id);
    $nuevoEstado = (int)$request->estado;

    // CASO 1: LA SOLICITUD SE APRUEBA (ESTADO 1: ASIGNADO)
    if ($nuevoEstado === 1) {
        // 1. Calcular Antigüedad y Días de Derecho en base a la fecha de ingreso
        $fechaIngreso = Carbon::parse($vacacion->fecha_ingreso_institucion);
        $antiguedad = $fechaIngreso->diffInYears(Carbon::now());

        if ($antiguedad >= 1 && $antiguedad < 5) $totalDerecho = 15;
        elseif ($antiguedad >= 5 && $antiguedad < 10) $totalDerecho = 20;
        elseif ($antiguedad >= 10) $totalDerecho = 30;
        else $totalDerecho = 0;

        // 2. Buscar saldo previo de la última vacación asignada de este usuario en la misma gestión
        $ultima = Vacacion::where('usuario_id', $vacacion->usuario_id)
            ->where('gestion_id', $vacacion->gestion_id)
            ->where('estado', 1)
            ->where('id', '!=', $vacacion->id)
            ->orderBy('id', 'desc')
            ->first();

        $saldoBase = $ultima ? $ultima->saldo_restante : $totalDerecho;
        $diasAConsumir = (int)$request->dias_solicitados;

        // Validar que el saldo no quede en negativo si no está permitido
        if ($diasAConsumir > $saldoBase) {
            return response()->json(['message' => "Saldo insuficiente en el sistema. Disponible: $saldoBase"], 400);
        }

        // Seteamos los valores calculados
        $vacacion->total_dias_derecho = $totalDerecho;
        $vacacion->dias_consumidos    = $diasAConsumir;
        $vacacion->saldo_restante     = $saldoBase - $diasAConsumir;
        $vacacion->dias_solicitados   = $diasAConsumir;
        $vacacion->motivo_tipo        = $request->motivo_tipo;
        $vacacion->observaciones      = $request->observaciones;
        $vacacion->aprobado_por       = auth()->id(); // Estampa el ID de tu Admin/SuperAdmin logueado
        $vacacion->permiso_cuenta     = 1;            // Se activa el control de Kardex

    // CASO 2: LA SOLICITUD SE RECHAZA (ESTADO 2: RECHAZADO)
    } elseif ($nuevoEstado === 2) {
        $vacacion->motivo_tipo   = $request->motivo_tipo;
        $vacacion->observaciones = $request->observaciones;
        $vacacion->aprobado_por   = auth()->id(); // Guarda quién ejecutó el rechazo
        $vacacion->permiso_cuenta = 0;            // No se activa en kardex

    // CASO 3: RETORNAR A "SIN ASIGNAR" (ESTADO 0)
    } else {
        $vacacion->motivo_tipo   = 'OTRO';
        $vacacion->observaciones = null;
        $vacacion->aprobado_por   = null; // Se limpia el aprobador al quedar pendiente
        $vacacion->permiso_cuenta = 0;
    }

    $vacacion->estado = $nuevoEstado;
    $vacacion->save(); // 'created_at' se mantiene intacto, 'updated_at' se actualiza automáticamente

    return response()->json([
        'success' => true,
        'message' => $this->obtenerMensajeEstado($nuevoEstado),
        'data'    => $vacacion->load(['user.persona', 'servicio', 'gestion'])
    ]);
}

/**
 * Función auxiliar para renderizar los mensajes de respuesta del servidor.
 */
private function obtenerMensajeEstado($estado) {
    switch ($estado) {
        case 1: return 'Solicitud asignada y aprobada con éxito.';
        case 2: return 'Solicitud marcada como Rechazada.';
        default: return 'Solicitud restablecida a Sin Asignar.';
    }
}


/**
 * Inicializa los registros de vacaciones para todo el personal real.
 * Ejecuta esto una vez (vía ruta) para llenar la base de datos con los saldos iniciales.
 */
 public function inicializarPersonalReal()
{
    $gestion = Gestion::where('año', 2026)->first(); 

    if (!$gestion) {
        return response()->json(['res' => false, 'mensaje' => 'Gestión 2026 no encontrada.'], 404);
    }

    $usuarios = User::with('persona')->get();
    $contador = 0;

    foreach ($usuarios as $user) {
    if (!$user->persona || !$user->persona->fecha_ingreso_institucion) {
        continue; 
    }

    Vacacion::updateOrCreate(
        ['usuario_id' => $user->id, 'gestion_id' => $gestion->id],
        [
            'servicio_id'               => $user->servicio_id ?? 1,
            'categoria_id'              => $user->categoria_id,
            'fecha_ingreso_institucion' => $user->persona->fecha_ingreso_institucion,
            
            // Periodos de la gestión
            'periodo_desde'             => '2025-01-01', 
            'periodo_hasta'             => '2025-12-31',

            // CAMPOS QUE FALTABAN (Usamos la fecha actual como marcador de posición)
            'fecha_inicio'              => now(), 
            'fecha_fin'                 => now(),
            'dias_solicitados'          => 0,

            'total_dias_derecho'        => 20,
            'dias_consumidos'           => 0,
            'saldo_restante'            => 20,
            'estado'                    => 1, 
            'motivo_tipo'               => 'OTRO', 
            'motivo_detalle'            => 'LLENADO DE DATOS INICIAL'
        ]
    );
    $contador++;
}

    return response()->json([
        'res' => true, 
        'mensaje' => "Sincronización completada. Se procesaron $contador profesionales con datos válidos."
    ]);
}
// para el recibir las fechas de inicio y fin mediante un  calendario 
public function programarFechas(Request $request, $id)
{
    $vacacion = Vacacion::findOrFail($id);

    // Validamos que vengan las fechas
    $request->validate([
        'fecha_inicio' => 'required|date',
        'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
    ]);

    // Calculamos los días solicitados automáticamente
    $inicio = \Carbon\Carbon::parse($request->fecha_inicio);
    $fin = \Carbon\Carbon::parse($request->fecha_fin);
    $dias = $inicio->diffInDays($fin) + 1; // +1 para incluir el día inicial

    $vacacion->update([
        'fecha_inicio'     => $request->fecha_inicio,
        'fecha_fin'        => $request->fecha_fin,
        'dias_solicitados' => $dias,
        'saldo_restante'   => $vacacion->total_dias_derecho - ($vacacion->dias_consumidos + $dias),
        'motivo_tipo'      => 'VACACION_PROGRAMADA'
    ]);

    return response()->json(['res' => true, 'mensaje' => 'Fechas programadas con éxito']);
}

public function persistirGestiones()
{
    // Buscamos solo las que tienen NULL en la base de datos
    $vacaciones = \App\Models\Vacacion::whereNull('gestiones_cumplidas')->get();
    $cont = 0;

    foreach ($vacaciones as $v) {
        $gestionKardex = \DB::table('kardex_vacaciones')
            ->where('user_id', $v->usuario_id) // 'user_id' según tu HeidiSQL
            ->orderBy('id', 'asc')
            ->value('gestiones_cumplidas');

        if ($gestionKardex) {
            // Esto actualiza la tabla 'vacaciones' físicamente
            $v->gestiones_cumplidas = $gestionKardex;
            $v->save();
            $cont++;
        }
    }

    return response()->json([
        'message' => 'Sincronización completada con éxito',
        'registros_actualizados' => $totalActualizados
    ], 200);
}

/**
 * 🚀 NUEVO MÉTODO OPTIMIZADO: Obtiene el saldo de vacaciones calculado 
 * de todo el personal en una única consulta HTTP (Elimina el bucle de Angular).
 */
public function obtenerSaldosMasivos()
{
    // 1. Usamos Eager Loading para traer los usuarios con su información de golpe
    $usuarios = User::with([
        'persona:id,user_id,nombre_completo,fecha_ingreso_institucion',
        'categoria:id,nombre'
    ])->get();

    // 2. Traemos de la base de datos la última vacación aprobada de cada usuario para la gestión actual (2026)
    // Agrupamos en memoria para cruzar datos al instante sin tocar MySQL en bucle
    $ultimosMovimientos = Vacacion::where('estado', 1) // 1: Asignado / Aprobado
        ->orderBy('id', 'desc')
        ->get()
        ->groupBy('usuario_id');

    $resultado = $usuarios->map(function($user) use ($ultimosMovimientos) {
        // Si el usuario no tiene una persona física asociada, lo saltamos de forma segura
        if (!$user->persona) {
            return null;
        }

        // Buscamos si tiene movimientos de vacaciones registrados
        $movimientosUsuario = $ultimosMovimientos->get($user->id);
        $ultimaVacacion = $movimientosUsuario ? $movimientosUsuario->first() : null;

        // Calculamos los días totales consumidos en el año por el trabajador
        $diasConsumidosTotal = $movimientosUsuario ? $movimientosUsuario->sum('dias_consumidos') : 0;

        return [
            'usuario_id'          => $user->id,
            'nombre_completo'     => strtoupper($user->persona->nombre_completo),
            'fecha_ingreso'       => $user->persona->fecha_ingreso_institucion ?? 'Sin Fecha',
            'categoria'           => $user->categoria->nombre ?? 'Sin Categoría',
            'gestiones_cumplidas' => $ultimaVacacion ? $ultimaVacacion->gestiones_cumplidas : '0',
            
            // Mapeo dinámico de saldos extraídos de tu última fila de control
            'dias_derecho'        => $ultimaVacacion ? $ultimaVacacion->total_dias_derecho : 15,
            'dias_consumidos'     => $diasConsumidosTotal,
            'saldo_restante'      => $ultimaVacacion ? $ultimaVacacion->saldo_restante : 15,
        ];
    })->filter()->values(); // Filtramos registros nulos y reindexamos el array

    return response()->json([
        'success' => true,
        'count'   => $resultado->count(),
        'data'    => $resultado
    ], 200);
}



}