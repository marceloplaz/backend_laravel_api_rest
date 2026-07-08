<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SincronizacionService; // Importamos el servicio

class SincronizarPersonal extends Command
{
    protected $signature = 'app:sincronizar-personal';
    protected $description = 'Sincroniza datos de personal desde SQL Server a MySQL';

    // Inyectamos el servicio en el constructor o en el handle
    public function handle(SincronizacionService $service)
    {
        $this->info("--- Iniciando proceso de sincronización ---");

        try {
            // Delegamos toda la lógica al Servicio
            $service->sincronizarDesdeSqlServer();
            
            $this->info("✔ Sincronización finalizada correctamente.");
        } catch (\Exception $e) {
            $this->error("✘ Error fatal: " . $e->getMessage());
        }
    }
}
