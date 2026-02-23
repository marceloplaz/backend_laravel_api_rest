<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Cargamos la infraestructura (Calendario, Categorías y sus 24 turnos)
        $this->call([
            CalendarioSeeder::class,
            TurnoSeeder::class,
        ]);

        // 2. Creamos un usuario de ejemplo para cada categoría
        // ID 1 = Médicos, ID 2 = Enfermera, ID 3 = Manual
        
        User::factory()->create([
            'name' => 'Dr. Garcia (Médico)',
            'email' => 'medico@test.com',
            'categoria_id' => 1, 
        ]);

        User::factory()->create([
            'name' => 'Lic. Pérez (Enfermera)',
            'email' => 'enfermera@test.com',
            'categoria_id' => 2,
        ]);

        User::factory()->create([
            'name' => 'Sr. Lopez (Manual)',
            'email' => 'manual@test.com',
            'categoria_id' => 3,
        ]);
    }
}