<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        /* Configuraciones para el PDF */
        @page { margin: 1.5cm; }
        body { font-family: 'Helvetica', Arial, sans-serif; color: #333; line-height: 1.4; font-size: 11px; }
        
        /* Encabezado */
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #28a745; padding-bottom: 10px; }
        .hospital-name { font-size: 18px; font-weight: bold; color: #28a745; margin: 0; }
        .report-title { font-size: 14px; color: #555; text-transform: uppercase; margin: 5px 0; }
        .meta-info { font-size: 9px; color: #888; }

        /* Tabla de Datos */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f2f2f2; border: 1px solid #ccc; padding: 8px; text-align: left; color: #444; }
        td { border: 1px solid #eee; padding: 7px; }
        
        /* Filas alternas para mejor lectura */
        tr:nth-child(even) { background-color: #fafafa; }

        /* Pie de página */
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="hospital-name">HOSPITAL REGIONAL SAN JUAN DE DIOS</h1>
        <h2 class="report-title">{{ $titulo }}</h2>
        <p class="meta-info">Documento generado el: {{ date('d/m/Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30%;">Nombre Completo</th>
                <th style="width: 15%;">C.I.</th>
                <th style="width: 15%;">Teléfono</th>
                <th style="width: 25%;">Categoría</th>
                <th style="width: 15%;">Salario</th>
            </tr>
        </thead>
        <tbody>
            @forelse($personal as $p)
                <tr>
                    <td>{{ $p->nombre_completo }}</td>
                    <td>{{ $p->carnet_identidad }}</td>
                    <td>{{ $p->telefono ?? 'N/A' }}</td>
                    <td>{{ $p->user->categoria->nombre ?? 'Sin categoría' }}</td>
                    <td>{{ $p->tipo_salario }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 20px;">No se encontró personal registrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Sistema de Gestión Hospitalaria - Reporte Oficial
    </div>

</body>
</html>