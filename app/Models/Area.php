<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory;

    protected $fillable = [
        'servicio_id',
        'nombre',
        'numero_consultorio',
        'categoria_id',
        'activo'
    ];

    public function servicio()
    {
        return $this->belongsTo(Servicio::class);
    }

    public function categoria() {
    
    return $this->belongsTo(\App\Models\Categoria::class, 'categoria_id');
    
    }
}