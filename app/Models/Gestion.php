<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gestion extends Model

{
    protected $table = 'gestiones';
    protected $fillable = ['año', 'activo'];

    public function meses() {
        return $this->hasMany(Mes::class);
    }
public function vacaciones() {
    return $this->hasMany(Vacacion::class, 'gestion_id');
}
public function kardexVacaciones(): HasMany
{
    return $this->hasMany(KardexVacacion::class, 'user_id');
}
}
