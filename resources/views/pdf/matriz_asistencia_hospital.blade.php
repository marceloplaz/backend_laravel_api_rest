<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planilla de Asistencia del Personal</title>
    <style>
        @page {
            size: letter landscape;
            margin: 6mm;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 7px;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .header-title {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        .sub-title {
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .info-bar {
            font-size: 8px;
            font-weight: bold;
            margin-bottom: 5px;
            border-bottom: 1px solid #444;
            padding-bottom: 3px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #666;
            text-align: center;
            padding: 2px 1px;
            overflow: hidden;
            word-wrap: break-word;
            vertical-align: middle;
        }
        th {
            background-color: #e6e6e6;
            font-size: 6.5px;
            font-weight: bold;
        }
        .col-n { width: 16px; }
        .col-ci { width: 42px; }
        .col-nombre { text-align: left; padding-left: 3px; width: 100px; font-weight: bold; font-size: 6.5px; }
        .col-dia { width: 15px; font-size: 6.5px; }
        .col-totales { width: 26px; font-weight: bold; font-size: 6.5px; }
        .col-obs { width: 80px; font-size: 5.5px; text-align: left; padding-left: 2px; }
        
        .text-presente { color: #0044cc; font-weight: bold; }
        .text-falta { color: #cc0000; font-weight: bold; }
        .text-permiso { color: #885500; font-weight: bold; }
    </style>
</head>
<body>

    <div class="header-title">HOSPITAL REGIONAL SAN JUAN DE DIOS - TARIJA</div>
    <div class="sub-title">INFORME: PLANILLA DE ASISTENCIA DEL PERSONAL</div>

    <div class="info-bar">
        PERIODO: Del {{ \Carbon\Carbon::parse($fechaInicio)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }} 
        &nbsp;&nbsp;|&nbsp;&nbsp; PERSONAL / CATEGORÍA: {{ strtoupper($nombreCategoria) }}
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" class="col-n">N°</th>
                <th rowspan="2" class="col-ci">CEDULA</th>
                <th rowspan="2" class="col-nombre">NOMBRES Y APELLIDOS</th>
                
                {{-- Días del mes (1 al 31) --}}
                @foreach($fechasRango as $fec)
                    <th class="col-dia">{{ \Carbon\Carbon::parse($fec)->format('j') }}</th>
                @endforeach

                <th rowspan="2" class="col-totales">TOTAL MINUTOS ATRASADO</th>
                <th rowspan="2" class="col-totales">TOTAL HRAS DEL MES</th>
                <th rowspan="2" class="col-obs">OBSERVACIONES</th>
            </tr>
            <tr>
                {{-- Día de la semana (L, M, M, J, V, S, D) --}}
                @foreach($fechasRango as $fec)
                    <th class="col-dia" style="font-size: 5px; background-color: #f2f2f2;">
                        {{ mb_strtoupper(mb_substr(\Carbon\Carbon::parse($fec)->locale('es')->dayName, 0, 1)) }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($empleados as $index => $emp)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $emp['ci'] }}</td>
                    <td class="col-nombre">{{ $emp['nombre_completo'] }}</td>

                    @foreach($fechasRango as $fec)
                        @php 
                            $infoDia = $emp['dias'][$fec]['estado'] ?? '';
                        @endphp
                        <td class="{{ $infoDia === 'F' ? 'text-falta' : ($infoDia === 'PER' ? 'text-permiso' : 'text-presente') }}">
    {{ $infoDia }}
</td>
                    @endforeach

                    <td>{{ $emp['total_minutos_atraso'] > 0 ? $emp['total_minutos_atraso'] : '' }}</td>
                    <td>{{ $emp['total_horas_mes'] > 0 ? $emp['total_horas_mes'] : '' }}</td>
                    <td class="col-obs">{{ $emp['observaciones'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>