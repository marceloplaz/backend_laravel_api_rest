<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioServicio extends Model
{
    // Nombre de la tabla
    protected $table = 'UsuarioServicio';

    // Llave primaria (cambiar si no es 'id')
    protected $primaryKey = 'id';
    public $incrementing = true; // poner false si no es autoincremental
    protected $keyType = 'int';  // cambiar a 'string' si la PK es string

    // Timestamps
    public $timestamps = true; // poner false si no hay created_at y updated_at

    // Campos asignables masivamente
    protected $fillable = [
        'usuario_id',
        'servicio_id',
        'fecha_inicio',
        'fecha_fin',
        'estado'
    ];

    // Relación con el modelo Usuario
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    // Relación con el modelo Servicio
    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }
}
