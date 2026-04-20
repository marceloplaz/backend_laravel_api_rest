<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Categoria;

class CategoriaNuevasSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            // --- TUS CATEGORÍAS ANTERIORES (Para que se mantengan) ---
            [
                'nombre' => 'Médicos',
                'nivel' => 1,
                'descripcion' => 'Personal médico especializado'
            ],
            [
                'nombre' => 'Enfermera',
                'nivel' => 2,
                'descripcion' => 'Personal de enfermería y auxiliares'
            ],
            [
                'nombre' => 'Manual',
                'nivel' => 3,
                'descripcion' => 'Personal de servicios operativos'
            ],

            // --- LAS NUEVAS CATEGORÍAS QUE NECESITAS ---
            [
                'nombre' => 'Tecnicos',
                'nivel' => 4,
                'descripcion' => 'Personal técnico de laboratorio y rayos x'
            ],
            [
                'nombre' => 'Mantenimiento',
                'nivel' => 5,
                'descripcion' => 'Personal de infraestructura y reparaciones'
            ],
            [
                'nombre' => 'Administrativo',
                'nivel' => 6,
                'descripcion' => 'Personal de oficina y gestión'
            ],
        ];

        foreach ($categorias as $cat) {
            // Buscamos por nombre. Si ya existe, NO crea uno nuevo, solo lo sincroniza.
            Categoria::updateOrCreate(
                ['nombre' => $cat['nombre']], 
                [
                    'nivel' => $cat['nivel'],
                    'descripcion' => $cat['descripcion']
                ]
            );
        }
    }
}