<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Categoria;
use App\Models\Servicio;

class Turno extends Model
{
    protected $fillable = [
        'nombre',
        'categoria_id'
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function servicios()
    {
        return $this->belongsToMany(
            Servicio::class,
            'servicio_turno',
            'turno_id',
            'servicio_id'
        );
    }
}

