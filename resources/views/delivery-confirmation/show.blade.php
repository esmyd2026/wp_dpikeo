<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#a21caf">
    <title>Confirmar entrega — {{ $orderNumber }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; background:#f8fafc; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:#0f172a; }
        .wrap { max-width:420px; margin:0 auto; padding:1.25rem 1rem 3rem; }
        .hero { text-align:center; margin-bottom:1.25rem; }
        .hero h1 { font-size:1.15rem; margin:.4rem 0 0; }
        .hero p { color:#64748b; font-size:.85rem; margin:.2rem 0 0; }
        .card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1rem; margin-bottom:1rem; }
        .card .lbl { display:block; font-size:.66rem; text-transform:uppercase; letter-spacing:.03em; color:#64748b; font-weight:700; }
        .card .val { display:block; color:#0f172a; font-weight:600; margin:.1rem 0 .7rem; }
        .card .val:last-child { margin-bottom:0; }
        label { display:block; font-size:.82rem; font-weight:700; color:#475569; margin-bottom:.35rem; margin-top:.9rem; }
        input[type="file"], textarea { width:100%; border:1px solid #e2e8f0; border-radius:10px; padding:.6rem .7rem; font-size:.85rem; font-family:inherit; }
        textarea { resize:vertical; }
        button { width:100%; margin-top:1.1rem; padding:.8rem; border-radius:10px; border:none; background:linear-gradient(135deg,#a21caf,#701a75); color:#fff; font-size:.95rem; font-weight:800; cursor:pointer; }
        button:disabled { opacity:.6; cursor:not-allowed; }
        .error { color:#b91c1c; font-size:.82rem; margin-top:.6rem; display:none; }
        .status-box { text-align:center; padding:2rem 1rem; background:#fff; border:1px solid #e2e8f0; border-radius:14px; }
        .status-box.success i { color:#16a34a; }
        .status-box i { font-size:2.4rem; color:#94a3b8; }
        .status-box p { color:#64748b; font-size:.9rem; margin-top:.6rem; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="hero">
            <h1>🛵 Pedido {{ $orderNumber }}</h1>
            <p>Confirmá la entrega con una foto</p>
        </div>

        @if($state === 'used')
            <div class="status-box success">
                <div style="font-size:2.4rem;">✅</div>
                <p>Esta entrega ya fue confirmada. ¡Gracias!</p>
            </div>
        @elseif($state === 'expired')
            <div class="status-box">
                <div style="font-size:2.4rem;">⏰</div>
                <p>Este link ya venció. Pedile al negocio que te mande uno nuevo.</p>
            </div>
        @else
            <div class="card">
                @if($branchName)
                    <span class="lbl">Sucursal</span>
                    <span class="val">{{ $branchName }}</span>
                @endif
                <span class="lbl">Entregar a</span>
                <span class="val">{{ $recipientName }}</span>
                @if($address)
                    <span class="lbl">Dirección</span>
                    <span class="val">{{ $address }}</span>
                @endif
                <span class="lbl">Pago</span>
                <span class="val">{{ $paymentLabel }}</span>
            </div>

            <form id="confirmForm">
                <label for="photo">Foto de la entrega (obligatoria)</label>
                <input type="file" id="photo" name="photo" accept="image/*" capture="environment" required>

                <label for="note">Nota (opcional)</label>
                <textarea id="note" name="note" rows="2" placeholder="Ej: Entregado en portería, recibió el guardia."></textarea>

                <div class="error" id="formError"></div>

                <button type="submit" id="submitBtn">Confirmar entrega</button>
            </form>
        @endif
    </div>

    @if($state === 'form')
    <script>
    (function () {
        const submitUrl = @json($submitUrl);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const form = document.getElementById('confirmForm');
        const errorBox = document.getElementById('formError');
        const submitBtn = document.getElementById('submitBtn');

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            errorBox.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.textContent = 'Enviando…';

            try {
                const res = await fetch(submitUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: new FormData(form),
                });
                const data = await res.json();
                if (!res.ok || !data.ok) throw new Error(data.message || 'No se pudo confirmar la entrega.');

                document.querySelector('.wrap').innerHTML = `
                    <div class="hero"><h1>🛵 Pedido {{ $orderNumber }}</h1></div>
                    <div class="status-box success">
                        <div style="font-size:2.4rem;">✅</div>
                        <p>${data.message}</p>
                    </div>`;
            } catch (error) {
                errorBox.textContent = error.message;
                errorBox.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Confirmar entrega';
            }
        });
    })();
    </script>
    @endif
</body>
</html>
