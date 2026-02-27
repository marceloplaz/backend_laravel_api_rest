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
            // 1. Crear primero el Usuario
            $user = User::updateOrCreate(
                ['email' => 'jugadordeunbit@gmail.com'],
                [
                    'name'         => 'marceloplaza',
                    'password'     => Hash::make('plaz5011070'),
                    'categoria_id' => 1, 
                ]
            );

            // 2. Crear la Persona vinculada al Usuario (según tu tabla)
            // Usamos DB::table para evitar errores si el modelo Persona no está actualizado
            DB::table('personas')->updateOrInsert(
                ['carnet_identidad' => '1234567'],
                [
                    'nombre_completo'    => 'EDSON MARCELO PLAZA BARRIOS',
                    'carnet_identidad'   => '5049801',
                    'fecha_nacimiento'   => '1982-05-28',
                    'genero'             => 'M',
                    'telefono'           => '68691672',
                    'direccion'          => 'B/Paraiso',
                    'tipo_trabajador'    => 'Administrativo',
                    'nacionalidad'       => 'Boliviana',
                    'tipo_salario'       => 'TGN',
                    'numero_tipo_salario'=> '9618',
                    'user_id'            => $user->id, // Vinculación correcta
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]
            );

            // 3. Asignar el Rol de Super Admin
            $user->roles()->sync([1]);
        });
    }
}