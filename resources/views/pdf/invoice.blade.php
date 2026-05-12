<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura {{ $invoice->invoice_number ?? $invoice->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        .page { padding: 24px 28px; }
        .header {
            border-bottom: 3px solid #4f46e5;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .biz-name {
            font-size: 20px;
            font-weight: bold;
            color: #4f46e5;
            margin: 0 0 4px 0;
        }
        .biz-meta { color: #6b7280; font-size: 10px; line-height: 1.5; }
        .doc-title {
            text-align: right;
            font-size: 22px;
            font-weight: bold;
            color: #111827;
            letter-spacing: 1px;
        }
        .doc-number {
            text-align: right;
            color: #4f46e5;
            font-size: 14px;
            font-weight: bold;
        }
        .logo {
            max-height: 60px;
            max-width: 140px;
        }

        .boxes {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin-bottom: 18px;
        }
        .box {
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            padding: 10px 12px;
            border-radius: 4px;
        }
        .box-title {
            text-transform: uppercase;
            font-size: 9px;
            color: #6b7280;
            letter-spacing: 1px;
            margin-bottom: 6px;
            font-weight: bold;
        }
        .box-line { line-height: 1.45; }
        .box-line strong { color: #111827; }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.items thead th {
            background: #4f46e5;
            color: #fff;
            padding: 8px 10px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table.items tbody td {
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 10px;
        }
        table.items .right { text-align: right; }
        table.items .center { text-align: center; }

        .totals {
            width: 320px;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals td {
            padding: 6px 10px;
        }
        .totals tr.subtotal td { color: #4b5563; }
        .totals tr.iva td { color: #4b5563; border-top: 1px dashed #e5e7eb; }
        .totals tr.total td {
            background: #4f46e5;
            color: #fff;
            font-size: 16px;
            font-weight: bold;
        }
        .totals .right { text-align: right; }

        .footer {
            margin-top: 28px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 10px;
        }
        .footer .thanks {
            font-size: 13px;
            color: #4f46e5;
            font-weight: bold;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
<div class="page">
    @php
        $business = $invoice->business ?? null;
        $client = $invoice->client ?? null;
        $logoPath = null;
        if ($business && !empty($business->logo)) {
            $candidate = storage_path('app/public/' . ltrim($business->logo, '/'));
            if (file_exists($candidate)) {
                $logoPath = $candidate;
            }
        }
        $subtotal = $invoice->subtotal ?? 0;
        $iva = $invoice->iva ?? 0;
        $ivaRate = $invoice->iva_rate ?? 19;
        $total = $invoice->total ?? 0;
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 60%;">
                    @if ($logoPath)
                        <img src="{{ $logoPath }}" class="logo" alt="logo">
                    @endif
                    <div class="biz-name">{{ $business->name ?? 'Mi Negocio' }}</div>
                    <div class="biz-meta">
                        @if ($business && !empty($business->nit))
                            NIT: {{ $business->nit }}<br>
                        @endif
                        @if ($business && !empty($business->address))
                            {{ $business->address }}<br>
                        @endif
                        @if ($business && !empty($business->phone))
                            Tel: {{ $business->phone }}
                        @endif
                        @if ($business && !empty($business->email))
                            &nbsp;·&nbsp; {{ $business->email }}
                        @endif
                    </div>
                </td>
                <td style="width: 40%;">
                    <div class="doc-title">FACTURA</div>
                    <div class="doc-number">N.º {{ $invoice->invoice_number ?? $invoice->id }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="boxes">
        <tr>
            <td style="width: 50%; padding: 0;">
                <div class="box">
                    <div class="box-title">Cliente</div>
                    @if ($client)
                        <div class="box-line"><strong>{{ $client->name ?? 'Sin nombre' }}</strong></div>
                        @if (!empty($client->document_number))
                            <div class="box-line">{{ $client->document_type ?? 'ID' }}: {{ $client->document_number }}</div>
                        @endif
                        @if (!empty($client->email))
                            <div class="box-line">{{ $client->email }}</div>
                        @endif
                        @if (!empty($client->phone))
                            <div class="box-line">Tel: {{ $client->phone }}</div>
                        @endif
                    @else
                        <div class="box-line"><em>Venta general (consumidor final)</em></div>
                    @endif
                </div>
            </td>
            <td style="width: 50%; padding: 0;">
                <div class="box">
                    <div class="box-title">Factura</div>
                    <div class="box-line"><strong>N.º:</strong> {{ $invoice->invoice_number ?? $invoice->id }}</div>
                    <div class="box-line"><strong>Fecha:</strong> {{ optional($invoice->created_at)->format('Y-m-d H:i') }}</div>
                    <div class="box-line"><strong>Método de pago:</strong> {{ ucfirst($invoice->payment_method ?? 'N/D') }}</div>
                    <div class="box-line"><strong>Estado:</strong> {{ strtoupper($invoice->status ?? 'N/D') }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="center" style="width: 60px;">Cant.</th>
                <th>Descripción</th>
                <th class="right" style="width: 110px;">Precio Unit.</th>
                <th class="right" style="width: 110px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->items as $item)
                <tr>
                    <td class="center">{{ $item->quantity }}</td>
                    <td>
                        {{ $item->product->name ?? 'Producto eliminado' }}
                        @if (!empty($item->product->sku))
                            <br><span style="color:#9ca3af; font-size: 9px;">SKU: {{ $item->product->sku }}</span>
                        @endif
                    </td>
                    <td class="right">$ {{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                    <td class="right">$ {{ number_format((float) $item->subtotal, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align:center; padding: 16px; color:#9ca3af;">
                        Sin items en esta factura.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr class="subtotal">
            <td>Subtotal</td>
            <td class="right">$ {{ number_format((float) $subtotal, 2, ',', '.') }}</td>
        </tr>
        <tr class="iva">
            <td>IVA ({{ number_format((float) $ivaRate, 0) }}%)</td>
            <td class="right">$ {{ number_format((float) $iva, 2, ',', '.') }}</td>
        </tr>
        <tr class="total">
            <td>TOTAL</td>
            <td class="right">$ {{ number_format((float) $total, 2, ',', '.') }}</td>
        </tr>
    </table>

    @if (!empty($invoice->notes))
        <div style="margin-top: 16px; padding: 8px 12px; background: #fef3c7; border-left: 3px solid #f59e0b; font-size: 10px;">
            <strong>Notas:</strong> {{ $invoice->notes }}
        </div>
    @endif

    <div class="footer">
        <div class="thanks">Gracias por su compra</div>
        <div>
            Documento generado automáticamente el {{ now()->format('Y-m-d H:i') }}
            &nbsp;·&nbsp; Folio interno: #{{ str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT) }}
        </div>
    </div>
</div>
</body>
</html>
