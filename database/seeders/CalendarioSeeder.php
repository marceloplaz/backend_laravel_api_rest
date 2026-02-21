<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CalendarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function asignarTurno(Request $request) {
    $fecha = Carbon::parse($request->fecha); // Ej: "2026-02-20"

    // Buscamos automáticamente en qué semana y mes cae esa fecha
    $semana = Semana::where('fecha_inicio', '<=', $fecha->format('Y-m-d'))
                    ->where('fecha_fin', '>=', $fecha->format('Y-m-d'))
                    ->first();

    if (!$semana) {
        return response()->json(['error' => 'La fecha no está dentro del calendario configurado'], 400);
    }

    // Creamos el registro con los IDs automáticos
    TurnoAsignado::create([
        'usuario_id' => $request->usuario_id,
        'servicio_id' => $request->servicio_id,
        'turno_id'   => $request->turno_id,
        'fecha'      => $fecha->format('Y-m-d'),
        'semana_id'  => $semana->id,
        'mes_id'     => $semana->mes_id,
        'gestion_id' => $semana->mes->gestion_id,
        'estado'     => 'programado'
    ]);
}
}
