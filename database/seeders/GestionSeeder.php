<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gestion;

class GestionSeeder extends Seeder
{
    public function run(): void
    {
        $gestiones = [
            2023, 2024, 2025, 2026, 2027, 2028, 2029, 
            2030, 2031, 2032, 2033, 2034, 2035, 2036
        ];

        foreach ($gestiones as $año) {
            Gestion::updateOrCreate(
                ['año' => $año], // Busca por la columna año
                [
                    'activo' => ($año == 2026) ? 1 : 0 // Mantiene 2026 como gestión actual
                ]
            );
        }
    }
}