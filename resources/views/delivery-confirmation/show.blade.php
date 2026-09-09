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
        .photo-help { margin:.4rem 0 0; color:#64748b; font-size:.74rem; line-height:1.35; }
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
            <p>Confirma la entrega con una foto</p>
        </div>

        @if($state === 'used')
            <div class="status-box success">
                <div style="font-size:2.4rem;">✅</div>
                <p>Esta entrega ya fue confirmada. ¡Gracias!</p>
            </div>
        @elseif($state === 'expired')
            <div class="status-box">
                <div style="font-size:2.4rem;">⏰</div>
                <p>Este link ya venció. Pídele al negocio que te mande uno nuevo.</p>
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
                <p class="photo-help">Las fotos grandes se optimizan automáticamente antes de enviarse.</p>

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
        const photoInput = document.getElementById('photo');

        const MAX_DIRECT_UPLOAD = 1.5 * 1024 * 1024;
        const MAX_IMAGE_SIDE = 1600;
        const WEB_FORMATS = ['image/jpeg', 'image/png', 'image/webp'];

        function canvasBlob(canvas, quality) {
            return new Promise((resolve, reject) => {
                canvas.toBlob(
                    blob => blob ? resolve(blob) : reject(new Error('No se pudo preparar la foto seleccionada.')),
                    'image/jpeg',
                    quality
                );
            });
        }

        async function loadPhoto(file) {
            if ('createImageBitmap' in window) {
                let bitmap;
                try {
                    bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
                } catch (_) {
                    bitmap = await createImageBitmap(file);
                }
                return { source: bitmap, width: bitmap.width, height: bitmap.height, close: () => bitmap.close() };
            }

            const objectUrl = URL.createObjectURL(file);
            const image = new Image();
            try {
                await new Promise((resolve, reject) => {
                    image.onload = resolve;
                    image.onerror = () => reject(new Error('El teléfono no pudo leer la foto seleccionada.'));
                    image.src = objectUrl;
                });
                return { source: image, width: image.naturalWidth, height: image.naturalHeight, close: () => URL.revokeObjectURL(objectUrl) };
            } catch (error) {
                URL.revokeObjectURL(objectUrl);
                throw error;
            }
        }

        async function optimizePhoto(file) {
            if (file.size <= MAX_DIRECT_UPLOAD && WEB_FORMATS.includes(file.type)) return file;

            const decoded = await loadPhoto(file);
            try {
                const scale = Math.min(1, MAX_IMAGE_SIDE / Math.max(decoded.width, decoded.height));
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(decoded.width * scale));
                canvas.height = Math.max(1, Math.round(decoded.height * scale));
                const context = canvas.getContext('2d', { alpha: false });
                if (!context) throw new Error('El teléfono no pudo preparar la foto seleccionada.');
                context.fillStyle = '#fff';
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.drawImage(decoded.source, 0, 0, canvas.width, canvas.height);

                let blob = await canvasBlob(canvas, .82);
                if (blob.size > MAX_DIRECT_UPLOAD) blob = await canvasBlob(canvas, .68);
                if (blob.size > MAX_DIRECT_UPLOAD) blob = await canvasBlob(canvas, .55);
                return blob;
            } finally {
                decoded.close();
            }
        }

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            errorBox.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.textContent = 'Enviando…';

            try {
                const originalPhoto = photoInput.files?.[0];
                if (!originalPhoto) throw new Error('Debes seleccionar una foto de la entrega.');

                submitBtn.textContent = 'Preparando foto…';
                const preparedPhoto = await optimizePhoto(originalPhoto);
                const formData = new FormData(form);
                const cleanName = (originalPhoto.name || 'entrega').replace(/\.[^.]+$/, '').replace(/[^a-zA-Z0-9_-]+/g, '-');
                formData.set('photo', preparedPhoto, cleanName + '.jpg');
                submitBtn.textContent = 'Enviando…';

                const res = await fetch(submitUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: formData,
                });
                const responseText = await res.text();
                let data = {};
                try { data = responseText ? JSON.parse(responseText) : {}; } catch (_) {}
                if (res.status === 413) throw new Error('La foto sigue siendo demasiado pesada para el servidor. Intenta tomarla con menor resolución.');
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
