<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. DEFINIR LOS PERMISOS (BITS)
        $permisos = [
            ['name' => 'admin_system', 'description' => 'Acceso total a CRUDs de configuración'],
            ['name' => 'gestionar_seguridad', 'description' => 'Cambiar contraseñas y gestionar accesos críticos'],
            ['name' => 'reportar_incidencia', 'description' => 'Crear notas de fallas técnicas'],
            ['name' => 'resolver_incidencia', 'description' => 'Resolver fallas técnicas'],
            ['name' => 'gestionar_servicios', 'description' => 'Configurar turnos en servicios'],
            ['name' => 'asignar_turnos', 'description' => 'Asignar personal a turnos'],
            ['name' => 'ver_equipo', 'description' => 'Ver calendario de subordinados'],
            ['name' => 'ver_reportes', 'description' => 'Generar reportes mensuales'],
        ];

        foreach ($permisos as $p) {
            Permission::updateOrCreate(['name' => $p['name']], $p);
        }

        // 2. PREPARAR GRUPOS DE IDs
        $allIds = Permission::all()->pluck('id');
        
        // El grupo para el Admin (Todo menos gestionar_seguridad)
        $adminIds = Permission::where('name', '!=', 'gestionar_seguridad')->pluck('id');
        
        $gestionIds = Permission::whereIn('name', ['reportar_incidencia', 'gestionar_servicios', 'asignar_turnos', 'ver_equipo', 'ver_reportes'])->pluck('id');
        $tecnicoIds = Permission::whereIn('name', ['reportar_incidencia', 'resolver_incidencia'])->pluck('id');
        $operativoIds = Permission::whereIn('name', ['reportar_incidencia'])->pluck('id');

        // 3. ASIGNACIÓN SEGÚN TU JERARQUÍA
        
        // SUPER ADMIN (Acceso Total + Seguridad)
        $super = Role::where('name', 'super_admin')->first();
        if ($super) $super->permissions()->sync($allIds);

        // ADMIN (Acceso Total - SIN Seguridad)
        $admin = Role::where('name', 'admin')->first();
        if ($admin) $admin->permissions()->sync($adminIds);

        // RESPONSABLE TÉCNICO
        $tecnico = Role::where('name', 'responsable_tecnico')->first();
        if ($tecnico) $tecnico->permissions()->sync($tecnicoIds);

        // JEFATURAS (Los 8 roles de jefatura que tienes)
        $jefaturas = Role::whereIn('name', [
            'jefe_servicio', 'jefa_enfermeras', 'jefa_servicios_generales',
            'admin_jefe_medico', 'admin_jefa_enfermeras', 'admin_jefa_servicios_generales',
            'jefe_medico_servicio', 'jefa_enfermeras_servicio'
        ])->get();
        foreach ($jefaturas as $j) $j->permissions()->sync($gestionIds);

        // OPERATIVOS
        $operativos = Role::whereIn('name', ['medico', 'enfermera', 'manual'])->get();
        foreach ($operativos as $o) $o->permissions()->sync($operativoIds);
    }
}