<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
// Database\Seeders\RoleSeeder.php
public function run(): void
{
    $roles = [
        ['name' => 'super_admin', 'description' => 'Control total'],
        ['name' => 'admin', 'description' => 'Gestión administrativa'],
        ['name' => 'admin_jefe_medico', 'description' => 'Gestiona médicos'],
        ['name' => 'admin_jefa_enfermeras', 'description' => 'Gestiona enfermería'],
        ['name' => 'admin_jefa_servicios_generales', 'description' => 'Gestiona manuales'],
        ['name' => 'jefe_medico_servicio', 'description' => 'Gestiona médicos por servicio'],
        ['name' => 'jefa_enfermeras_servicio', 'description' => 'Gestiona enfermería por servicio'],
        ['name' => 'responsable_tecnico', 'description' => 'Soluciona incidencias'],
        ['name' => 'medico', 'description' => 'Personal médico'],
        ['name' => 'enfermera', 'description' => 'Personal enfermería'],
        ['name' => 'manual', 'description' => 'Apoyo y limpieza'],
    ];

    foreach ($roles as $rol) {
        // Usamos updateOrCreate para que si ya existe, actualice la descripción
        \App\Models\Role::updateOrCreate(
            ['name' => $rol['name']], 
            ['description' => $rol['description'], 'estado' => 1]
        );
    }
}

}
