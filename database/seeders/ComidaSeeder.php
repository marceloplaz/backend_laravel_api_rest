<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Comida;

class ComidaSeeder extends Seeder
{
   
    public function run(): void
    {
        $comidas = [
            [
                'nombre' => 'Desayuno',
                'codigo' => 'desayuno',
                'estado' => true,
            ],
            [
                'nombre' => 'Almuerzo',
                'codigo' => 'almuerzo',
                'estado' => true,
            ],
            [
                'nombre' => 'Té',
                'codigo' => 'te',
                'estado' => true,
            ],
            [
                'nombre' => 'Cena',
                'codigo' => 'cena',
                'estado' => true,
            ],
        ];

        foreach ($comidas as $comida) {
            Comida::updateOrCreate(
                ['codigo' => $comida['codigo']],
                $comida
            );
        }
    }
}