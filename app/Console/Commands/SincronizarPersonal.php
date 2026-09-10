<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SincronizacionService; 

class SincronizarPersonal extends Command
{
    protected $signature = 'app:sincronizar-personal';
    protected $description = 'Sincroniza datos de personal (enfermería, internos y residentes) desde SQL Server a MySQL';

    // Inyectamos el servicio en el handle
    public function handle(SincronizacionService $service)
    {
        $this->info("--- Iniciando proceso de sincronización ---");

        try {
            // 1. Sincronizar Enfermería (Auxiliares y Licenciadas)
            $this->info("Sincronizando personal de enfermería...");
            $service->sincronizarDesdeSqlServer();

            // 2. Sincronizar Internos y Residentes
            $this->info("Sincronizando internos y residentes...");
            $service->sincronizarInternosYResidentes();
            
            $this->info("✔ Sincronización completa finalizada correctamente.");
        } catch (\Exception $e) {
            $this->error("✘ Error fatal: " . $e->getMessage());
        }
    }
}