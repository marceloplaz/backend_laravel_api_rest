<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Semana extends Model
{
    protected $fillable = ['numero_semana', 'fecha_inicio', 'fecha_fin', 'mes_id'];

    public function mes() {
        return $this->belongsTo(Mes::class);
    }

    public function turnosAsignados() {
        return $this->hasMany(TurnoAsignado::class);
    }
}
