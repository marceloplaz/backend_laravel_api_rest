<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Servicio;

class ServicioSeeder extends Seeder
{
    public function run(): void
    {
        $servicios = [
            ['nombre' => 'HEMODIALISIS', 'descripcion' => 'Tratamiento de insuficiencia renal'],
            ['nombre' => 'CIRUGIA MUJERES', 'descripcion' => 'Área de cirugías para pacientes femeninas'],
            ['nombre' => 'CIRUGIA VARONES', 'descripcion' => 'Área de cirugías para pacientes masculinos'],
            ['nombre' => 'MEDICINA MUJERES', 'descripcion' => 'Medicina interna femenina'],
            ['nombre' => 'MEDICINA VARONES', 'descripcion' => 'Medicina interna masculina'],
            ['nombre' => 'GINECOLOGIA', 'descripcion' => 'Salud reproductiva femenina'],
            ['nombre' => 'MATERNIDAD', 'descripcion' => 'Atención de partos y post-parto'],
            ['nombre' => 'QUIROFANO', 'descripcion' => 'Salas de operaciones'],
            ['nombre' => 'PEDIATRIA', 'descripcion' => 'Atención médica infantil'],
            ['nombre' => 'NEONATOLOGIA', 'descripcion' => 'Cuidado de recién nacidos'],
            ['nombre' => 'QUEMADOS', 'descripcion' => 'Unidad de cuidados para quemaduras'],
            ['nombre' => 'COLOPROCTOLOGIA', 'descripcion' => 'Especialidad en colon y recto'],
            ['nombre' => 'RAYOS X', 'descripcion' => 'Servicios de radiología e imagenología'],
            ['nombre' => 'EMERGENCIAS', 'descripcion' => 'Atención de urgencias médicas'],
            ['nombre' => 'NUTRICION', 'descripcion' => 'Planificación dietética y nutrición'],
            ['nombre' => 'PATOLOGIA', 'descripcion' => 'Laboratorio de análisis de tejidos'],
            ['nombre' => 'UTI-TERAPIA INTENSIVA', 'descripcion' => 'Cuidados críticos e intensivos'],
            ['nombre' => 'CAMILLEROS', 'descripcion' => 'Servicio de traslado de pacientes'],
            ['nombre' => 'LAVANDERIA', 'descripcion' => 'Gestión de textiles hospitalarios'],
            ['nombre' => 'COCINA', 'descripcion' => 'Gestión de alimentacion Hospitalaria'],
            
        ];

        foreach ($servicios as $s) {
            Servicio::updateOrCreate(['nombre' => $s['nombre']], $s);
        }
    }
}