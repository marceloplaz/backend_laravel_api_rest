<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class KardexVacacion extends Model
{
    // Nombre de la tabla que creamos en la migración
    protected $table = 'kardex_vacaciones';

    // Campos que permitimos llenar masivamente
    protected $fillable = [


    'user_id',
        'gestion_id',
        'servicio_id',
        'gestiones_cumplidas',
        'cas_calificacion',
        'dias_derecho',
        'fecha_inicio',
        'fecha_fin',
        'fecha_retorno',
        'fecha_solicitud',
        'dias_solicitados',
        'saldo_restante',
        'descripcion',
        'estado'
    ];

    /**
     * Relación con el Usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Acceso directo a los datos de la Persona saltando por el Usuario
     */
    public function persona(): HasOneThrough
    {
        return $this->hasOneThrough(
            Persona::class, 
            User::class, 
            'id',      // Llave foránea en users (id del user)
            'user_id', // Llave foránea en personas (apunta al id de user)
            'user_id', // Llave local en kardex_vacaciones
            'id'       // Llave local en users
        );
    }

    // Relaciones adicionales para completar la tarjeta de control
    public function gestion()
    {
        return $this->belongsTo(Gestion::class);
    }

    public function servicio()
    {
        return $this->belongsTo(Servicio::class);
    }
}