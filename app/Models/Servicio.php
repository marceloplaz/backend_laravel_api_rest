<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Turno;
use App\Models\User;

class Servicio extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre'
    ];

    // USAR SOLO ESTA VERSIÓN DE USUARIOS
    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'usuario_servicios', 'servicio_id', 'usuario_id')
                    ->withPivot('id', 'descripcion_usuario_servicio', 'fecha_ingreso', 'estado');
    }

    public function turnos()
    {
        return $this->belongsToMany(
            Turno::class,
            'servicio_turno',
            'servicio_id',
            'turno_id'
        );
    }
}