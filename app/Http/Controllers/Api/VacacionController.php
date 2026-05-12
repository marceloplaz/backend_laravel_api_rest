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
/**
 * Lista todas las solicitudes pendientes para el administrador.
 */
public function indexPendientes()
{
    // Cargamos relaciones necesarias para la tabla de administración
    $pendientes = Vacacion::with([
            'user.persona', // Nombre del solicitante
            'servicio',     // Servicio al que pertenece
            'user.categoria',
            'gestion'       // Gestión (año) de la vacación
        ])
        ->where('estado', Vacacion::ESTADO_SIN_ASIGNAR)
        ->orderBy('created_at', 'asc') // Las más antiguas primero para atender por orden
        ->get();

    return response()->json($pendientes);
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
public function actualizarEstado(Request $request, $id)
{
    $request->validate([
        'estado' => 'required|in:1,2', // 1: Aprobar, 2: Rechazar
        'dias_solicitados' => 'required_if:estado,1|numeric',
        'observaciones' => 'nullable|string|max:500',
    ]);

    $vacacion = Vacacion::findOrFail($id);

    if ($vacacion->estado != Vacacion::ESTADO_SIN_ASIGNAR) {
        return response()->json(['message' => 'Esta solicitud ya fue procesada.'], 422);
    }

    $nuevoEstado = (int)$request->estado;

    if ($nuevoEstado === Vacacion::ESTADO_ASIGNADO) {
        // 1. Calcular Antigüedad y Días de Derecho (image_d2f034.png)
        $fechaIngreso = Carbon::parse($vacacion->fecha_ingreso_institucion);
        $antiguedad = $fechaIngreso->diffInYears(Carbon::now());

        if ($antiguedad >= 1 && $antiguedad < 5) $totalDerecho = 15;
        elseif ($antiguedad >= 5 && $antiguedad < 10) $totalDerecho = 20;
        elseif ($antiguedad >= 10) $totalDerecho = 30;
        else $totalDerecho = 0;

        // 2. Buscar saldo previo de la última vacación aprobada en la misma gestión
        $ultima = Vacacion::where('usuario_id', $vacacion->usuario_id)
            ->where('gestion_id', $vacacion->gestion_id)
            ->where('estado', Vacacion::ESTADO_ASIGNADO)
            ->orderBy('id', 'desc')
            ->first();

        $saldoBase = $ultima ? $ultima->saldo_restante : $totalDerecho;

        // 3. Validar y descontar
        $diasAConsumir = $request->dias_solicitados;
        if ($diasAConsumir > $saldoBase) {
            return response()->json(['message' => "Saldo insuficiente. Disponible: $saldoBase"], 400);
        }

        $vacacion->total_dias_derecho = $totalDerecho;
        $vacacion->dias_consumidos = $diasAConsumir;
        $vacacion->saldo_restante = $saldoBase - $diasAConsumir;
        $vacacion->permiso_cuenta = 1; // Se activa al aprobar
    }

    $vacacion->estado = $nuevoEstado;
    $vacacion->aprobado_por = auth()->id(); // El admin logueado
    $vacacion->observaciones = $request->observaciones;
    $vacacion->save();

    return response()->json([
        'message' => $nuevoEstado === 1 ? 'Aprobada y saldo actualizado' : 'Rechazada',
        'data' => $vacacion->load(['user.persona', 'servicio', 'gestion'])
    ]);
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
}