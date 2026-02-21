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

}
