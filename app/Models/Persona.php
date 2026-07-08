<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    use HasFactory;
    
    
    protected $fillable = [
        'nombre_completo',
        'carnet_identidad',
        'fecha_nacimiento',
        'genero',
        'telefono',
        'direccion',
        'tipo_trabajador',
        'nacionalidad',
        'tipo_salario',
        'fecha_ingreso_institucion',
        'numero_tipo_salario',
        'user_id'
    ];

    // Relación inversa: Una persona pertenece a un usuario
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function kardexVacaciones(): HasMany
{
    return $this->hasMany(KardexVacacion::class, 'user_id');
}
}
