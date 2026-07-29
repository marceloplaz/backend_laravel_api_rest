<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Semana;
use App\Models\Categoria;
use Carbon\Carbon;

class SemanaController extends Controller
{
    /**
     * Lista las semanas filtradas por mes y categoría para los selects de la interfaz.
     */
    public function getSemanasPorMesYCategoria(Request $request)
    {
        $mesId = $request->input('mes_id');
        $categoriaId = $request->input('categoria_id');
        
        if ($categoriaId === 'todos' || $categoriaId === 'todas' || $categoriaId === '') {
            $categoriaId = null;
        }

        $semanas = Semana::where('mes_id', $mesId)
            ->when($categoriaId, function($query) use ($categoriaId) {
                $query->where(function($q) use ($categoriaId) {
                    $q->where('categoria_id', $categoriaId)
                      ->orWhereNull('categoria_id');
                });
            }, function($query) {
                $query->whereNull('categoria_id');
            })
            ->orderBy('numero_semana', 'asc')
            ->get();

        $resultado = $semanas->map(function($sem) {
            $fInicio = Carbon::parse($sem->fecha_inicio)->format('d/m');
            $fFin = Carbon::parse($sem->fecha_fin)->format('d/m');
            return [
                'id' => $sem->id,
                'numero_semana' => $sem->numero_semana,
                'fecha_inicio' => $sem->fecha_inicio,
                'fecha_fin' => $sem->fecha_fin,
                'etiqueta' => "Sem. {$sem->numero_semana} ({$fInicio} al {$fFin})"
            ];
        });

        return response()->json($resultado);
    }

    /**
     * Genera automáticamente las semanas de lunes a domingo basadas en un rango elegido por el administrador.
     */
    public function generarSemanas(Request $request)
    {
        $request->validate([
            'mes_id' => 'required|exists:meses,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'categoria_id' => 'nullable|exists:categorias,id'
        ]);

        $mesId = $request->input('mes_id');
        $categoriaId = $request->input('categoria_id');
        if ($categoriaId === 'todos' || $categoriaId === 'todas' || $categoriaId === '') {
            $categoriaId = null;
        }

        // Forzamos el inicio de la semana a Lunes y el fin de la última semana a Domingo
        $inicio = Carbon::parse($request->input('fecha_inicio'))->startOfWeek(Carbon::MONDAY);
        $finMax = Carbon::parse($request->input('fecha_fin'))->endOfWeek(Carbon::SUNDAY);

        // Limpiamos las semanas previas de ese mes y categoría antes de registrar las nuevas
        $queryBorrar = Semana::where('mes_id', $mesId);
        if ($categoriaId) {
            $queryBorrar->where('categoria_id', $categoriaId);
        } else {
            $queryBorrar->whereNull('categoria_id');
        }
        $queryBorrar->delete();

        $numeroSemana = 1;
        $currentInicio = $inicio->copy();

        // Bucle para crear bloques exactos de 7 días (Lunes a Domingo)
        while ($currentInicio->lte($finMax)) {
            $currentFin = $currentInicio->copy()->endOfWeek(Carbon::SUNDAY);

            Semana::create([
                'mes_id' => $mesId,
                'categoria_id' => $categoriaId,
                'numero_semana' => $numeroSemana,
                'fecha_inicio' => $currentInicio->toDateString(),
                'fecha_fin' => $currentFin->toDateString(),
            ]);

            $numeroSemana++;
            $currentInicio->addWeek()->startOfWeek(Carbon::MONDAY);
        }

        return response()->json([
            'message' => 'Semanas generadas exitosamente de lunes a domingo',
            'total_generadas' => $numeroSemana - 1
        ], 201);
    }
}