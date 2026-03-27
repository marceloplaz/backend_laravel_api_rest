<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Servicio;

class IncidenciaTecnica extends Model
{
    protected $table = 'incidencias_tecnicas';

    protected $fillable = [
        'usuario_id', 
        'servicio_id', 
        'fecha', 
        'descripcion', 
        'prioridad', 
        'estado', 
        'observacion_tecnica',
        'fecha_solucion'
    ];

    // Ahora el color puede depender de la PRIORIDAD para el frontend
    public function getStatusColorAttribute() {
        return [
            'alta'  => '#fee2e2', // Rojo claro
            'media' => '#fef3c7', // Amarillo claro
            'baja'  => '#d1fae5', // Verde claro
        ][$this->prioridad] ?? '#ffffff';
    }

    // Relación: Quién reportó
    public function usuario() {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // Relación: En qué servicio ocurrió
    public function servicio() {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }
}