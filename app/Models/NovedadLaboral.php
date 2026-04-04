<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NovedadLaboral extends Model
{
    use HasFactory;

    protected $table = 'novedades_laborales';

    protected $fillable = [
        'asignacion_id',
        'tipo_novedad',
        'usuario_solicitante_id',
        'usuario_reemplazo_id',
        'fecha_original',
        'fecha_nueva',
        'con_devolucion',
        'documento_respaldo',
        'con_devolucion',
        'observacion_detalle'
    ];

    // Casteamos los campos para manejarlos como objetos Carbon/Fecha
    protected $casts = [
        'fecha_original' => 'date',
        'fecha_nueva'    => 'date',
        'con_devolucion' => 'boolean',
    ];

    /**
     * Relación con el turno asignado original
     */
    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(TurnoAsignado::class, 'asignacion_id');
    }

    /**
     * El personal que solicita la novedad (Ej: Lic. Pérez)
     */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_solicitante_id');
    }

    /**
     * El personal que cubre el turno (Ej: Elena)
     */
    public function reemplazo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_reemplazo_id');
    }
}