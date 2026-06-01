<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
protected $table = 'configuraciones';
protected $fillable = ['clave', 'valor'];

public static function estaBloqueado($clave) {
    return self::where('clave', $clave)->value('valor') === 'true';
}
    }
