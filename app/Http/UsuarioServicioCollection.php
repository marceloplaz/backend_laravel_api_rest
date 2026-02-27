<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UsuarioServicioCollection extends ResourceCollection
{
    /**
     * Transforma la colección de recursos en un array.
     */
    public function toArray(Request $request): array
    {
        return [
            // Cada elemento de la lista usará el UsuarioServicioResource que ya creamos
            'data' => $this->collection,
            
            'meta' => [
                'total_registros' => $this->collection->count(),
                'organizacion'    => 'Sistema Hospitalario',
                'fecha_consulta'  => now()->format('d-m-Y H:i:s'),
            ],
            'status' => 'success'
        ];
    }
}