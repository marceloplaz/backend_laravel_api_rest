<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vacacion extends Model
{
    use HasFactory;

    // Nombre de la tabla en plural como definimos en la migración
    protected $table = 'vacaciones';

    // Constantes para los estados (0: Sin asignar, 1: Asignado, 2: Rechazado)
    const ESTADO_SIN_ASIGNAR = 0;
    const ESTADO_ASIGNADO = 1;
    const ESTADO_RECHAZADO = 2;

    protected $fillable = [
        'usuario_id',               // FK a users
        'categoria_id',
        'servicio_id',              // FK a servicios
        'categoria_id',
        'gestion_id',               // FK a gestiones
        'fecha_ingreso_institucion',
        'periodo_desde',
        'periodo_hasta',
        'total_dias_derecho',
        'dias_consumidos',
        'saldo_restante',
        'fecha_inicio',
        'fecha_fin',
        'dias_solicitados',
        'es_permiso_a_cuenta',
        'motivo_tipo',
        'motivo_detalle',
        'estado',
        'aprobado_por',             // FK a users (quien firma)
        'observaciones',
    ];

    protected $casts = [
        'fecha_ingreso_institucion' => 'date',
        'periodo_desde'             => 'date',
        'periodo_hasta'             => 'date',
        'fecha_inicio'              => 'date',
        'fecha_fin'                 => 'date',
        'es_permiso_a_cuenta'       => 'boolean',
        'estado'                    => 'integer',
    ];

    // --- RELACIONES ESTRUCTURALES ---

    /**
     * El profesional (User) que solicita la vacación.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function categoria()
{
    return $this->belongsTo(Categoria::class, 'categoria_id');
}
    /**
     * El servicio relacionado.
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }


    public function persona(): HasOneThrough
    {
        return $this->hasOneThrough(
            Persona::class, 
            User::class, 
            'id',      // FK en users
            'user_id', // FK en personas
            'usuario_id', // Local key en vacaciones
            'id'       // Local key en users
        );
    }
    /**
     * La gestión (año) a la que corresponde.
     */
    public function gestion(): BelongsTo
    {
        return $this->belongsTo(Gestion::class, 'gestion_id');
    }

    /**
     * El usuario que aprueba el registro (usualmente un Jefe o Admin).
     */
    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }
}