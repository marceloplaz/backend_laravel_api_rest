<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServicioUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
       
        return true; //para la peticion de put
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'cantidad_pacientes' => 'required|integer|min:0',
        ];
    }
}