<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "nombre_usuario" => $this->name,
            "email" => $this->email,
            
            // Usamos el null-safe operator y nos aseguramos de que los roles existan.
            // Si acabas de hacer el attach, es mejor usar roles() como método 
            // si la relación no está cargada, pero para Recursos, 
            // lo ideal es que en el Controller uses ->load('roles')
            "rol_nombre" => $this->roles->first()->name ?? 'Sin Rol Asignado',
            
            // Cargamos los datos personales
            "detalles_personales" => new PersonaResource($this->whenLoaded('persona') ?? $this->persona)
        ];
    }
}