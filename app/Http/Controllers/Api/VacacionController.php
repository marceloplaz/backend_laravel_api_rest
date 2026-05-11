<?php

namespace App\Http\Controllers;

use App\Models\Vacacion;
use App\Models\UsuarioServicio;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

        // 1. Calcular días solicitados usando Carbon
        $inicio = Carbon::parse($request->fecha_inicio);
        $fin = Carbon::parse($request->fecha_fin);
        $diasPedidios = $inicio->diffInDays($fin) + 1; // Incluimos el día inicial

        // 2. Obtener la fecha de ingreso de la tabla intermedia usuario_servicios
        $asignacion = UsuarioServicio::where('usuario_id', $request->usuario_id)
            ->where('servicio_id', $request->servicio_id)
            ->first();

        if (!$asignacion) {
            return response()->json(['message' => 'El usuario no está asignado a este servicio'], 422);
        }

        // 3. Lógica de Saldo: Buscar la última vacación aprobada para saber el saldo restante
        $ultimaVacacion = Vacacion::where('usuario_id', $request->usuario_id)
            ->where('gestion_id', $request->gestion_id)
            ->where('estado', Vacacion::ESTADO_ASIGNADO)
            ->orderBy('id', 'desc')
            ->first();

        // Si no hay vacaciones previas en esta gestión, el saldo inicial es el total_dias_derecho
        // (Este valor podrías enviarlo desde el frontend o tener una tabla de saldos por gestión)
        $totalDerecho = $request->total_dias_derecho ?? 15; 
        $saldoAnterior = $ultimaVacacion ? $ultimaVacacion->saldo_restante : $totalDerecho;

        if ($diasPedidios > $saldoAnterior) {
            return response()->json(['message' => 'Saldo insuficiente. Días disponibles: ' . $saldoAnterior], 400);
        }

        // 4. Crear el registro en estado "Sin asignar" (0)
        $vacacion = Vacacion::create([
            'usuario_id'                => $request->usuario_id,
            'servicio_id'               => $request->servicio_id,
            'gestion_id'                => $request->gestion_id,
            'fecha_ingreso_institucion' => $asignacion->fecha_ingreso,
            'periodo_desde'             => $request->periodo_desde,
            'periodo_hasta'             => $request->periodo_hasta,
            'total_dias_derecho'        => $totalDerecho,
            'dias_consumidos'           => $diasPedidios,
            'saldo_restante'            => $saldoAnterior - $diasPedidios,
            'fecha_inicio'              => $request->fecha_inicio,
            'fecha_fin'                 => $request->fecha_fin,
            'dias_solicitados'          => $diasPedidios,
            'es_permiso_a_cuenta'       => $request->es_permiso_a_cuenta ?? false,
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
 */
public function actualizarEstado(Request $request, $id)
{
    $request->validate([
        'estado' => 'required|in:0,1,2,3', //0:Sin asignar,  1: Asignado (Aprobado), 2: Rechazado
        'observaciones' => 'required_if:estado,2|nullable|string|max:500',
    ]);

    // Cargamos la vacación con su relación de gestión para tener el contexto
    $vacacion = Vacacion::findOrFail($id);

    // 1. Evitar doble procesamiento
    if ($vacacion->estado != Vacacion::ESTADO_SIN_ASIGNAR) {
        return response()->json(['message' => 'Esta solicitud ya ha sido procesada previamente.'], 422);
    }

    $nuevoEstado = (int)$request->estado;
    $aprobadorId = auth()->id();

    // 2. Lógica si el Administrador presiona "APROBAR"
    if ($nuevoEstado === Vacacion::ESTADO_ASIGNADO) {
        
        // Buscamos la última vacación APROBADA del usuario en esta misma gestión para saber su saldo real actual
        $ultimaAprobada = Vacacion::where('usuario_id', $vacacion->usuario_id)
            ->where('gestion_id', $vacacion->gestion_id)
            ->where('estado', Vacacion::ESTADO_ASIGNADO)
            ->orderBy('id', 'desc')
            ->first();

        // El saldo base es: lo que quedó de la última aprobada O el total_dias_derecho si es la primera
        $saldoBaseActual = $ultimaAprobada ? $ultimaAprobada->saldo_restante : $vacacion->total_dias_derecho;

        // Validación de último momento: ¿Aún tiene días suficientes?
        if ($vacacion->dias_consumidos > $saldoBaseActual) {
            return response()->json([
                'message' => "No se puede aprobar. El usuario solo tiene {$saldoBaseActual} días disponibles."
            ], 400);
        }

        // Calculamos el nuevo saldo
        $vacacion->saldo_restante = $saldoBaseActual - $vacacion->dias_consumidos;
        $mensaje = 'Solicitud aprobada y saldo actualizado correctamente.';
    } 
    else {
        // 3. Lógica si el Administrador presiona "RECHAZAR"
        // El saldo_restante se queda como está en el registro (el saldo base sin restar)
        $mensaje = 'Solicitud rechazada.';
    }

    // 4. Actualizamos campos comunes y guardamos
    $vacacion->estado = $nuevoEstado;
    $vacacion->aprobado_por = $aprobadorId;
    $vacacion->observaciones = $request->observaciones;
    $vacacion->save();

    return response()->json([
        'message' => $mensaje,
        'data' => $vacacion->load(['user.persona', 'aprobador.persona', 'servicio'])
    ]);
}
}