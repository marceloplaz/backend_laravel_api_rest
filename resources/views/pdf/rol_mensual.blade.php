<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Semanal de Asistencia</title>
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

                    @php $diasTotales = 0; $horasTotales = 0; @endphp
                    
                    @for ($dia = 1; $dia <= 7; $dia++)
                        <td>
                            @php 
                                $turnoEncontrado = null;
                                foreach ($personal['semanas'] as $semana) {
                                    foreach ($semana as $t) {
                                        if (\Carbon\Carbon::parse($t['fecha'])->dayOfWeekIso == $dia) {
                                            $turnoEncontrado = $t;
                                        }
                                    }
                                }
                            @endphp
                            
                            @if($turnoEncontrado)
    <div class="turno-box">
        <div class="area-text">{{ strtoupper($turnoEncontrado['area']) }}</div>
        <div style="font-weight: bold;">{{ strtoupper($turnoEncontrado['turno']) }}</div>
        <div>
            {{ \Carbon\Carbon::parse($turnoEncontrado['hora_inicio'])->format('H:i') }} - 
            {{ \Carbon\Carbon::parse($turnoEncontrado['hora_fin'])->format('H:i') }}
        </div>
    </div>
    @php 
        $diasTotales++; 
        $horasTotales += 6; 
    @endphp
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