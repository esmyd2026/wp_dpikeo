<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $order['number'] }}</title>
    <style>
        @page { margin: 9px 10px 14px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #161616; font-size: 8.5px; line-height: 1.36; }
        .center { text-align: center; }
        .brand { color: #a93800; font-size: 18px; line-height: 1; font-weight: 800; letter-spacing: .04em; margin: 7px 0 4px; }
        .brand-subtitle { color: #555; font-size: 7px; text-transform: uppercase; letter-spacing: .16em; }
        .rule { border-top: 1px dashed #4b4b4b; margin: 9px 0; }
        .ticket-title { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .05em; }
        .order-number { font-size: 13px; font-weight: bold; margin: 3px 0; }
        .muted { color: #5b5b5b; }
        .meta { width: 100%; border-collapse: collapse; }
        .meta td { padding: 1px 0; vertical-align: top; }
        .meta td:last-child { text-align: right; }
        .label { color: #666; text-transform: uppercase; font-size: 6.5px; letter-spacing: .08em; }
        .section { font-weight: bold; text-transform: uppercase; letter-spacing: .08em; font-size: 7px; margin: 8px 0 4px; }
        .line { margin: 7px 0; page-break-inside: avoid; }
        .line-head { width: 100%; border-collapse: collapse; }
        .line-head td { vertical-align: top; }
        .line-name { font-weight: bold; font-size: 9px; }
        .line-total { text-align: right; white-space: nowrap; font-weight: bold; font-size: 9px; }
        .line-detail { color: #555; font-size: 7.2px; margin-top: 1px; }
        .line-note { color: #a93800; font-size: 7px; margin-top: 2px; font-style: italic; }
        .totals { width: 100%; border-collapse: collapse; }
        .totals td { padding: 2px 0; }
        .totals td:last-child { text-align: right; font-weight: bold; }
        .total td { padding-top: 6px; border-top: 2px solid #161616; font-size: 12px; font-weight: bold; }
        .status { display: inline-block; margin-top: 6px; padding: 3px 7px; background: #fff0e6; color: #a93800; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .note { background: #f5f5f5; padding: 6px; border-radius: 3px; color: #444; font-size: 7.4px; white-space: pre-wrap; }
        .footer { margin-top: 11px; text-align: center; color: #555; font-size: 7px; }
    </style>
</head>
<body>
    <div class="center">
        <div class="brand">{{ $company['trade_name'] ?: $company['legal_name'] }}</div>
        <div class="brand-subtitle">Pedido digital</div>
        @if(!empty($company['address']))<div class="muted">{{ $company['address'] }}@if(!empty($company['city'])), {{ $company['city'] }}@endif</div>@endif
        @if(!empty($company['phone']))<div class="muted">WhatsApp: {{ $company['phone'] }}</div>@endif
    </div>

    <div class="rule"></div>
    <div class="center ticket-title">Confirmación de pedido</div>
    <div class="center order-number">{{ $order['number'] }}</div>
    <div class="center muted">{{ $order['date'] }} · {{ $order['time'] }}</div>
    <div class="center"><span class="status">{{ $order['status'] }}</span></div>

    <div class="rule"></div>
    <table class="meta">
        <tr><td><span class="label">Cliente</span><br><strong>{{ $client['name'] }}</strong></td><td><span class="label">Teléfono</span><br><strong>{{ $client['phone'] }}</strong></td></tr>
        @if($client['address'] !== '—')<tr><td colspan="2"><span class="label">Entrega / referencia</span><br>{{ $client['address'] }}</td></tr>@endif
    </table>

    <div class="rule"></div>
    <div class="section">Tu pedido</div>
    @forelse($lines as $line)
        <div class="line">
            <table class="line-head"><tr><td><span class="line-name">{{ $line['quantity'] }} × {{ $line['name'] }}</span></td><td class="line-total">{{ $currency_symbol }}{{ number_format($line['subtotal'], 2) }}</td></tr></table>
            @if(!empty($line['description']))<div class="line-detail">{{ $line['description'] }}</div>@endif
            @if(!empty($line['line_note']))<div class="line-note">{{ $line['line_note'] }}</div>@endif
            <div class="line-detail">{{ $currency_symbol }}{{ number_format($line['unit_price'], 2) }} c/u</div>
        </div>
    @empty
        <div class="muted">Sin productos registrados.</div>
    @endforelse

    <div class="rule"></div>
    <table class="totals">
        <tr><td>Subtotal</td><td>{{ $currency_symbol }}{{ number_format($totals['subtotal'], 2) }}</td></tr>
        @if($totals['iva_rate_percent'] > 0)<tr><td>IVA ({{ $totals['iva_rate_percent'] }}%)</td><td>{{ $currency_symbol }}{{ number_format($totals['iva'], 2) }}</td></tr>@endif
        <tr class="total"><td>TOTAL</td><td>{{ $currency_symbol }}{{ number_format($totals['total'], 2) }}</td></tr>
    </table>

    <div class="rule"></div>
    <table class="meta"><tr><td><span class="label">Pago</span><br><strong>{{ $order['payment_method'] }}</strong></td><td><span class="label">Estado pago</span><br><strong>{{ ucfirst($order['payment_status'] ?: 'pendiente') }}</strong></td></tr></table>
    @if(!empty($order['note']))<div class="section">Indicaciones</div><div class="note">{{ $order['note'] }}</div>@endif
    <div class="footer">Gracias por elegir {{ $company['trade_name'] ?: $company['legal_name'] }}.<br>{{ $legal_footer ?: 'Conserva este ticket para el seguimiento de tu pedido.' }}</div>
</body>
</html>
