<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0.5cm; }
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; color: #333; margin: 0; }
        
        /* Encabezado Estilo Hospital - Sincronizado con tu Verde #065022 */
        .header { width: 100%; border-bottom: 3px solid #065022; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { color: #065022; font-size: 20px; font-weight: bold; margin: 0; text-align: center; letter-spacing: 0.5px; }
        
        .info-table { width: 100%; margin-bottom: 12px; }
        .info-table td { font-size: 11px; padding: 2px; color: #2b2b2b; }

        /* Tabla Principal con Layout Fijo Controlado por Porcentajes */
        .main-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .main-table th { background-color: #f4f9f5; border: 1px solid #bce2e2; padding: 8px 2px; color: #065022; font-size: 9.5px; font-weight: bold; }
        .main-table td { border: 1px solid #dee2e6; padding: 4px 2px; vertical-align: middle; height: 42px; }

        /* 💎 DISTRIBUCIÓN DE ANCHOS PROPORCIONADOS 💎 */
        .col-personal { width: 32.5%; text-align: left; padding-left: 8.5px !important; }
        .col-dia-semana { width: 8.5%; text-align: center; } 
        .col-totales { width: 4%; text-align: center; font-weight: bold; font-size: 12px; }
        
        /* Personal Grande e Imponente */
        .nombre-p { 
            font-weight: 800; 
            color: #111111; 
            text-transform: uppercase; 
            font-size: 14px; 
            display: block; 
            line-height: 1.1;
            white-space: nowrap; 
        }
        .cargo-p { color: #055112; font-size: 10px; display: block; margin-top: 2px; font-weight: 600; }
        .salario-p { color: #065022; font-size: 10px; font-weight: bold; text-transform: uppercase; display: block; margin-top: 1px; }

        .horas-txt { color: #09935c; }

        /* 🚀 CUADROS DE TURNOS CON FUENTES AGRANDADAS PARA IMPRESIÓN 🚀 */
        .turno-box {
            background-color: rgba(9, 134, 26, 0.08) !important;
            border: 1px solid rgba(7, 80, 34, 0.25);
            border-radius: 4px;
            padding: 4px 2px;
            text-align: center;
        }
        .turno-area { 
            color: #140101; 
            font-size: 8.5px; /* Aumentado para lectura clara */
            font-weight: bold; 
            display: block; 
            margin-bottom: 2px; 
        }
        .turno-nombre { 
            color: #065022; 
            font-weight: 800; 
            font-size: 12px;  /* ¡Agrandado! Resalta directo a la vista */
            display: block; 
            text-transform: uppercase;
            margin-bottom: 2px;
            line-height: 1.1;
        }
        .turno-horas { 
            color: #222; 
            font-size: 8.5px; /* Horarios legibles sin forzar la vista */
            font-weight: 700; 
        }
        .vacio { color: #adb5bd; text-align: center; font-size: 11px; font-weight: bold; }
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
            <span class="cargo-p">{{ $categoria->nombre }}</span>
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
                    @foreach($asignacionesDelDia as $asignacion)
                        <div class="turno-box" style="margin-bottom: 2px;">
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
                                <div style="color: #e74c3c; font-size: 6px; font-weight: bold; margin-top: 2px; border-top: 0.5px solid rgba(0,0,0,0.1); padding-top: 1px; text-transform: uppercase;">
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