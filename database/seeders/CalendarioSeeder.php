<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gestion;
use App\Models\Mes;
use App\Models\Semana;
use Carbon\Carbon;

class CalendarioSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tabla 'gestiones': columnas 'año' y 'activo'
        $gestion = Gestion::create([
            'año'    => '2026', 
            'activo' => true
        ]);

        $nombresMeses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        foreach ($nombresMeses as $numero => $nombre) {
            // 2. Tabla 'meses': columnas 'nombre' y 'numero_mes'
            $mes = Mes::create([
                'nombre'     => $nombre,
                'numero_mes' => $numero,
                'gestion_id' => $gestion->id
            ]);

            // 3. Generar Semanas para la tabla 'semanas'
            $inicioMes = Carbon::create(2026, $numero, 1)->startOfMonth();
            $finMes = Carbon::create(2026, $numero, 1)->endOfMonth();
            $fechaCaminante = $inicioMes->copy();
            $contadorSemana = 1;

            while ($fechaCaminante <= $finMes) {
                $inicioSemana = $fechaCaminante->copy();
                $finSemana = $fechaCaminante->copy()->endOfWeek(Carbon::SUNDAY);
                
                if ($finSemana > $finMes) {
                    $finSemana = $finMes->copy();
                }

                // Tabla 'semanas': columnas 'numero_semana', 'fecha_inicio', 'fecha_fin'
                Semana::create([
                    'numero_semana' => $contadorSemana,
                    'fecha_inicio'  => $inicioSemana->format('Y-m-d'),
                    'fecha_fin'     => $finSemana->format('Y-m-d'),
                    'mes_id'        => $mes->id
                ]);

                $fechaCaminante = $finSemana->addDay();
                $contadorSemana++;
            }
        }
    }
}