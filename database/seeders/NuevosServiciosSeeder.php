<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Servicio;

class NuevosServiciosSeeder extends Seeder
{
    public function run(): void
    {
        $servicios = [
            'ONCOLOGIA', 'GASTROENTEROLOGIA', 'QUIMIOTERAPIA', 'ANESTESIOLOGIA', 
            'NEUROCIRUGIA', 'ECOGRAFIA', 'QUIROFANO', 'ESTERILIZACION', 
            'CONSULTA EXTERNA', 'SALUD PUBLICA', 'TOMOGRAFIA Y RESONANCIA MAGNETICA', 
            'TRANSFUSION', 'ELECTROMEDICINA', 'ESTADISTICA', 'BIOQUIMICOS', 
            'SECRETARIA LABORATORIO', 'FARMACIA', 'COSTURA', 'PLANCHADO', 
            'LABORATORIO', 'LIMPIEZA ADM', 'CHOFERES'
        ];

        foreach ($servicios as $nombre) {
            Servicio::updateOrCreate(
                ['nombre' => $nombre], // No duplicará si ya existe
                [
                    'descripcion' => 'Servicio de ' . strtolower($nombre),
                    'cantidad_pacientes' => 0 // Valor inicial según tu modelo
                ]
            );
        }
    }
}