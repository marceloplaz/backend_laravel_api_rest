<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mes extends Model
{
    protected $table = 'meses';
    protected $fillable = ['nombre', 'numero_mes', 'gestion_id'];

    public function gestion() {
        return $this->belongsTo(Gestion::class);
    }

    public function semanas() {
        return $this->hasMany(Semana::class);
    }
}