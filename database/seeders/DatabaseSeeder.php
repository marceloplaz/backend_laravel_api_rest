<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 1. Configuraciones de Seguridad y Acceso
            PermissionSeeder::class,
            RoleSeeder::class,

            // 2. Datos Maestros (Estructura Base)
            CategoriaNuevasSeeder::class,     // Incorpora Técnicos, Mantenimiento, etc.
            ServicioSeeder::class,      // Servicios base
            NuevosServiciosSeeder::class, // Tus servicios adicionales recientes

            // 3. Estructura Detallada (Depende de Servicios)
            AreaSeeder::class,          // Vincula todas las áreas a los servicios

            // 4. Personal y Horarios
            UserAdminSeeder::class,     // Crea el administrador del sistema
            TurnoSeeder::class,         // Define Mañana, Tarde, Noche, etc.
            ServicioTurnoSeeder::class, // Relaciona qué turnos existen por servicio
            
            // 5. Datos Auxiliares o de Calendario
            CalendarioSeeder::class,
        ]);
    }
}