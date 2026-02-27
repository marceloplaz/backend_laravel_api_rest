<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioServicioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuario_id,
            // Si la relación existe, trae el nombre, si no, pone "No asignado"
            'usuario_nombre' => $this->usuario ? $this->usuario->name : 'Usuario no encontrado',
            
            'servicio_id' => $this->servicio_id,
            'servicio_nombre' => $this->servicio ? $this->servicio->nombre : 'Servicio no encontrado',
            
            'fecha_ingreso' => $this->fecha_ingreso,
            'descripcion' => $this->descripcion_usuario_servicio,
            'estado' => (bool)$this->estado,
            'fecha_registro' => $this->created_at ? $this->created_at->format('d-m-Y H:i:s') : null,
        ];
    }
}