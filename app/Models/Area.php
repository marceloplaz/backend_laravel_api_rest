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
        'activo'
    ];

    public function servicio()
    {
        return $this->belongsTo(Servicio::class);
    }
}