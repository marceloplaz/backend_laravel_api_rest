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
        .col-personal { text-align: left; width: 220px; padding-left: 8px !important; }
        .nombre-p { font-weight: bold; color: #333; text-transform: uppercase; font-size: 9px; display: block; }
        .cargo-p { color: #00bfa5; font-size: 8px; font-weight: bold; }

        /* Cuadro de Turno */
        .turno-box {
            background-color: #e0f2f1; /* Verde hospital claro */
            border: 1px solid #b2dfdb;
            border-radius: 4px;
            padding: 4px 2px;
            text-align: center;
        }
        .turno-nombre { 
            color: #00796b; 
            font-weight: bold; 
            font-size: 8px; 
            display: block; 
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .turno-horas { color: #555; font-size: 7px; font-weight: bold; }
        .vacio { color: #eee; text-align: center; font-size: 14px; }
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
        </tr>
    </thead>
    <tbody>
        @foreach($usuarios as $u)
        <tr>
            <td class="col-personal">
                <span class="nombre-p">{{ $u->persona->nombre_completo ?? $u->name }}</span>
                <span class="cargo-p">{{ $categoria->nombre }}</span>
            </td>

            @for($i = 1; $i <= 7; $i++)
                @php
                    $asignacion = $u->turnosAsignados->first(function($item) use ($i) {
                        return (int)\Carbon\Carbon::parse($item->fecha)->format('N') === $i;
                    });
                @endphp

                <td align="center">
                    @if($asignacion && $asignacion->turno)
                        <div class="turno-box">
                            <span class="turno-nombre">
                                {{-- Usamos nombre_turno según tu modelo Turno --}}
                                {{ $asignacion->turno->nombre_turno }}
                            </span>
                            <span class="turno-horas">
                                {{ \Carbon\Carbon::parse($asignacion->turno->hora_inicio)->format('H:i') }} - 
                                {{ \Carbon\Carbon::parse($asignacion->turno->hora_fin)->format('H:i') }}
                            </span>
                        </div>
                    @else
                        <span class="vacio">-</span>
                    @endif
                </td>
            @endfor
        </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>