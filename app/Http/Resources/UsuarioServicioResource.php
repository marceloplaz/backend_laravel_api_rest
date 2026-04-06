<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioServicioResource extends JsonResource
{
   // app/Http/Resources/UsuarioServicioResource.php

public function toArray($request)
{
    return [
        'id'             => $this->id,
        'usuario_id'     => $this->usuario_id,
        // Usamos el 'name' de la tabla users que vimos en tu DB
        'usuario_nombre' => $this->usuario->name ?? 'Sin nombre', 
        'descripcion'    => $this->descripcion_usuario_servicio,
        'fecha_ingreso'  => $this->fecha_ingreso,
        'estado'         => $this->estado,
    ];
}
}