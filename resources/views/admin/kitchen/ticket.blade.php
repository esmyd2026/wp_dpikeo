<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comanda {{ $order['number'] }}</title>
    <style>
        @page { margin:4mm; size:80mm auto; }
        * { box-sizing:border-box; }
        body { width:72mm; margin:0 auto; color:#111; background:#fff; font-family:"Courier New",monospace; font-size:11px; font-weight:600; }
        .ticket { padding:3mm 0; }
        .brand { padding-bottom:3mm; border-bottom:2px solid #111; text-align:center; }
        .brand img { width:32px; height:32px; margin-bottom:2px; object-fit:cover; border-radius:6px; vertical-align:middle; }
        .brand strong { display:block; font-family:Arial,sans-serif; font-size:20px; letter-spacing:2px; }
        .brand span { font-size:10px; letter-spacing:1px; }
        .order { padding:3mm 0; border-bottom:1px dashed #111; text-align:center; }
        .order strong { display:block; font-family:Arial,sans-serif; font-size:29px; letter-spacing:1px; }
        .order small { display:block; margin-top:2px; font-size:10px; }
        .section { padding:2.5mm 0; border-bottom:1px dashed #111; }
        .section-title { margin-bottom:1.4mm; font-size:10px; font-weight:900; letter-spacing:.7px; text-transform:uppercase; }
        .customer-line { margin-top:1mm; line-height:1.35; }
        .items { margin:0; padding:2mm 0; list-style:none; border-bottom:1px dashed #111; }
        .items li { display:grid; grid-template-columns:11mm 1fr; gap:2mm; padding:2.2mm 0; }
        .qty { font-size:13px; font-weight:900; }
        .name { font-family:Arial,sans-serif; font-size:12px; font-weight:800; }
        .note { display:block; margin-top:1mm; color:#333; font-size:10px; font-style:italic; line-height:1.35; }
        .note b { font-style:normal; }
        .footer { padding-top:3mm; text-align:center; font-size:10px; }
        .print-hint { position:fixed; right:12px; bottom:12px; border:0; border-radius:8px; padding:9px 12px; background:#111; color:#fff; cursor:pointer; font:inherit; }
        @media print { .print-hint { display:none; } }
    </style>
</head>
<body>
    <main class="ticket">
        <header class="brand"><strong>{{ $activeCompany?->name ?? 'Cocina' }}</strong><span>COMANDA DE COCINA</span></header>
        <section class="order"><strong>TURNO {{ $order['turn_number'] }}</strong><small>{{ $order['branch'] }}</small><small>Ref. {{ $order['number'] }}</small><small>{{ $order['created_at'] ? \Carbon\Carbon::parse($order['created_at'])->format('d/m/Y · h:i a') : now()->format('d/m/Y · h:i a') }}</small><small>{{ $order['status_label'] }}</small></section>
        <section class="section">
            <div class="section-title">Datos del cliente</div>
            <div class="customer-line">
                <b>{{ $order['customer']['name'] }}</b>
                @if (!empty($order['customer']['phone']))
                    <br>Tel. {{ $order['customer']['phone'] }}
                @endif
                @if (!empty($order['customer']['address']))
                    <br>{{ $order['customer']['address'] }}
                @endif
            </div>
        </section>
        <ul class="items">
            @foreach($order['items'] as $item)
                <li>
                    <span class="qty">{{ $item['quantity'] }}×</span>
                    <span>
                        <span class="name">{{ $item['name'] }}</span>
                        @if (!empty($item['note']))
                            <span class="note"><b>Adicionales:</b> {{ $item['note'] }}</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
        @if (!empty($order['order_note']))
            <section class="section">
                <div class="section-title">Observación</div>
                {{ $order['order_note'] }}
            </section>
        @endif
        <footer class="footer">Preparar con cuidado · {{ $order['elapsed_label'] }}</footer>
    </main>
    <button class="print-hint" type="button" onclick="window.print()">Imprimir</button>
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
</body>
</html>
