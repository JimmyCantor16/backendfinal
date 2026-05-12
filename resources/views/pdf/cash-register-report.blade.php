<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cierre de Caja #{{ $cash_register->id }}</title>
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
            margin: 0;
        }
        .biz-meta { color: #6b7280; font-size: 10px; }
        .doc-title {
            font-size: 20px;
            font-weight: bold;
            color: #111827;
            margin-top: 6px;
            letter-spacing: 1px;
        }

        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 14px;
        }
        .info-cell {
            display: table-cell;
            padding: 8px 12px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            width: 33%;
            vertical-align: top;
        }
        .info-cell + .info-cell { border-left: 0; }
        .label {
            text-transform: uppercase;
            font-size: 9px;
            color: #6b7280;
            letter-spacing: 1px;
            margin-bottom: 4px;
            font-weight: bold;
        }
        .value { color: #111827; font-size: 12px; }

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

        table.payments {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        table.payments th {
            background: #f3f4f6;
            text-align: left;
            padding: 6px 8px;
            font-size: 10px;
            text-transform: uppercase;
            color: #6b7280;
        }
        table.payments td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .right { text-align: right; }

        .totals-box {
            display: table;
            width: 100%;
            margin: 12px 0;
        }
        .totals-cell {
            display: table-cell;
            width: 50%;
            padding: 12px 16px;
            border-radius: 6px;
            text-align: center;
        }
        .totals-cell.primary { background: #4f46e5; color: #fff; margin-right: 8px; }
        .totals-cell.accent { background: #111827; color: #fff; }
        .totals-label {
            font-size: 10px;
            letter-spacing: 1px;
            text-transform: uppercase;
            opacity: 0.85;
        }
        .totals-value {
            font-size: 22px;
            font-weight: bold;
            margin-top: 4px;
        }

        table.orders {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.orders th {
            background: #4f46e5;
            color: #fff;
            padding: 6px 8px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
        }
        table.orders td {
            padding: 5px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        table.orders tbody tr:nth-child(even) { background: #f9fafb; }

        .empty {
            text-align: center;
            color: #9ca3af;
            padding: 10px;
            font-style: italic;
        }

        .footer {
            margin-top: 18px;
            text-align: center;
            font-size: 9px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
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
        <div class="doc-title">CIERRE DE CAJA #{{ $cash_register->id }}</div>
    </div>

    <div class="info-row">
        <div class="info-cell">
            <div class="label">Operador</div>
            <div class="value">{{ optional($cash_register->user)->name ?? '—' }}</div>
        </div>
        <div class="info-cell">
            <div class="label">Apertura</div>
            <div class="value">{{ optional($cash_register->opened_at)->format('Y-m-d H:i') ?? '—' }}</div>
        </div>
        <div class="info-cell">
            <div class="label">Cierre</div>
            <div class="value">{{ optional($cash_register->closed_at)->format('Y-m-d H:i') ?? 'En curso' }}</div>
        </div>
    </div>

    <h2 class="section">Totales por Método de Pago</h2>
    <table class="payments">
        <thead>
            <tr>
                <th>Método</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Efectivo</td>
                <td class="right">$ {{ number_format((float) ($resumen['total_cash'] ?? 0), 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Tarjeta</td>
                <td class="right">$ {{ number_format((float) ($resumen['total_card'] ?? 0), 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Transferencia</td>
                <td class="right">$ {{ number_format((float) ($resumen['total_transfer'] ?? 0), 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>QR</td>
                <td class="right">$ {{ number_format((float) ($resumen['total_qr'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="totals-box">
        <div class="totals-cell primary">
            <div class="totals-label">Total Ventas</div>
            <div class="totals-value">$ {{ number_format((float) ($resumen['total_ventas'] ?? 0), 2, ',', '.') }}</div>
        </div>
        <div class="totals-cell accent">
            <div class="totals-label">Monto Cierre</div>
            <div class="totals-value">$ {{ number_format((float) ($resumen['monto_cierre'] ?? 0), 2, ',', '.') }}</div>
        </div>
    </div>

    <div class="info-row">
        <div class="info-cell">
            <div class="label">Monto Apertura</div>
            <div class="value">$ {{ number_format((float) ($resumen['monto_apertura'] ?? 0), 2, ',', '.') }}</div>
        </div>
        <div class="info-cell">
            <div class="label">Órdenes Cerradas</div>
            <div class="value">{{ $resumen['ordenes_cerradas'] ?? 0 }}</div>
        </div>
        <div class="info-cell">
            <div class="label">Órdenes Canceladas</div>
            <div class="value">{{ $resumen['ordenes_canceladas'] ?? 0 }}</div>
        </div>
    </div>

    <h2 class="section">Detalle de Órdenes Cerradas</h2>
    @if ($closed_orders->isEmpty())
        <div class="empty">No hay órdenes cerradas en esta caja.</div>
    @else
        <table class="orders">
            <thead>
                <tr>
                    <th>N.º Orden</th>
                    <th>Hora</th>
                    <th>Método</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($closed_orders as $order)
                    <tr>
                        <td>{{ $order->order_number ?? ('ORD-' . $order->id) }}</td>
                        <td>{{ optional($order->closed_at)->format('H:i') ?? '—' }}</td>
                        <td>{{ ucfirst($order->payment_method ?? '—') }}</td>
                        <td class="right">$ {{ number_format((float) $order->total, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($cancelled_orders->isNotEmpty())
        <h2 class="section">Órdenes Canceladas</h2>
        <table class="orders">
            <thead>
                <tr>
                    <th>N.º Orden</th>
                    <th>Hora</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cancelled_orders as $order)
                    <tr>
                        <td>{{ $order->order_number ?? ('ORD-' . $order->id) }}</td>
                        <td>{{ optional($order->updated_at)->format('H:i') ?? '—' }}</td>
                        <td class="right">$ {{ number_format((float) $order->total, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Generado automáticamente el {{ now()->format('Y-m-d H:i:s') }}
    </div>
</div>
</body>
</html>
