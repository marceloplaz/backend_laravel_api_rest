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
    protected $appends = ['gestiones_cumplidas']; // Fuerza a que siempre aparezca en el JSON

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
        'gestiones_cumplidas',
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
        'aprobado_por',             // FK a users 
        'observaciones',
    ];

    protected $casts = [
        'fecha_ingreso_institucion' => 'date',
        'periodo_desde'             => 'date',
        'periodo_hasta'             => 'date',
        'gestiones_cumplidas'       => 'string',         
        'fecha_inicio'              => 'date',
        'fecha_fin'                 => 'date',
        'es_permiso_a_cuenta'       => 'boolean',
        'estado'                    => 'integer',
    ];

    

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
 // En tu archivo app/Models/Vacacion.php

public function aprobador()
{
 
    return $this->belongsTo(User::class, 'aprobado_por');
}

public function getGestionesCumplidasAttribute($value)
{
    // Si la vacación ya tiene guardada la gestión, la usamos directamente
    if (!empty($value)) {
        return $value;
    }

    // El error indica que 'saldo_dias' NO existe. 
    // Cámbialo por el nombre correcto (comúnmente 'saldo' en tu sistema)
   return \DB::table('kardex_vacaciones')
        ->where('user_id', $this->usuario_id) // 'user_id' es el nombre en tu DB
        ->orderBy('id', 'desc')               // Traemos el último registro
        ->value('gestiones_cumplidas') ?? 'Sin Gestión';
}
}