<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pedido {{ $order['number'] }}</title>
    <style>
        @page { margin: 11px 12px 15px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 8.2px; line-height: 1.38; }
        .center { text-align: center; }
        .brand-logo { display: block; width: auto; max-width: 112px; height: auto; max-height: 54px; margin: 5px auto 6px; }
        .brand { margin: 7px 0 4px; color: #a93800; font-size: 17px; font-weight: 800; letter-spacing: .035em; line-height: 1.05; }
        .brand.has-logo { margin-top: 2px; color: #526071; font-size: 8px; letter-spacing: .08em; text-transform: uppercase; }
        .brand-subtitle { color: #667085; font-size: 6.7px; letter-spacing: .15em; text-transform: uppercase; }
        .muted { color: #667085; }
        .rule { margin: 9px 0; border-top: 1px dashed #98a2b3; }
        .ticket-title { color: #475467; font-size: 7px; font-weight: bold; letter-spacing: .11em; text-transform: uppercase; }
        .order-number { margin: 2px 0; color: #101828; font-size: 14px; font-weight: 800; }
        .label { color: #667085; font-size: 6.3px; letter-spacing: .08em; text-transform: uppercase; }
        .section { margin: 8px 0 4px; color: #344054; font-size: 7px; font-weight: bold; letter-spacing: .09em; text-transform: uppercase; }
        .meta { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .meta td { padding: 1px 0; vertical-align: top; }
        .meta td:last-child { text-align: right; }
        .delivery-box, .payment-box { margin-top: 7px; padding: 7px 8px; border: 1px solid #e4e7ec; border-radius: 5px; background: #f9fafb; page-break-inside: avoid; }
        .delivery-title, .payment-title { margin-bottom: 4px; color: #344054; font-size: 7px; font-weight: bold; letter-spacing: .07em; text-transform: uppercase; }
        .delivery-row { margin-top: 2px; overflow-wrap: break-word; word-wrap: break-word; }
        .delivery-row strong { color: #101828; }
        .line { margin: 7px 0; page-break-inside: avoid; }
        .line-head { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .line-head td { vertical-align: top; }
        .line-head td:first-child { padding-right: 7px; }
        .line-name { color: #101828; font-size: 8.7px; font-weight: bold; }
        .line-total { width: 54px; text-align: right; white-space: nowrap; color: #101828; font-size: 8.7px; font-weight: bold; }
        .line-detail { margin-top: 1px; color: #667085; font-size: 7px; }
        .line-note { margin-top: 2px; color: #a93800; font-size: 7px; font-style: italic; }
        .totals { width: 100%; border-collapse: collapse; }
        .totals td { padding: 2px 0; }
        .totals td:last-child { text-align: right; color: #101828; font-weight: bold; }
        .delivery-fee td { color: #475467; }
        .total td { padding-top: 6px; border-top: 2px solid #101828; color: #101828; font-size: 12px; font-weight: 800; }
        .payment-box { border-color: #f2d6c5; background: #fff8f3; }
        .payment-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .payment-grid td { vertical-align: top; }
        .payment-grid td:last-child { text-align: right; }
        .payment-status { color: #087a61; font-weight: bold; }
        .payment-help { margin-top: 5px; padding-top: 5px; border-top: 1px solid #f2d6c5; color: #5e4a3f; font-size: 7px; }
        .note { padding: 6px 7px; border-radius: 4px; background: #f2f4f7; color: #475467; font-size: 7.2px; white-space: pre-wrap; }
        .footer { margin-top: 11px; color: #667085; font-size: 6.7px; text-align: center; }
    </style>
</head>
<body>
    <div class="center">
        @if(!empty($company['logo_data_uri']))
            <img class="brand-logo" src="{{ $company['logo_data_uri'] }}" alt="Logo">
        @endif
        <div class="brand{{ !empty($company['logo_data_uri']) ? ' has-logo' : '' }}">
            {{ $company['trade_name'] ?: $company['legal_name'] }}
        </div>
        <div class="brand-subtitle">Pedido digital</div>
        @if(!empty($company['address']))
            <div class="muted">{{ $company['address'] }}@if(!empty($company['city'])), {{ $company['city'] }}@endif</div>
        @elseif(!empty($company['city']))
            <div class="muted">{{ $company['city'] }}</div>
        @endif
        @if(!empty($company['phone']))<div class="muted">WhatsApp: {{ $company['phone'] }}</div>@endif
    </div>

    <div class="rule"></div>
    <div class="center ticket-title">{{ $document_title ?: 'Confirmación de pedido' }}</div>
    <div class="center order-number">{{ $order['number'] }}</div>
    <div class="center muted">{{ $order['date'] }} · {{ $order['time'] }}</div>

    <div class="rule"></div>
    <table class="meta">
        <tr>
            <td><span class="label">Cliente</span><br><strong>{{ $client['name'] }}</strong></td>
            <td><span class="label">Teléfono</span><br><strong>{{ $client['phone'] }}</strong></td>
        </tr>
    </table>

    @if(!empty($fulfillment['type']) || !empty($fulfillment['branch']) || !empty($fulfillment['recipient']) || !empty($fulfillment['address']))
        <div class="delivery-box">
            <div class="delivery-title">Entrega</div>
            @if(!empty($fulfillment['type']))<div class="delivery-row"><span class="label">Modalidad</span> <strong>{{ $fulfillment['type'] }}</strong></div>@endif
            @if(!empty($fulfillment['branch']))<div class="delivery-row"><span class="label">Sucursal</span> <strong>{{ $fulfillment['branch'] }}</strong></div>@endif
            @if(!empty($fulfillment['recipient']))<div class="delivery-row"><span class="label">Recibe</span> <strong>{{ $fulfillment['recipient'] }}</strong></div>@endif
            @if(!empty($fulfillment['address']))<div class="delivery-row"><span class="label">Dirección / referencia</span><br>{{ $fulfillment['address'] }}</div>@endif
        </div>
    @endif

    <div class="rule"></div>
    <div class="section">Detalle del pedido</div>
    @forelse($lines as $line)
        <div class="line">
            <table class="line-head">
                <tr>
                    <td><span class="line-name">{{ $line['quantity'] }} × {{ $line['name'] }}</span></td>
                    <td class="line-total">{{ $currency_symbol }}{{ number_format($line['subtotal'], 2) }}</td>
                </tr>
            </table>
            @if(!empty($line['description']))<div class="line-detail">{{ $line['description'] }}</div>@endif
            @if(!empty($line['line_note']))<div class="line-note">Opción: {{ $line['line_note'] }}</div>@endif
            <div class="line-detail">{{ $currency_symbol }}{{ number_format($line['unit_price'], 2) }} c/u</div>
        </div>
    @empty
        <div class="muted">Sin productos registrados.</div>
    @endforelse

    <div class="rule"></div>
    <table class="totals">
        <tr><td>Subtotal productos</td><td>{{ $currency_symbol }}{{ number_format($totals['subtotal'], 2) }}</td></tr>
        @if($totals['iva'] > 0)
            <tr><td>IVA{{ $totals['prices_include_iva'] ? ' incluido' : '' }} ({{ $totals['iva_rate_percent'] }}%)</td><td>{{ $currency_symbol }}{{ number_format($totals['iva'], 2) }}</td></tr>
        @endif
        @if(($totals['delivery_fee'] ?? 0) > 0)
            <tr class="delivery-fee"><td>Costo de delivery</td><td>{{ $currency_symbol }}{{ number_format($totals['delivery_fee'], 2) }}</td></tr>
        @endif
        <tr class="total"><td>TOTAL</td><td>{{ $currency_symbol }}{{ number_format($totals['total'], 2) }}</td></tr>
    </table>

    <div class="payment-box">
        <div class="payment-title">Información de pago</div>
        <table class="payment-grid">
            <tr>
                <td><span class="label">Forma de pago</span><br><strong>{{ $order['payment_method'] }}</strong></td>
                <td><span class="label">Situación del pago</span><br><span class="payment-status">{{ $order['payment_status_label'] }}</span></td>
            </tr>
        </table>
        <div class="payment-help">{{ $order['payment_explanation'] }}</div>
    </div>

    @if(!empty($order['note']))
        <div class="section">Indicaciones del pedido</div>
        <div class="note">{{ $order['note'] }}</div>
    @endif

    <div class="footer">
        Gracias por elegir {{ $company['trade_name'] ?: $company['legal_name'] }}.<br>
        {{ $legal_footer ?: 'Conserva este comprobante para consultar los datos de tu pedido.' }}
    </div>
</body>
</html>
