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
           
            PermissionSeeder::class,
            RoleSeeder::class,

           
            CategoriaNuevasSeeder::class,     
            ServicioSeeder::class,      
            NuevosServiciosSeeder::class,

            
            AreaSeeder::class,

            
            UserAdminSeeder::class,     // Crea el administrador del sistema
            TurnoSeeder::class,         // Define Mañana, Tarde, Noche, etc.
            ServicioTurnoSeeder::class, // Relaciona qué turnos existen por servicio
            
            
            CalendarioSeeder::class,
           
            ComidaSeeder::class,
        ]);
    }
}