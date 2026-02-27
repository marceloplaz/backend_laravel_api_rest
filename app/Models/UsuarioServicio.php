<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioServicio extends Model
{
    // 1. El nombre en la migración es 'usuario_servicios' (minúsculas y plural)
    protected $table = 'usuario_servicios';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    // 2. Ajustamos los campos según tu migración real
    protected $fillable = [
        'usuario_id',
        'servicio_id',
        'descripcion_usuario_servicio',
        'estado',
        'fecha_ingreso'
    ];

    // 3. Relación con el modelo User (importante usar el nombre correcto de la clase)
    public function usuario()
    {
        // Tu modelo se llama 'User', no 'Usuario'
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }
}