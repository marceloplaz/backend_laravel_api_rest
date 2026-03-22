<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
{
    return [
        "nombre" => $this->nombre_completo,
        'usuario_id' => $this->user_id,
        "ci" => $this->carnet_identidad,
        "cargo" => $this->tipo_trabajador,
        "salario_tipo" => $this->tipo_salario,
        "contacto" => $this->telefono
    ];
}
}
