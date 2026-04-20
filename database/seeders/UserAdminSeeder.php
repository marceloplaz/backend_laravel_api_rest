<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserAdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Crear o actualizar el Usuario
            // Buscamos por email para evitar duplicados de cuenta
            $user = User::updateOrCreate(
                ['email' => 'jugadordeunbit@gmail.com'],
                [
                    'name'         => 'marceloplaza',
                    'password'     => Hash::make('plaz5011070'),
                    'categoria_id' => 1, 
                ]
            );

            // 2. Crear o actualizar la Persona vinculada al Usuario
            // IMPORTANTE: Buscamos por el carnet real para que no intente duplicarlo
            DB::table('personas')->updateOrInsert(
                ['carnet_identidad' => '5049801'], // Clave de búsqueda correcta
                [
                    'nombre_completo'    => 'EDSON MARCELO PLAZA BARRIOS',
                    'fecha_nacimiento'   => '1982-05-28',
                    'genero'             => 'M',
                    'telefono'           => '68691672',
                    'direccion'          => 'B/Paraiso',
                    'tipo_trabajador'    => 'Administrativo',
                    'nacionalidad'       => 'Boliviana',
                    'tipo_salario'       => 'TGN',
                    'numero_tipo_salario'=> '9618',
                    'user_id'            => $user->id,
                    'updated_at'         => now(),
                ]
            );

            // 3. Asignar el Rol (Asumiendo que el ID 1 es el Admin)
            $user->roles()->sync([1]);
        });
    }
}