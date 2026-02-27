<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TurnoAsignadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha,
            'estado' => $this->estado,
            'observacion' => $this->observacion,
            'usuario' => [
                'id' => $this->usuario_id,
                'nombre' => $this->usuario->persona->nombre_completo ?? $this->usuario->name,
                'categoria' => $this->usuario->categoria->nombre ?? 'N/A',
            ],
            'servicio' => [
                'id' => $this->servicio_id,
                'nombre' => $this->servicio->nombre ?? 'N/A',
            ],
            'turno' => [
                'id' => $this->turno_id,
                'nombre' => $this->turno->nombre_turno,
                'horario' => $this->turno->hora_inicio . ' - ' . $this->turno->hora_fin,
            ],
            'calendario' => [
                'semana' => $this->semana_id,
                'mes' => $this->mes_id,
                'gestion' => $this->gestion_id,
            ]
        ];
    }
}