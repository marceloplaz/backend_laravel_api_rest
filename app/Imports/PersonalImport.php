<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Persona;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;

class PersonalImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        // 1. FILTRO DE SEGURIDAD: Si la columna 'name' está vacía, ignora esta fila
        if (empty($row['name'])) {
            return null;
        }

        return DB::transaction(function () use ($row) {
            
            // 2. CREAR USUARIO (Forzando categoría 3: Manual)
            $user = User::create([
                'name'         => trim($row['name']), // trim() elimina espacios accidentales
                'email'        => trim($row['email']),
                'password'     => Hash::make($row['password'] ?? '123456'),
                'categoria_id' => 3, 
            ]);

            // 3. ASIGNAR ROL (Columna 'name_2' o 'rol')
            $rolNombre = $row['name_2'] ?? $row['rol'] ?? 'personal';
            $rol = Role::where('name', trim($rolNombre))->first();
            if ($rol) {
                $user->roles()->attach($rol->id);
            }

            // 4. CREAR PERSONA
            return new Persona([
                'nombre_completo'    => $row['nombre_completo'],
                'carnet_identidad'   => $row['carnet_identidad'],
                'fecha_nacimiento'   => $row['fecha_nacimiento'],
                'genero'             => $row['genero'],
                'telefono'           => $row['telefono'],
                'direccion'          => $row['direccion'] ?? 'S/D',
                'tipo_trabajador'    => $row['tipo_trabajador'],
                'nacionalidad'       => 'Boliviana',
                'tipo_salario'       => $row['tipo_salario'],
                'numero_tipo_salario'=> $row['numero_tipo_salario'],
                'user_id'            => $user->id,
            ]);
        });
    }
}