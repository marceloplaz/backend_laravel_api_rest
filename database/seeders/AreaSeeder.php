<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Servicio;
use App\Models\Area;

class AreaSeeder extends Seeder
{
    public function run(): void
    {
        // 1. NEONATOLOGIA
        $this->cargarAreasGenericas('NEONATOLOGIA', [
            'UCIN', 'UCIN/MAT/INT', 'INTERMEDIA', 'MAT/INT', 'MATERNIDAD'
        ]);

        // 2. GASTROENTEROLOGIA
        $this->cargarAreasGenericas('GASTROENTEROLOGIA', [
            'ENDOSCOPIA', 'SALA', 'CONSULTA EXTERNA', 'CONSULTA SALA', 'ECOGRAFIA',
            'RAYOS X', 'TOMOGRAFIA- RESONANCIA MAGNETICA', 'PROGRAMACION DE ENDOSCOPIA'
        ]);

        // 3. HEMODIALISIS / NEFROLOGIA
        $this->cargarAreasGenericas('HEMODIALISIS', [
            'HD EMERGENCIA', 'CONSULTA EXTERNA', 'INTERCONSULTA', 'HD', 'IC'
        ]);

        // 4. QUEMADOS / CIRUGIA PLASTICA
        $this->cargarAreasGenericas('QUEMADOS', [
            'SALA', 'INTERCONSULTA', 'CONSULTORIO', 'APOYO CLINICO QUIRURGICO Y REEMPLAZO'
        ]);

        // 5. ANESTESIOLOGIA
        $this->cargarAreasGenericas('ANESTESIOLOGIA', [
            'URPA MAÑANA', 'PROG. TARDE Y URPA'
        ]);

        // 6. QUIROFANO
        $this->cargarAreasGenericas('QUIROFANO', [
            'Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'CIRUGIAS PROGRAMADAS', 'CIRUGIAS EMERGENCIAS', 'URPA'
        ]);

        // 7. EMERGENCIAS
        $this->cargarAreasGenericas('EMERGENCIAS', [
            'EMERGENCIAS CIRUGIA', 'EMERGENCIAS MEDICINA', 'EMERGENCIAS TRIAGE 1', 
            'EMERGENCIAS APOYO', 'EMERGENCIAS TRIAGE 2', 'RESPONSABLE DE TURNO MAÑANA', 
            'RESPONSABLE DE TURNO TARDE', 'RESPONSABLE DE TURNO NOCHE'
        ]);

        // 8. LABORATORIO Y BIOQUIMICOS
        $this->cargarAreasGenericas('LABORATORIO', [
            'TOMA DE MUESTRA', 'HEMATOLOGIA', 'QUIMICA SANGUINEA', 'BACTERIOLOGIA', 
            'INMUNOLOGIA', 'UROANALISIS', 'COPROLOGIA'
        ]);
        $this->cargarAreasGenericas('BIOQUIMICOS', ['VALIDACION DE RESULTADOS', 'CONTROL DE CALIDAD']);
        $this->cargarAreasGenericas('SECRETARIA LABORATORIO', ['RECEPCION', 'ENTREGA DE RESULTADOS']);

        // 9. OTROS SERVICIOS DE APOYO
        $this->cargarAreasGenericas('FARMACIA', ['DISPENSACION', 'ALMACEN', 'UNIDOSIS']);
        $this->cargarAreasGenericas('TRANSFUSION', ['SEROLOGIA', 'FRACCIONAMIENTO', 'INMUNOHEMATOLOGIA']);
        $this->cargarAreasGenericas('ESTADISTICA', ['ADMISION', 'ARCHIVO CLINICO', 'CODIFICACION']);
        $this->cargarAreasGenericas('SALUD PUBLICA', ['INYECTABLES', 'TUBERCULOSIS', 'ELECTRO ENCEFALOGRAMA', 'PAI']);
        $this->cargarAreasGenericas('CIRUGIA MUJERES', ['TRAUMATOLOGIA']);
        $this->cargarAreasGenericas('CIRUGIA VARONES', ['TRAUMATOLOGIA']);
        $this->cargarAreasGenericas('MEDICINA MUJERES', ['ONCOLOGIA']);

        // 10. SERVICIOS CON CONSULTORIOS (Se cargan vacíos para llenar luego)
        $this->cargarGineco();
        $this->cargarConsultaExterna();

        // 11. BARRIDO FINAL: Asegura que TODOS los servicios tengan un área (GENERAL)
        $this->crearAreaGeneralParaTodos();
    }

    private function cargarAreasGenericas($nombreServicio, $nombresAreas)
    {
        $servicio = Servicio::where('nombre', 'LIKE', "%$nombreServicio%")->first();
        if ($servicio) {
            foreach ($nombresAreas as $area) {
                Area::updateOrCreate(
                    ['servicio_id' => $servicio->id, 'nombre' => $area],
                    ['activo' => true, 'numero_consultorio' => null]
                );
            }
        }
    }

    private function cargarGineco()
    {
        $servicio = Servicio::where('nombre', 'GINECOLOGIA')->first();
        if ($servicio) {
            $areas = [
                'CIRUGIA GINECOOBSTETRICIA', 'VISITA', 'PAT. CERVICAL', 
                'CONSULTA EXTERNA MAÑANA 1', 'CONSULTA EXTERNA MAÑANA 2', 
                'CONSULTA EXTERNA TARDE 1', 'CONSULTA EXTERNA TARDE 2', 'GUARDIA'
            ];
            foreach ($areas as $a) {
                Area::updateOrCreate(
                    ['servicio_id' => $servicio->id, 'nombre' => $a],
                    ['activo' => true, 'numero_consultorio' => null]
                );
            }
        }
    }

    private function cargarConsultaExterna()
    {
        $servicio = Servicio::where('nombre', 'CONSULTA EXTERNA')->first();
        if ($servicio) {
            $especialidades = [
                'Gineco-Obstetricia N1', 'Gineco-Obstetricia N2', 'Diabetologa', 'Clinica Medica',
                'Alergologia', 'Vacuna BCG Maternidad', 'Neumologia - Tuberculosis', 'Neurocirugia',
                'Coloproctologia', 'Urologia 1 y 2', 'Gastroenterologia', 'Traumatologia 1 y 2',
                'Cirugia del Torax', 'Cirugia Cardio Vascular', 'Clinica Quirurgica', 'Nefrologia',
                'Dermatologia', 'Endocrinologia', 'Cardiologia', 'Oncologia General', 'Nutriologia'
            ];
            foreach ($especialidades as $nombre) {
                Area::updateOrCreate(
                    ['servicio_id' => $servicio->id, 'nombre' => $nombre],
                    ['activo' => true, 'numero_consultorio' => null]
                );
            }
        }
    }

    private function crearAreaGeneralParaTodos()
    {
        $servicios = Servicio::all();
        foreach ($servicios as $s) {
            Area::updateOrCreate(
                [
                    'servicio_id' => $s->id, 
                    'nombre' => $s->nombre . ' (GENERAL)'
                ],
                [
                    'activo' => true,
                    'numero_consultorio' => null
                ]
            );
        }
    }
}