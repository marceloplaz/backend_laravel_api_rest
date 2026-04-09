
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Personal</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; }
        /* Estilo Verde Hospital */
        .header { text-align: center; color: #1a4f32; border-bottom: 2px solid #1a4f32; padding-bottom: 10px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #1a4f32; color: white; padding: 10px; text-align: left; font-size: 12px; }
        td { border: 1px solid #a3cfbb; padding: 8px; font-size: 11px; }
        tr:nth-child(even) { background-color: #f2f8f5; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: right; font-size: 9px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>GESTIÓN DE PERSONAL - HOSPITAL</h1>
        <p>Reporte Generado por: jugadordeunbit</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Nombre Completo</th>
                <th>C.I.</th>
                <th>Cargo / Especialidad</th>
                <th>Salario</th>
            </tr>
        </thead>
        <tbody>
            @foreach($personal as $p)
            <tr>
                <td>{{ $p->nombre_completo }}</td>
                <td>{{ $p->carnet_identidad }}</td>
                <td>{{ $p->tipo_trabajador }}</td>
                <td>{{ number_format($p->numero_tipo_salario, 2) }} {{ $p->tipo_salario }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Fecha de emisión: {{ date('d/m/Y H:i:s') }}
    </div>
</body>
</html>