<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TurnoAsignado extends Model
{
    // Laravel por defecto busca "turno_asignados", 
    // pero tu migración dice "turno_asignados" (fíjate en el plural/singular)
    protected $table = 'turnos_asignados'; 

    protected $fillable = [
    'usuario_id', // O user_id según tu migración
    'servicio_id',
    'turno_id',
    'semana_id',
    'mes_id',
    'gestion_id',
    'fecha',
    'estado',
    'observacion'
];

// No olvides las relaciones para que el 'with' del controlador funcione
public function usuario() { return $this->belongsTo(User::class, 'usuario_id'); }
public function servicio() { return $this->belongsTo(Servicio::class); }
public function turno() { return $this->belongsTo(Turno::class); }
public function semana() { return $this->belongsTo(Semana::class); }
}
