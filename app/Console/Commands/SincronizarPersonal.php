<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SincronizarPersonal extends Command
{
    protected $signature = 'app:sincronizar-personal';
    protected $description = 'Sincroniza datos de personal desde SQL Server a MySQL';

 public function handle()
{
    $dbName = DB::connection('mysql')->getDatabaseName();
    $dbHost = DB::connection('mysql')->getConfig('host');
    
    $this->info("--- DEBUG DE CONEXIÓN ---");
    $this->info("Host destino: " . $dbHost);
    $this->info("Base de datos destino: " . $dbName);
    $this->info("-------------------------");
    $this->info('Iniciando sincronización...');

    try {
        $datosSql = DB::connection('sqlsrv_externo')->table('PERSONA')->get();
        $total = $datosSql->count();
        
        $this->info("Registros encontrados en SQL Server: $total");

        foreach ($datosSql as $item) {
            try {
                $this->line("Procesando: " . $item->nombre_completo);

                DB::connection('mysql')->table('personas')->updateOrInsert(
                    ['carnet_identidad' => $item->carnet_identidad],
                    [
                        'nombre_completo'           => $item->nombre_completo,
                'fecha_nacimiento'          => $item->fecha_nacimiento,
                'genero'                    => $item->genero,
                'telefono'                  => $item->telefono,
                'direccion'                 => $item->direccion,
                'tipo_trabajador'           => $item->tipo_trabajador,
                'nacionalidad'              => $item->nacionalidad,
                'tipo_salario'              => $item->tipo_salario,
                'numero_tipo_salario'       => $item->numero_tipo_salario,
                'fecha_ingreso_institucion' => $item->fecha_ingreso_institucion,
                'user_id'                   => $item->user_id, 
                'updated_at'                => now(),
                'created_at'                => now(), 
                    ]
                );
                
                // Mover el éxito DENTRO del foreach asegura que se imprima por cada uno
                $this->info("✔ Éxito al guardar a: " . $item->nombre_completo);

            } catch (\Exception $e) {
                // Mover el error DENTRO del foreach atrapa errores por registro individual
                $this->error("✘ Error al procesar a " . $item->nombre_completo . ": " . $e->getMessage());
            }
        }

        $this->info("Sincronización finalizada.");

    } catch (\Exception $e) {
        $this->error("Error fatal de conexión: " . $e->getMessage());
    }
}
}