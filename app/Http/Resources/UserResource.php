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
        // Cargamos los datos personales si existen
        "detalles_personales" => new PersonaResource($this->persona)
    ];
}
}