<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { 
            margin: 1cm 0.8cm; 
        }
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 9.5px; 
            color: #2b2b2b; 
            margin: 0; 
            background-color: #ffffff;
        }
        
        /* Título Principal Estilo Mensual */
        .header-title {
            text-align: center;
            color: #065022;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 12px;
            letter-spacing: 0.5px;
        }

        /* Tabla de Información General */
        .info-table { 
            width: 100%; 
            margin-bottom: 15px; 
            border-collapse: collapse;
        }
        .info-table td { 
            font-size: 10.5px; 
            padding: 3px 0; 
            color: #2b2b2b; 
        }

        /* Tabla Principal de Asistencia */
        .main-table { 
            width: 100%; 
            border-collapse: collapse; 
            table-layout: fixed; 
        }
        .main-table th { 
            background-color: #e1f5ee; 
            border: 1px solid #bce2e2; 
            padding: 7px 3px; 
            color: #065022; 
            font-size: 9px; 
            font-weight: bold; 
            text-align: center;
        }
        .main-table td { 
            border: 1px solid #d0e8df; 
            padding: 5px 3px; 
            vertical-align: middle; 
        }

        /* Anchos de Columnas Optimizados */
        .col-personal { width: 24%; text-align: left; padding-left: 6px !important; }
        .col-dia-semana { width: 9.5%; text-align: center; } 
        .col-totales { width: 4.5%; text-align: center; font-weight: bold; font-size: 11px; }
        
        /* Estilos del Personal */
        .nombre-p { font-weight: bold; color: #111111; text-transform: uppercase; font-size: 9.5px; display: block; line-height: 1.1; }
        .cargo-p { color: #065022; font-size: 8.5px; display: block; margin-top: 1px; font-weight: bold; }
        .salario-p { color: #c0392b; font-size: 8px; font-weight: bold; display: block; margin-top: 1px; }

        /* Tarjetas de Turnos Limpias y Elegantes (Estilo Mensual) */
        .turno-box { 
            background-color: #f4fbf8; 
            border: 1px solid #a3d9c9; 
            border-radius: 3px; 
            padding: 3px 2px; 
            text-align: center; 
            margin-bottom: 2px;
        }
        .turno-area { color: #555; font-size: 7.5px; font-weight: bold; display: block; margin-bottom: 1px; text-transform: uppercase; }
        .turno-nombre { color: #065022; font-weight: bold; font-size: 9.5px; display: block; text-transform: uppercase; margin-bottom: 1px; }
        .turno-horas { color: #333; font-size: 8px; font-weight: normal; }
        
        .vacio { color: #bbb; text-align: center; font-size: 10px; }
        .horas-txt { color: #065022; }

        /* Divisor elegante cuando hay turnos cruzados o múltiples */
        .turno-divider {
            border-top: 1px dashed #a3d9c9;
            margin: 3px 0;
        }
    </style>
</head>
<body>

<!-- Cabecera idéntica al reporte mensual -->
<div class="header-title">REPORTE SEMANAL DE ASISTENCIA</div>

<table class="info-table">
    <tr>
        <td width="40%"><strong>SERVICIO:</strong> {{ $servicio->nombre }}</td>
        <td width="35%"><strong>CATEGORÍA:</strong> {{ $nombre_categorias }}</td>
        <td width="25%" align="right"><strong>MES:</strong> {{ $mes }}</td>
    </tr>
    <tr>
        <td colspan="3"><strong>PERIODO:</strong> {{ $periodo }}</td>
    </tr>
</table>

<table class="main-table">
    <thead>
        <tr>
            <th class="col-personal">PERSONAL</th>
            <th class="col-dia-semana">LUNES</th>
            <th class="col-dia-semana">MARTES</th>
            <th class="col-dia-semana">MIÉRCOLES</th>
            <th class="col-dia-semana">JUEVES</th>
            <th class="col-dia-semana">VIERNES</th>
            <th class="col-dia-semana">SÁBADO</th>
            <th class="col-dia-semana">DOMINGO</th>
            <th class="col-totales">DÍAS</th>
            <th class="col-totales">HORAS</th>
        </tr>
    </thead>
    <tbody>
    @foreach($usuarios as $u)
        @php
            $totalDiasTrabajados = 0;
            $totalHorasSemanales = 0;
            $fechasContadas = [];
        @endphp
    <tr>
        <td class="col-personal">
            <span class="nombre-p">{{ $u->persona->nombre_completo ?? $u->name }}</span>
            <span class="cargo-p">{{ $u->categoria->nombre ?? 'Sin Categoría' }}</span>
            <span class="salario-p">[{{ $u->persona->tipo_salario ?? 'No Definido' }}]</span>
        </td>

        @for($i = 0; $i < 7; $i++)
            @php
                $fechaString = \Carbon\Carbon::parse($fecha_inicio_limpia)->addDays($i)->format('Y-m-d');
                $asignacionesDelDia = $u->turnosAsignados->filter(function($item) use ($fechaString) {
                    return \Carbon\Carbon::parse($item->fecha)->format('Y-m-d') === $fechaString;
                });

                if($asignacionesDelDia->isNotEmpty()) {
                    if(!in_array($fechaString, $fechasContadas)) {
                        $totalDiasTrabajados++;
                        $fechasContadas[] = $fechaString;
                    }
                    foreach($asignacionesDelDia as $asig) {
                        $totalHorasSemanales += floatval($asig->turno->duracion_horas ?? 0);
                    }
                }
            @endphp

            <td align="center" class="col-dia-semana">
                @if($asignacionesDelDia->isNotEmpty())
                    @foreach($asignacionesDelDia as $index => $asignacion)
                        @if($index > 0)
                            <div class="turno-divider"></div>
                        @endif
                        <div class="turno-box">
                            <span class="turno-area">
                                {{ $asignacion->area->nombre ?? ($asignacion->servicio->nombre ?? 'GENERAL') }}
                            </span>
                            <span class="turno-nombre">
                                {{ $asignacion->turno->nombre_turno ?? 'Sin Nombre' }}
                            </span>
                            <span class="turno-horas">
                                {{ \Carbon\Carbon::parse($asignacion->turno->hora_inicio)->format('H:i') }} - 
                                {{ \Carbon\Carbon::parse($asignacion->turno->hora_fin)->format('H:i') }}
                            </span>
                            @if($asignacion->servicio_id != $servicio->id)
                                <div style="color: #e74c3c; font-size: 6.5px; font-weight: bold; margin-top: 1px; border-top: 0.5px solid rgba(0,0,0,0.1); padding-top: 1px; text-transform: uppercase;">
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

        <td class="col-totales">{{ $totalDiasTrabajados }}</td>
        <td class="col-totales horas-txt">{{ $totalHorasSemanales }}h</td>
    </tr>
    @endforeach
    </tbody>
</table>

</body>
</html>