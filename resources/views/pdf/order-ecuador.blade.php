<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $document_title }} {{ $order['number'] }}</title>
    <style>
        @page { margin: 28px 32px 36px 32px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1f2937;
            line-height: 1.45;
        }
        .accent-bar {
            height: 4px;
            background: #128c7e;
            margin-bottom: 18px;
        }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .header-table td { vertical-align: top; }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #075e54;
            margin: 0 0 4px;
        }
        .company-meta { font-size: 9px; color: #4b5563; line-height: 1.5; }
        .doc-box {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px 12px;
            text-align: right;
            background: #f8fafc;
        }
        .doc-title {
            font-size: 14px;
            font-weight: bold;
            color: #075e54;
            letter-spacing: 0.04em;
            margin: 0 0 2px;
        }
        .doc-subtitle { font-size: 8px; color: #6b7280; margin: 0 0 8px; }
        .doc-number { font-size: 12px; font-weight: bold; margin: 0 0 6px; }
        .doc-meta { font-size: 9px; color: #374151; }
        .section-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #075e54;
            margin: 0 0 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .info-grid td {
            width: 50%;
            vertical-align: top;
            padding-right: 10px;
        }
        .info-box {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
            min-height: 88px;
        }
        .info-row { margin-bottom: 4px; }
        .info-label {
            display: inline-block;
            width: 92px;
            color: #6b7280;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .info-value { font-size: 9.5px; font-weight: bold; color: #111827; }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 12px;
        }
        .items-table thead th {
            background: #075e54;
            color: #fff;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 7px 6px;
            text-align: left;
        }
        .items-table thead th.num,
        .items-table tbody td.num,
        .items-table tbody td.qty,
        .items-table tbody td.money { text-align: right; }
        .items-table tbody td {
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 6px;
            vertical-align: top;
            font-size: 9px;
        }
        .items-table tbody tr:nth-child(even) td { background: #fafafa; }
        .item-name { font-weight: bold; color: #111827; margin-bottom: 2px; }
        .item-desc { color: #4b5563; font-size: 8px; margin-top: 2px; }
        .item-measure {
            display: inline-block;
            margin-top: 3px;
            font-size: 7.5px;
            color: #047857;
            background: #ecfdf5;
            padding: 2px 5px;
            border-radius: 999px;
        }
        .item-note {
            margin-top: 3px;
            font-size: 7.5px;
            color: #92400e;
            font-style: italic;
        }
        .bottom-table { width: 100%; border-collapse: collapse; }
        .bottom-table td { vertical-align: top; }
        .notes-box {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
            min-height: 90px;
        }
        .notes-text { font-size: 9px; color: #374151; white-space: pre-wrap; }
        .totals-box {
            width: 240px;
            margin-left: auto;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            overflow: hidden;
        }
        .totals-row {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-row td {
            padding: 6px 10px;
            font-size: 9px;
            border-bottom: 1px solid #e5e7eb;
        }
        .totals-row td:last-child { text-align: right; font-weight: bold; }
        .totals-row.grand td {
            background: #075e54;
            color: #fff;
            font-size: 11px;
            font-weight: bold;
            border-bottom: none;
        }
        .footer {
            position: fixed;
            bottom: -8px;
            left: 0;
            right: 0;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
            font-size: 7.5px;
            color: #6b7280;
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e;
            font-size: 8px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="accent-bar"></div>

    <table class="header-table">
        <tr>
            <td style="width:58%">
                <div class="company-name">{{ $company['legal_name'] }}</div>
                @if(!empty($company['trade_name']) && $company['trade_name'] !== $company['legal_name'])
                    <div class="company-meta"><strong>Nombre comercial:</strong> {{ $company['trade_name'] }}</div>
                @endif
                @if(!empty($company['ruc']))
                    <div class="company-meta"><strong>RUC:</strong> {{ $company['ruc'] }}</div>
                @endif
                @if(!empty($company['address']))
                    <div class="company-meta"><strong>Dirección:</strong> {{ $company['address'] }}@if(!empty($company['city'])), {{ $company['city'] }}@endif</div>
                @endif
                @if(!empty($company['phone']))
                    <div class="company-meta"><strong>Tel.:</strong> {{ $company['phone'] }}</div>
                @endif
                @if(!empty($company['email']))
                    <div class="company-meta"><strong>Email:</strong> {{ $company['email'] }}</div>
                @endif
                @if(!empty($company['website']))
                    <div class="company-meta"><strong>Web:</strong> {{ $company['website'] }}</div>
                @endif
            </td>
            <td style="width:42%">
                <div class="doc-box">
                    <div class="doc-title">{{ $document_title }}</div>
                    @if(!empty($document_subtitle))
                        <div class="doc-subtitle">{{ $document_subtitle }}</div>
                    @endif
                    <div class="doc-number">No. {{ $order['number'] }}</div>
                    <div class="doc-meta"><strong>Fecha:</strong> {{ $order['date'] }}</div>
                    <div class="doc-meta"><strong>Hora:</strong> {{ $order['time'] }}</div>
                    <div class="doc-meta"><strong>Estado:</strong> {{ $order['status'] }}</div>
                    @if($order['requires_invoice'])
                        <div class="doc-meta" style="margin-top:4px;"><span class="badge">Solicita factura</span></div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="info-grid">
        <tr>
            <td>
                <div class="section-title">Datos del cliente</div>
                <div class="info-box">
                    <div class="info-row">
                        <span class="info-label">Cliente</span>
                        <span class="info-value">{{ $client['name'] }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">{{ $client['identification_type'] }}</span>
                        <span class="info-value">{{ $client['identification'] }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Teléfono</span>
                        <span class="info-value">{{ $client['phone'] }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Dirección</span>
                        <span class="info-value">{{ $client['address'] }}</span>
                    </div>
                </div>
            </td>
            <td>
                <div class="section-title">Condiciones del pedido</div>
                <div class="info-box">
                    <div class="info-row">
                        <span class="info-label">Forma pago</span>
                        <span class="info-value">{{ $order['payment_method'] }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Moneda</span>
                        <span class="info-value">Dólares (USD)</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Referencia</span>
                        <span class="info-value">ID interno #{{ $order['id'] }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">País</span>
                        <span class="info-value">Ecuador</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-title">Detalle de productos / servicios</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:4%" class="num">#</th>
                <th style="width:10%">Código</th>
                <th style="width:38%">Descripción</th>
                <th style="width:8%" class="qty">Cant.</th>
                <th style="width:12%" class="money">P. unit.</th>
                <th style="width:12%" class="money">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
                <tr>
                    <td class="num">{{ $line['index'] }}</td>
                    <td>{{ $line['sku'] }}</td>
                    <td>
                        <div class="item-name">{{ $line['name'] }}</div>
                        @if(!empty($line['description']))
                            <div class="item-desc">{{ $line['description'] }}</div>
                        @endif
                        @if(!empty($line['measurements']))
                            <span class="item-measure">{{ $line['measurements'] }}</span>
                        @endif
                        @if(!empty($line['line_note']))
                            <div class="item-note">Nota: {{ $line['line_note'] }}</div>
                        @endif
                    </td>
                    <td class="qty">{{ number_format($line['quantity'], 0) }}</td>
                    <td class="money">{{ $currency_symbol }}{{ number_format($line['unit_price'], 2) }}</td>
                    <td class="money">{{ $currency_symbol }}{{ number_format($line['subtotal'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:#6b7280;padding:16px;">Sin productos registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="bottom-table">
        <tr>
            <td style="width:55%;padding-right:12px;">
                <div class="section-title">Observaciones</div>
                <div class="notes-box">
                    @if(!empty($order['note']))
                        <div class="notes-text">{{ $order['note'] }}</div>
                    @else
                        <div class="notes-text" style="color:#9ca3af;">Sin observaciones adicionales.</div>
                    @endif
                </div>
            </td>
            <td style="width:45%">
                <div class="totals-box">
                    <table class="totals-row">
                        <tr>
                            <td>Subtotal {{ $totals['prices_include_iva'] ? '(incl. base imponible)' : '(sin IVA)' }}</td>
                            <td>{{ $currency_symbol }}{{ number_format($totals['subtotal'], 2) }}</td>
                        </tr>
                        @if($totals['iva_rate_percent'] > 0)
                            <tr>
                                <td>IVA ({{ $totals['iva_rate_percent'] }}%)</td>
                                <td>{{ $currency_symbol }}{{ number_format($totals['iva'], 2) }}</td>
                            </tr>
                        @endif
                        <tr class="grand">
                            <td>TOTAL USD</td>
                            <td>{{ $currency_symbol }}{{ number_format($totals['total'], 2) }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $legal_footer }}
    </div>
</body>
</html>
