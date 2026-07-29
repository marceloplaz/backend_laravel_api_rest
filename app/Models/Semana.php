<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Semana extends Model
{
    protected $fillable = ['numero_semana', 'fecha_inicio', 'fecha_fin', 'mes_id', 'categoria_id'];

    public function mes() {
        return $this->belongsTo(Mes::class, 'mes_id');
    }

    public function categoria() {
        return $this->belongsTo(categoria::class, 'categoria_id');
    }
    
    public function turnosAsignados() {
        return $this->hasMany(TurnoAsignado::class);
    }
}
