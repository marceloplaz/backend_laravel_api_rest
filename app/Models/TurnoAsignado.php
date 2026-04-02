<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TurnoAsignado extends Model
{
    use HasFactory;

    protected $table = 'turnos_asignados'; 

    protected $fillable = [
        'usuario_id',
        'servicio_id',
        'turno_id',
        'semana_id',
        'mes_id',
        'gestion_id',
        'fecha',
        'estado',
        'observacion',
        'es_intercambio'
    ];
 protected $casts = [
        'es_intercambio' => 'boolean',
       
    ];
    // Relaciones para el Controlador
    public function usuario() 
    { 
        return $this->belongsTo(User::class, 'usuario_id'); 
    }

    public function servicio() 
    { 
        return $this->belongsTo(Servicio::class, 'servicio_id'); 
    }

    public function turno() 
    { 
        return $this->belongsTo(Turno::class, 'turno_id'); 
    }

    public function semana() 
    { 
        return $this->belongsTo(Semana::class, 'semana_id'); 
    }

    // Estas son vitales para obtener mes_id y gestion_id automáticamente
    public function mes() 
    { 
        return $this->belongsTo(Mes::class, 'mes_id'); 
    }

    public function gestion() 
    { 
        return $this->belongsTo(Gestion::class, 'gestion_id'); 
    }
    // Dentro de la clase TurnoAsignado
    public function novedad()
    {
    return $this->hasOne(NovedadLaboral::class, 'asignacion_id');
    }
}