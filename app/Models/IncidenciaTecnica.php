<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidenciaTecnica extends Model
{
    const CAT_LUZ = 'LUZ';
    const CAT_AGUA = 'AGUA';
    const CAT_EQUIPO_MEDICO = 'EQUIPO MEDICO';
    const CAT_COMPUTACION = 'COMPUTACION';

    // Para usarlas fácilmente en un select del frontend
    public static function getCategorias()
    {
        return [
            self::CAT_LUZ,
            self::CAT_AGUA,
            self::CAT_EQUIPO_MEDICO,
            self::CAT_COMPUTACION,
        ];
    }

    protected $table = 'incidencias_tecnicas';
    protected $fillable = [
        'usuario_id', 'servicio_id', 'categoria', 'fecha', 
        'descripcion', 'prioridad', 'estado', 'observacion_tecnica'
    ];
    public function getColorFondoAttribute() {
    return [
        'luz'            => '#fef3c7', // Amarillo (Alerta eléctrica)
        'agua'           => '#e0f2fe', // Azul (Fugas/Suministro)
        'equipo medico'  => '#fee2e2', // Rojo (Crítico)
        'computacion'    => '#f3f4f6', // Gris (Soporte)
    ][$this->categoria] ?? '#ffffff';
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