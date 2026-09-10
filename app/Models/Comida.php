<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comida extends Model
{
    use HasFactory;

    protected $table = 'comidas';

    protected $fillable = [
        'nombre',
        'codigo',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    
   public function turnos()
{
    return $this->belongsToMany(Turno::class, 'turno_comida', 'comida_id', 'turno_id')
                ->withPivot('dia_relativo') 
                ->withTimestamps();
}
}