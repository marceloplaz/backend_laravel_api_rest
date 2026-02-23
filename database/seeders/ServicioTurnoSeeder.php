<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Servicio;
use App\Models\Turno;
use App\Models\Categoria;

class ServicioTurnoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Definimos los grupos de servicios
        $serviciosClinicos = [
            'HEMODIALISIS', 'CIRUGIA MUJERES', 'CIRUGIA VARONES', 
            'MEDICINA MUJERES', 'MEDICINA VARONES', 'GINECOLOGIA', 
            'MATERNIDAD', 'QUIROFANO', 'PEDIATRIA', 'NEONATOLOGIA', 
            'QUEMADOS', 'COLOPROCTOLOGIA', 'RAYOS X', 'EMERGENCIAS', 
            'NUTRICION', 'PATOLOGIA', 'UTI-TERAPIA INTENSIVA'
        ];

        $serviciosApoyo = ['LAVANDERIA', 'CAMILLEROS', 'COCINA'];

        // 2. Obtenemos los turnos por categoría
        $turnosMedicos = Turno::whereHas('categoria', fn($q) => $q->where('nombre', 'Médicos'))->pluck('id');
        $turnosEnfermeras = Turno::whereHas('categoria', fn($q) => $q->where('nombre', 'Enfermera'))->pluck('id');
        $turnosManuales = Turno::whereHas('categoria', fn($q) => $q->where('nombre', 'Manual'))->pluck('id');

        // 3. Vinculación Lógica
        $servicios = Servicio::all();

        foreach ($servicios as $servicio) {
            if (in_array($servicio->nombre, $serviciosClinicos)) {
                // En servicios clínicos pueden trabajar Médicos, Enfermeras y también Manuales (limpieza)
                $servicio->turnos()->sync(
                    $turnosMedicos->concat($turnosEnfermeras)->concat($turnosManuales)
                );
            } 
            elseif (in_array($servicio->nombre, $serviciosApoyo)) {
                // En Lavandería, Camilleros y Cocina SOLO pueden trabajar Manuales
                // Médicos y Enfermeras quedan excluidos aquí
                $servicio->turnos()->sync($turnosManuales);
            }
        }

        $this->command->info("Vínculos restringidos aplicados correctamente.");
    }
}