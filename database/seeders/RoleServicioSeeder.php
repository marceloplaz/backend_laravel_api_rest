<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Servicio;

class RoleServicioSeeder extends Seeder
{
    public function run(): void
    {
        // 1. ACCESO TOTAL (super_admin, admin, admin_jefe_medico)
        $accesoTotal = Role::whereIn('id', [1, 2, 10])->get();
        $todosLosServicios = Servicio::all()->pluck('id')->toArray();

        foreach ($accesoTotal as $role) {
            $role->servicios()->sync($todosLosServicios);
        }

        // 2. JEFE_MEDICO_SERVICIO (ID 3)
        // Se deja declarado pero sin servicios base por ahora, 
        // para que solo vea el servicio específico que se le asigne luego.
        $jefeMedicoServicio = Role::find(3);
        if ($jefeMedicoServicio) {
             $jefeMedicoServicio->servicios()->detach(); 
        }

        // 3. JEFE_SERVICIO (Internos y Residentes) (ID 5)
        // Solo los servicios específicos donde realizan rotaciones académicas
        $jefeInternosResidentes = Role::find(5);
        if ($jefeInternosResidentes) {
            $serviciosAcademicos = Servicio::whereIn('nombre', [
                'MEDICINA INTERNA', 
                'CIRUGIA GENERAL', 
                'TRAUMATOLOGIA', 
                'PEDIATRIA', 
                'GINECOLOGIA', 
                'ANESTESIOLOGIA',
                'UTI-TERAPIA INTENSIVA', 
                'NEONATOLOGIA', 
                'BIOQUIMICOS', 
                'ENFERMERIA', 
                'FISIOTERAPIA', 
                'IMAGENOLOGIA'
            ])->pluck('id')->toArray();
            
            $jefeInternosResidentes->servicios()->sync($serviciosAcademicos);
        }

        // 4. JEFA_SERVICIOS_GENERALES (ID 12)
        $jefaServiciosGrales = Role::find(12);
        if ($jefaServiciosGrales) {
            $serviciosGrales = Servicio::whereNotIn('nombre', [
                'MEDICINA INTERNA', 
                'CIRUGIA GENERAL', 
                'TRAUMATOLOGIA', 
                'ENFERMERIA'
            ])->pluck('id')->toArray();
            $jefaServiciosGrales->servicios()->sync($serviciosGrales);
        }

        // 5. JEFATURAS DE ENFERMERÍA (IDs 11 y 13)
        $jefaturaEnfermeria = Role::whereIn('id', [11, 13])->get();
        foreach ($jefaturaEnfermeria as $role) {
            $role->servicios()->sync($todosLosServicios);
        }
    }
}