<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
       
        $serviciosIds = $this->roles->flatMap(function ($role) {
            return $role->servicios ? $role->servicios->pluck('id') : [];
        })->unique()->values()->toArray();

        return [
            "id"                  => $this->id,
            "nombre_usuario"      => $this->name,
            "rol_nombre"          => $this->roles->first()->name ?? 'Sin Rol Asignado', 
                
            "servicios"           => $serviciosIds, 
            
            "detalles_personales" => new PersonaResource($this->whenLoaded('persona') ?? $this->persona)
        ];
    }
}