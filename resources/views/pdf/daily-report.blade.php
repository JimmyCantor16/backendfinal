<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Diario {{ $date->format('Y-m-d') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 0;
        }
        .page { padding: 24px 28px; }

        .header {
            border-bottom: 3px solid #4f46e5;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }
        .biz-name {
            font-size: 16px;
            font-weight: bold;
            color: #4f46e5;
        }
        .biz-meta { color: #6b7280; font-size: 10px; }
        .doc-title {
            font-size: 22px;
            font-weight: bold;
            color: #111827;
            margin-top: 6px;
            letter-spacing: 1px;
        }
        .doc-date {
            font-size: 12px;
            color: #4f46e5;
            margin-top: 2px;
        }

        h2.section {
            font-size: 12px;
            color: #4f46e5;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin-top: 18px;
            margin-bottom: 8px;
        }

        .kpis {
            display: table;
            width: 100%;
            border-spacing: 8px 0;
            margin-bottom: 14px;
        }
        .kpi {
            display: table-cell;
            background: #4f46e5;
            color: #fff;
            padding: 14px 16px;
            border-radius: 6px;
            text-align: center;
            width: 33%;
        }
        .kpi.accent { background: #111827; }
        .kpi.muted { background: #6b7280; }
        .kpi-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.85;
        }
        .kpi-value {
            font-size: 22px;
            font-weight: bold;
            margin-top: 4px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        table.data th {
            background: #f3f4f6;
            color: #6b7280;
            text-align: left;
            padding: 6px 8px;
            font-size: 10px;
            text-transform: uppercase;
        }
        table.data td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        table.data tbody tr:nth-child(even) { background: #f9fafb; }
        .right { text-align: right; }
        .center { text-align: center; }

        .empty {
            text-align: center;
            color: #9ca3af;
            padding: 10px;
            font-style: italic;
        }

        .footer {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 9px;
            color: #6b7280;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <div class="biz-name">{{ $business->name ?? 'Mi Negocio' }}</div>
        <div class="biz-meta">
            @if ($business && !empty($business->nit)) NIT: {{ $business->nit }} &nbsp;·&nbsp; @endif
            @if ($business && !empty($business->address)) {{ $business->address }} @endif
        </div>
        <div class="doc-title">REPORTE DIARIO</div>
        <div class="doc-date">{{ $date->format('l, d \d\e F \d\e Y') }}</div>
    </div>

    <h2 class="section">Resumen General</h2>
    <div class="kpis">
        <div class="kpi">
            <div class="kpi-label">Ventas Totales</div>
            <div class="kpi-value">$ {{ number_format((float) $ventas_totales, 2, ',', '.') }}</div>
        </div>
        <div class="kpi accent">
            <div class="kpi-label">Facturas</div>
            <div class="kpi-value">{{ $facturas['total'] ?? 0 }}</div>
        </div>
        <div class="kpi muted">
            <div class="kpi-label">Órdenes POS</div>
            <div class="kpi-value">{{ $ordenes_pos['total'] ?? 0 }}</div>
        </div>
    </div>

    <h2 class="section">Distribución por Método de Pago (POS)</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Método</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Efectivo</td>
                <td class="right">$ {{ number_format((float) ($ordenes_pos['efectivo'] ?? 0), 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Tarjeta</td>
                <td class="right">$ {{ number_format((float) ($ordenes_pos['tarjeta'] ?? 0), 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Transferencia</td>
                <td class="right">$ {{ number_format((float) ($ordenes_pos['transferencia'] ?? 0), 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>QR</td>
                <td class="right">$ {{ number_format((float) ($ordenes_pos['qr'] ?? 0), 2, ',', '.') }}</td>
            </tr>
            <tr style="font-weight: bold;">
                <td>Subtotal POS</td>
                <td class="right">$ {{ number_format((float) ($ordenes_pos['ventas'] ?? 0), 2, ',', '.') }}</td>
            </tr>
            <tr style="font-weight: bold;">
                <td>Subtotal Facturación</td>
                <td class="right">$ {{ number_format((float) ($facturas['ventas'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <h2 class="section">Top 5 Productos Vendidos</h2>
    @if (empty($top_products))
        <div class="empty">No hay ventas registradas para este día.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th class="center" style="width: 40px;">#</th>
                    <th>Producto</th>
                    <th>SKU</th>
                    <th class="right" style="width: 90px;">Unidades</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($top_products as $idx => $row)
                    <tr>
                        <td class="center">{{ $idx + 1 }}</td>
                        <td>{{ $row['product']->name ?? '—' }}</td>
                        <td>{{ $row['product']->sku ?? '—' }}</td>
                        <td class="right"><strong>{{ $row['quantity'] }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Reporte generado el {{ $generated_at->format('Y-m-d H:i:s') }}
        &nbsp;·&nbsp; Sistema POS · Jamz
    </div>
</div>
</body>
</html>
