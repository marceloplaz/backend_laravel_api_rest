<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gestion;
use App\Models\Mes;
use App\Models\Semana;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CalendarioSeeder extends Seeder
{
    /**
     * Genera el calendario 2026 con cortes de mes exactos.
     * Ejecutar con: php artisan db:seed --class=CalendarioSeeder
     */
    public function run($anio = 2026): void
    {
        // 1. Limpieza de seguridad para evitar duplicados
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        $gestion = Gestion::updateOrCreate(
            ['año' => $anio],
            ['activo' => true]
        );

        $mesesIds = Mes::where('gestion_id', $gestion->id)->pluck('id');
        Semana::whereIn('mes_id', $mesesIds)->delete();
        Mes::where('gestion_id', $gestion->id)->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $nombresMeses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        // 2. Definición de cortes exactos según tus requerimientos
        // Aquí forzamos que el 30 de marzo sea el inicio de Abril
        $inicioDeMeses = [
            1  => '2025-12-29', // Enero
            2  => '2026-02-02', // Febrero
            3  => '2026-03-02', // Marzo
            4  => '2026-03-30', // Abril (Semana 1 empieza el 30 de marzo)
            5  => '2026-05-04', // Mayo
            6  => '2026-06-01', // Junio
            7  => '2026-06-29', // Julio
            8  => '2026-08-03', // Agosto
            9  => '2026-08-31', // Septiembre
            10 => '2026-09-28', // Octubre
            11 => '2026-11-02', // Noviembre
            12 => '2026-11-30', // Diciembre
        ];

        foreach ($nombresMeses as $numero => $nombre) {
            $mes = Mes::create([
                'nombre'     => $nombre,
                'numero_mes' => $numero,
                'gestion_id' => $gestion->id
            ]);

            // Punto de inicio configurado para este mes
            $fechaCaminante = Carbon::parse($inicioDeMeses[$numero]);
            
            // Calculamos cuándo debe parar este mes (el inicio del siguiente)
            if ($numero < 12) {
                $proximoInicio = Carbon::parse($inicioDeMeses[$numero + 1]);
            } else {
                // Para diciembre, el límite es el inicio de enero del año siguiente
                $proximoInicio = Carbon::parse($inicioDeMeses[1])->addYear();
            }

            $contadorSemana = 1;

            // 3. Generación de semanas correlativas de 7 días
            while ($fechaCaminante < $proximoInicio) {
                $inicioSemana = $fechaCaminante->copy();
                $finSemana = $fechaCaminante->copy()->endOfWeek(Carbon::SUNDAY);

                Semana::create([
                    'numero_semana' => $contadorSemana,
                    'fecha_inicio'  => $inicioSemana->format('Y-m-d'),
                    'fecha_fin'     => $finSemana->format('Y-m-d'),
                    'mes_id'        => $mes->id
                ]);

                // Avanzamos exactamente una semana (7 días)
                $fechaCaminante->addWeek();
                $contadorSemana++;
            }
        }

        $this->command->info("Calendario 2026 regenerado con éxito.");
        $this->command->warn("Verificación: 30-03-2026 asignado a Abril.");
    }
}