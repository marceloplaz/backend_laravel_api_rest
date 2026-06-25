<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Mensual de Turnos</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; color: #333; }
        .header { text-align: center; margin-bottom: 10px; }
        .header h1 { font-size: 16px; margin: 0; color: #004d40; text-transform: uppercase; }
        .meta-info { width: 100%; margin-bottom: 15px; font-weight: bold; font-size: 11px; }
        
        .rol-table { width: 100%; border-collapse: collapse; }
        .rol-table th { background-color: #e0f2f1; border: 1px solid #b2dfdb; padding: 6px; text-align: center; color: #004d40; }
        .rol-table td { border: 1px solid #b2dfdb; padding: 4px; text-align: center; vertical-align: middle; }
        
        .user-cell { text-align: left !important; padding-left: 8px !important; }
        .user-name { font-weight: bold; font-size: 10px; }
        .user-cat { font-size: 9px; color: #555; }
        
        .turno-container { margin-bottom: 3px; padding-bottom: 3px; border-bottom: 1px dashed #b2dfdb; }
        .turno-container:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .turno-box { font-size: 7px; line-height: 1.1; color: #00796b; }
        .area-text { font-weight: bold; font-size: 7px; color: #333; }
    </style>
</head>
<body>

    <div class="header">
        <h1>ROL MENSUAL DE TURNOS</h1>
    </div>

    <table class="meta-info">
        <tr>
            <td>SERVICIO: {{ $cabecera['servicio'] }}</td>
            <td>CATEGORÍA: {{ $cabecera['categoria_vista'] }}</td>
            <td style="text-align: right;">MES: {{ $cabecera['mes'] }}</td>
        </tr>
        <tr>
            <td colspan="3">PERIODO: {{ $cabecera['periodo_exacto'] }}</td>
        </tr>
    </table>

    <table class="rol-table">
        <thead>
            <tr>
                <th style="width: 20%;">PERSONAL</th>
                <th>LUNES</th><th>MARTES</th><th>MIÉRCOLES</th><th>JUEVES</th><th>VIERNES</th><th>SÁBADO</th><th>DOMINGO</th>
                <th style="width: 5%;">DÍAS</th>
                <th style="width: 6%;">HORAS</th>
            </tr>
        </thead>
        <tbody>
            @foreach($personalTurnos as $personal)
                <tr>
                    <td class="user-cell">
                        <div class="user-name">{{ strtoupper($personal['nombre']) }}</div>
                        <div class="user-cat">{{ $personal['categoria'] }}</div>
                        <div style="font-size: 8px; color: #d84315; font-weight: bold;">
                            {{ $personal['tipo_salario'] }}
                        </div>
                    </td>

                    @php 
                        $diasTotales = 0; 
                        $horasTotales = 0; 
                    @endphp
                    
                    {{-- Iteramos de Lunes (1) a Domingo (7) --}}
                    @for ($dia = 1; $dia <= 7; $dia++)
                        @php
                            // Extraemos todos los turnos que coincidan con este día de la semana a lo largo del mes
                            $turnosDelDia = $personal['dias_semana'][$dia] ?? [];
                        @endphp
                        <td>
                            @if(!empty($turnosDelDia))
                                @foreach($turnosDelDia as $t)
                                    @php 
                                        $diasTotales++; 
                                        // Sumamos el valor exacto de la columna duracion_horas de la BD
                                        $horasTotales += floatval($t['duracion_horas']); 
                                    @endphp
                                    <div class="turno-container">
                                        <div class="turno-box">
                                            <div class="area-text">{{ strtoupper($t['area']) }}</div>
                                            <div style="font-weight: bold;">{{ strtoupper($t['turno']) }}</div>
                                            <div>
                                                {{ substr($t['hora_inicio'], 0, 5) }} - {{ substr($t['hora_fin'], 0, 5) }}
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <span style="color: #ccc;">-</span>
                            @endif
                        </td>
                    @endfor

                    <td style="font-weight: bold;">{{ $diasTotales }}</td>
                    <td style="font-weight: bold; color: #00796b;">{{ $horasTotales }}h</td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>