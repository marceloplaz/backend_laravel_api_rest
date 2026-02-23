<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Categoria;
use App\Models\Turno;

class TurnoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Definimos las 3 categorías principales
        $categorias = [
            ['nombre' => 'Médicos',   'nivel' => 1],
            ['nombre' => 'Enfermera', 'nivel' => 2],
            ['nombre' => 'Manual',    'nivel' => 3],
        ];

        // 2. Definimos los 8 turnos detallados de tu imagen
        $turnosBase = [
            ['nombre_turno' => 'noche',           'inicio' => '19:00:00', 'fin' => '07:00:00', 'hrs' => 12],
            ['nombre_turno' => 'Mañana',          'inicio' => '07:00:00', 'fin' => '13:00:00', 'hrs' => 6],
            ['nombre_turno' => 'Tarde',           'inicio' => '13:00:00', 'fin' => '19:00:00', 'hrs' => 6],
            ['nombre_turno' => 'Noche/12hrs',     'inicio' => '19:00:00', 'fin' => '07:00:00', 'hrs' => 12],
            ['nombre_turno' => 'Noche',           'inicio' => '19:00:00', 'fin' => '01:00:00', 'hrs' => 6],
            ['nombre_turno' => 'Mañana/Tarde',    'inicio' => '07:00:00', 'fin' => '19:00:00', 'hrs' => 12],
            ['nombre_turno' => 'Tarde/Noche',     'inicio' => '13:00:00', 'fin' => '01:00:00', 'hrs' => 12],
            ['nombre_turno' => 'Mañana Temprano', 'inicio' => '04:00:00', 'fin' => '10:00:00', 'hrs' => 6],
        ];

        foreach ($categorias as $catData) {
            // Creamos o recuperamos la categoría
            $categoria = Categoria::updateOrCreate(
                ['nombre' => $catData['nombre']],
                ['nivel' => $catData['nivel']]
            );

            // Creamos los 8 turnos vinculados a esta categoría
            foreach ($turnosBase as $t) {
                Turno::create([
                    'nombre_turno'   => $t['nombre_turno'],
                    'hora_inicio'    => $t['inicio'],
                    'hora_fin'       => $t['fin'],
                    'duracion_horas' => $t['hrs'],
                    'categoria_id'   => $categoria->id,
                ]);
            }
        }
    }
}