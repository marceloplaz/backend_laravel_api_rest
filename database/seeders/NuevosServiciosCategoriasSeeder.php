<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Servicio;
use App\Models\Categoria;

class NuevosServiciosCategoriasSeeder extends Seeder
{
    public function run(): void
    {
        // --- NUEVAS CATEGORÍAS ---
        $categorias = [
            [
                'nombre' => 'Internos',
                'nivel' => 7,
                'descripcion' => 'Estudiantes de medicina en último año'
            ],
            [
                'nombre' => 'Residentes',
                'nivel' => 8,
                'descripcion' => 'Médicos en proceso de especialización'
            ],
        ];

        foreach ($categorias as $cat) {
            Categoria::updateOrCreate(
                ['nombre' => $cat['nombre']], 
                [
                    'nivel' => $cat['nivel'],
                    'descripcion' => $cat['descripcion']
                ]
            );
        }

        // --- NUEVOS SERVICIOS ---
        $servicios = [
            ['nombre' => 'MEDICINA INTERNA', 'descripcion' => 'Atención integral del adulto'],
            ['nombre' => 'CIRUGIA GENERAL', 'descripcion' => 'Procedimientos quirúrgicos generales'],
            ['nombre' => 'TRAUMATOLOGIA', 'descripcion' => 'Lesiones del sistema músculo-esquelético'],
            ['nombre' => 'ENFERMERIA', 'descripcion' => 'Servicios generales de enfermería'],
            ['nombre' => 'FISIOTERAPIA', 'descripcion' => 'Rehabilitación física y motora'],
            ['nombre' => 'IMAGENOLOGIA', 'descripcion' => 'Estudios de diagnóstico por imagen'],
        ];

        foreach ($servicios as $ser) {
            Servicio::updateOrCreate(
                ['nombre' => $ser['nombre']], 
                ['descripcion' => $ser['descripcion']]
            );
        }
    }
}