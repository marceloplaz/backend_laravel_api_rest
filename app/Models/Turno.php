<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Categoria;
use App\Models\Servicio;

class Turno extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre', 
        'hora_inicio',
        'hora_fin',
        'duracion_horas',
        'categoria_id'
    ];

    /**
     * Esto formatea automáticamente las horas para que Angular 
     * las reciba como "07:00" en lugar de "07:00:00"
     */
    protected $casts = [
        'hora_inicio' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i',
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