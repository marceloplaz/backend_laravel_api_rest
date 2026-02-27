<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. DEFINIR LOS PERMISOS (Solo campos que existen en tu DB)
        $permisos = [
            ['action' => 'admin_system'],
            ['action' => 'gestionar_seguridad'],
            ['action' => 'reportar_incidencia'],
            ['action' => 'resolver_incidencia'],
            ['action' => 'gestionar_servicios'],
            ['action' => 'asignar_turnos'],
            ['action' => 'ver_equipo'],
            ['action' => 'ver_reportes'],
        ];

        foreach ($permisos as $p) {
            Permission::updateOrCreate(
                ['action' => $p['action']], 
                [
                    'action'        => $p['action'],
                    'subject'       => 'all',
                    'label'         => ucfirst(str_replace('_', ' ', $p['action'])),
                    'visibleInMenu' => true,
                ]
            );
        }

        // 2. PREPARAR GRUPOS DE IDs (Dentro de la función run)
        $allIds = Permission::all()->pluck('id');
        
        $adminIds = Permission::where('action', '!=', 'gestionar_seguridad')->pluck('id');
        $gestionIds = Permission::whereIn('action', ['reportar_incidencia', 'gestionar_servicios', 'asignar_turnos', 'ver_equipo', 'ver_reportes'])->pluck('id');
        $tecnicoIds = Permission::whereIn('action', ['reportar_incidencia', 'resolver_incidencia'])->pluck('id');
        $operativoIds = Permission::whereIn('action', ['reportar_incidencia'])->pluck('id');

        // 3. ASIGNACIÓN SEGÚN TU JERARQUÍA
        
        // SUPER ADMIN
        $super = Role::where('name', 'super_admin')->first();
        if ($super) $super->permissions()->sync($allIds);

        // ADMIN
        $admin = Role::where('name', 'admin')->first();
        if ($admin) $admin->permissions()->sync($adminIds);

        // RESPONSABLE TÉCNICO
        $tecnico = Role::where('name', 'responsable_tecnico')->first();
        if ($tecnico) $tecnico->permissions()->sync($tecnicoIds);

        // JEFATURAS
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