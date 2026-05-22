<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0.5cm; }
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; color: #333; margin: 0; }
        
        /* Encabezado Estilo Hospital */
        .header { width: 100%; border-bottom: 3px solid #00bfa5; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { color: #00bfa5; font-size: 22px; margin: 0; text-align: center; }
        
        .info-table { width: 100%; margin-bottom: 10px; }
        .info-table td { font-size: 11px; padding: 2px; }

        /* Tabla Principal */
        .main-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .main-table th { background-color: #f0fdfa; border: 1px solid #b2dfdb; padding: 8px; color: #00796b; font-size: 10px; }
        .main-table td { border: 1px solid #e0e0e0; padding: 5px; vertical-align: middle; height: 45px; }

        /* Celda del Personal */
        .col-personal { text-align: left; width: 180px; padding-left: 8px !important; }
        .nombre-p { font-weight: bold; color: #333; text-transform: uppercase; font-size: 9px; display: block; }
        .cargo-p { color: #757575; font-size: 8px; display: block; margin-top: 1px; }
        .salario-p { color: #00796b; font-size: 8px; font-weight: bold; text-transform: uppercase; display: block; margin-top: 1px; }

        /* Columnas de Totales */
        .col-totales { width: 45px; text-align: center; font-weight: bold; font-size: 9px; }
        .horas-txt { color: #0288d1; } /* Azul para destacar las horas calculadas */

        /* Cuadro de Turno */
        .turno-box {
            background-color: #e0f2f1; /* Verde hospital claro */
            border: 1px solid #b2dfdb;
            border-radius: 4px;
            padding: 4px 2px;
            text-align: center;
        }
        .turno-area { color: #555; font-size: 7px; font-weight: bold; display: block; margin-bottom: 1px; }
        .turno-nombre { 
            color: #00796b; 
            font-weight: bold; 
            font-size: 8px; 
            display: block; 
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .turno-horas { color: #555; font-size: 7px; font-weight: bold; }
        .vacio { color: #9e9e9e; text-align: center; font-size: 11px; }
    </style>
</head>
<body>

<div class="header">
    <h1>REPORTE SEMANAL DE ASISTENCIA</h1>
</div>

<table class="info-table">
    <tr>
        <td width="35%"><strong>SERVICIO:</strong> {{ $servicio->nombre }}</td>
        <td width="35%"><strong>CATEGORÍA:</strong> {{ $categoria->nombre }}</td>
        <td width="30%" align="right"><strong>MES:</strong> {{ $mes }}</td>
    </tr>
    <tr>
        <td colspan="3"><strong>PERIODO:</strong> {{ $periodo }}</td>
    </tr>
</table>

<table class="main-table">
    <thead>
        <tr>
            <th class="col-personal">PERSONAL</th>
            <th>LUNES</th>
            <th>MARTES</th>
            <th>MIÉRCOLES</th>
            <th>JUEVES</th>
            <th>VIERNES</th>
            <th>SÁBADO</th>
            <th>DOMINGO</th>
            <th class="col-totales">DÍAS</th>
            <th class="col-totales">HORAS</th>
        </tr>
    </thead>
    <tbody>
    @foreach($usuarios as $u)
        @php
            // Inicializadores para los totales acumulados del usuario en la semana
            $totalDiasTrabajados = 0;
            $totalHorasSemanales = 0;
            $fechasContadas = [];
        @endphp
    <tr>
        <td class="col-personal">
            <span class="nombre-p">{{ $u->persona->nombre_completo ?? $u->name }}</span>
            <span class="cargo-p">{{ $categoria->nombre }}</span>
            {{-- Muestra el tipo de salario dinámico que viene del modelo del usuario --}}
          <span class="salario-p">[{{ $u->persona->tipo_salario ?? 'No Definido' }}]</span>
        </td>

        @for($i = 0; $i < 7; $i++)
            @php
                $fechaString = \Carbon\Carbon::parse($fecha_inicio_limpia)->addDays($i)->format('Y-m-d');
                
                // Filtramos la colección de asignaciones para este día específico
                $asignacionesDelDia = $u->turnosAsignados->filter(function($item) use ($fechaString) {
                    return \Carbon\Carbon::parse($item->fecha)->format('Y-m-d') === $fechaString;
                });

                // Si trabajó este día, acumulamos sus métricas
                if($asignacionesDelDia->isNotEmpty()) {
                    if(!in_array($fechaString, $fechasContadas)) {
                        $totalDiasTrabajados++;
                        $fechasContadas[] = $fechaString;
                    }
                    foreach($asignacionesDelDia as $asig) {
                        // Suma la duración guardada en la tabla relacional de turnos
                        $totalHorasSemanales += floatval($asig->turno->duracion_horas ?? 0);
                    }
                }
            @endphp

            <td align="center">
                @if($asignacionesDelDia->isNotEmpty())
                    @foreach($asignacionesDelDia as $asignacion)
                        <div class="turno-box" style="margin-bottom: 4px;">
                            {{-- 1. AREA --}}
                            <span class="turno-area">
                                {{ $asignacion->area->nombre ?? ($asignacion->servicio->nombre ?? 'GENERAL') }}
                            </span>

                            {{-- 2. NOMBRE DEL TURNO --}}
                            <span class="turno-nombre">
                                {{ $asignacion->turno->nombre_turno ?? 'Sin Nombre' }}
                            </span>

                            {{-- 3. HORARIO --}}
                            <span class="turno-horas">
                                {{ \Carbon\Carbon::parse($asignacion->turno->hora_inicio)->format('H:i') }} - 
                                {{ \Carbon\Carbon::parse($asignacion->turno->hora_fin)->format('H:i') }}
                            </span>

                            {{-- 4. ETIQUETA ROJA EXTERNA --}}
                            @if($asignacion->servicio_id != $servicio->id)
                                <div style="color: #e74c3c; font-size: 6px; font-weight: bold; margin-top: 2px; border-top: 0.5px solid #ccc; padding-top: 1px; text-transform: uppercase;">
                                    {{ $asignacion->servicio->nombre ?? 'EXTERNO' }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                @else
                    <span class="vacio">-</span>
                @endif
            </td>
        @endfor

        {{-- CELDAS DE TOTALES (DÍAS Y HORAS) --}}
        <td class="col-totales">
            {{ $totalDiasTrabajados }}
        </td>
        <td class="col-totales horas-txt">
            {{ $totalHorasSemanales }}h
        </td>
    </tr>
    @endforeach
</tbody>
</table>

</body>
</html>