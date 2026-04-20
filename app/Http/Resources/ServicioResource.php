<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicioResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
{
    return [
        'id'     => $this->id,
        'nombre' => $this->nombre,
        'descripcion'        => $this->descripcion, 
        'cantidad_pacientes' => $this->cantidad_pacientes, 
        'usuarios' => $this->usuarios->map(function($user) {
            return [
                'id'             => $user->pivot->id, // ID de la tabla usuario_servicios
                'usuario_id'     => $user->id,        // ID de la tabla users
                'usuario_nombre' => $user->name,      // El nombre que buscamos
                'descripcion'    => $user->pivot->descripcion_usuario_servicio,
                'fecha_ingreso'  => $user->pivot->fecha_ingreso,
                'estado'         => $user->pivot->estado,
            ];
        }),
    ];
}
}