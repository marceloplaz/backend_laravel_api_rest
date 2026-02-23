<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Turno;

class Servicio extends Model
{
    protected $fillable = [
        'nombre'
    ];

    public function turnos()
    {
        return $this->belongsToMany(
            Turno::class,
            'servicio_turno',      // tabla pivote
            'servicio_id',         // FK en pivote hacia servicio
            'turno_id'             // FK en pivote hacia turno
        );
    }
}