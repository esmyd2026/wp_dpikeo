@php
    $demoNumber = preg_replace('/[^0-9]/', '', config('pricing.demo.whatsapp_number', config('whatsapp.demo_whatsapp_number', '')));
    $demoMessage = rawurlencode(config('pricing.demo.whatsapp_message', '¡Hola! Quiero probar el demo del bot de WhatsApp 🤖'));
    $demoWhatsappUrl = $demoNumber ? "https://wa.me/{$demoNumber}?text={$demoMessage}" : null;
    $demoPanelUser = config('pricing.demo.panel_user', 'gosorio');
    $demoPanelPassword = config('pricing.demo.panel_password', 'go123');
    $isDemoLogin = request()->boolean('demo');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Ingreso — DPIKEOS</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #0b141a;
            color: #e9edef;
        }
        .login-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        @media (max-width: 900px) {
            .login-shell { grid-template-columns: 1fr; }
            .login-brand { display: none; }
        }
        .login-brand {
            background: linear-gradient(160deg, #111b21 0%, #075e54 55%, #128c7e 100%);
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .login-brand::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M60 0H0v60' fill='none' stroke='%23ffffff' stroke-opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
        }
        .brand-top { position: relative; z-index: 1; }
        .brand-logo {
            display: flex;
            align-items: center;
            gap: .85rem;
            margin-bottom: 2.5rem;
        }
        .brand-logo-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: rgba(255,255,255,.12);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: #25d366;
        }
        .brand-logo-text {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -.02em;
        }
        .brand-logo-text span { color: #25d366; }
        .brand-headline {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.25;
            max-width: 420px;
            margin: 0 0 1rem;
        }
        .brand-sub {
            color: rgba(233,237,239,.78);
            font-size: 1rem;
            line-height: 1.6;
            max-width: 400px;
        }
        .brand-features {
            position: relative;
            z-index: 1;
            display: grid;
            gap: .75rem;
            margin-top: 2rem;
        }
        .brand-feature {
            display: flex;
            align-items: center;
            gap: .65rem;
            font-size: .88rem;
            color: rgba(233,237,239,.85);
        }
        .brand-feature i { color: #25d366; width: 18px; text-align: center; }
        .brand-pricing-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .6rem;
            margin-top: 2rem;
            padding: .85rem 1.4rem;
            border-radius: 12px;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.28);
            color: #fff;
            font-size: .92rem;
            font-weight: 600;
            text-decoration: none;
            backdrop-filter: blur(6px);
            transition: background .15s, border-color .15s, transform .12s, box-shadow .15s;
            box-shadow: 0 4px 20px rgba(0,0,0,.15);
        }
        .brand-pricing-btn:hover {
            background: rgba(255,255,255,.18);
            border-color: #25d366;
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(0,0,0,.22);
        }
        .brand-pricing-btn i { color: #25d366; font-size: 1rem; }
        .brand-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: .65rem;
            margin-top: 2rem;
        }
        .brand-actions .brand-pricing-btn { margin-top: 0; }
        .brand-resumen-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .55rem;
            padding: .75rem 1.25rem;
            border-radius: 12px;
            background: transparent;
            border: 1px dashed rgba(255,255,255,.35);
            color: rgba(233,237,239,.92);
            font-size: .86rem;
            font-weight: 600;
            text-decoration: none;
            transition: background .15s, border-color .15s, color .15s, transform .12s;
        }
        .brand-resumen-btn:hover {
            background: rgba(37, 211, 102, .1);
            border-color: #25d366;
            color: #fff;
            transform: translateY(-1px);
        }
        .brand-resumen-btn i { color: #53bdeb; font-size: .95rem; }
        .login-resumen-link {
            display: none;
            margin-top: 1rem;
            text-align: center;
        }
        .login-resumen-link a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            font-size: .84rem;
            font-weight: 600;
            color: #128c7e;
            text-decoration: none;
            padding: .55rem .85rem;
            border-radius: 8px;
            border: 1px solid #d1fae5;
            background: #f0fdf4;
            transition: background .15s, border-color .15s;
        }
        .login-resumen-link a:hover {
            background: #dcfce7;
            border-color: #86efac;
        }
        .brand-footer {
            position: relative;
            z-index: 1;
            font-size: .78rem;
            color: rgba(233,237,239,.45);
        }
        .login-panel {
            background: #f4f6f9;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 40px rgba(17, 27, 33, .12);
            border: 1px solid #e8ecf1;
            overflow: hidden;
        }
        .login-card-header {
            padding: 2rem 2rem 1.25rem;
            border-bottom: 1px solid #eef1f4;
        }
        .login-card-header h1 {
            margin: 0 0 .35rem;
            font-size: 1.45rem;
            font-weight: 700;
            color: #111b21;
        }
        .login-card-header p {
            margin: 0;
            color: #667781;
            font-size: .9rem;
        }
        .login-card-body { padding: 1.5rem 2rem 2rem; }
        .form-group { margin-bottom: 1.15rem; }
        .form-label {
            display: block;
            font-size: .82rem;
            font-weight: 600;
            color: #3b4a54;
            margin-bottom: .4rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .input-wrap { position: relative; }
        .input-wrap > i.field-icon {
            position: absolute;
            left: .9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #8696a0;
            font-size: .9rem;
            pointer-events: none;
        }
        .form-control {
            width: 100%;
            padding: .75rem 2.75rem .75rem 2.5rem;
            border: 1px solid #d1d7db;
            border-radius: 10px;
            font-size: .95rem;
            color: #111b21;
            background: #fff;
            transition: border-color .15s, box-shadow .15s;
        }
        .form-control.no-toggle { padding-right: .9rem; }
        .form-control:focus {
            outline: none;
            border-color: #128c7e;
            box-shadow: 0 0 0 3px rgba(18, 140, 126, .12);
        }
        .form-control.is-invalid { border-color: #dc3545; }
        .toggle-password {
            position: absolute;
            right: .65rem;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #8696a0;
            cursor: pointer;
            padding: .35rem;
            border-radius: 6px;
            line-height: 1;
            transition: color .15s, background .15s;
        }
        .toggle-password:hover {
            color: #128c7e;
            background: rgba(18, 140, 126, .08);
        }
        .invalid-feedback {
            color: #dc3545;
            font-size: .82rem;
            margin-top: .35rem;
        }
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: .75rem 1rem;
            font-size: .85rem;
            color: #b91c1c;
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: .5rem;
        }
        .btn-login {
            width: 100%;
            padding: .85rem 1rem;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #128c7e, #075e54);
            color: #fff;
            font-size: .95rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform .12s, box-shadow .12s;
            box-shadow: 0 4px 14px rgba(7, 94, 84, .25);
        }
        .btn-login:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(7, 94, 84, .32);
        }
        .btn-login:disabled {
            opacity: .65;
            cursor: not-allowed;
        }
        .btn-demo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            width: 100%;
            margin-top: .85rem;
            padding: .8rem 1rem;
            border-radius: 10px;
            border: 2px solid #25d366;
            background: #fff;
            color: #075e54;
            font-size: .92rem;
            font-weight: 600;
            text-decoration: none;
            transition: background .15s, color .15s, transform .12s;
        }
        .btn-demo:hover {
            background: #25d366;
            color: #fff;
            transform: translateY(-1px);
        }
        .btn-demo i { font-size: 1.1rem; }
        .btn-demo-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            width: 100%;
            margin-top: .65rem;
            padding: .8rem 1rem;
            border-radius: 10px;
            border: 2px solid #128c7e;
            background: #fff;
            color: #075e54;
            font-size: .92rem;
            font-weight: 600;
            text-decoration: none;
            transition: background .15s, color .15s, transform .12s;
        }
        .btn-demo-panel:hover {
            background: #128c7e;
            color: #fff;
            transform: translateY(-1px);
        }
        .btn-pricing {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            width: 100%;
            margin-top: 1rem;
            padding: .8rem 1rem;
            border-radius: 10px;
            border: 2px solid #111b21;
            background: #111b21;
            color: #fff;
            font-size: .92rem;
            font-weight: 600;
            text-decoration: none;
            transition: background .15s, color .15s, transform .12s, box-shadow .15s;
            box-shadow: 0 4px 14px rgba(17, 27, 33, .18);
        }
        .btn-pricing:hover {
            background: #075e54;
            border-color: #075e54;
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(7, 94, 84, .28);
        }
        .btn-pricing i { font-size: .95rem; opacity: .9; }
        .demo-panel-box {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 10px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            font-size: .85rem;
            color: #166534;
        }
        .demo-panel-box strong {
            font-family: ui-monospace, monospace;
            color: #14532d;
        }
        .demo-panel-box .demo-title {
            font-weight: 700;
            margin-bottom: .5rem;
            display: flex;
            align-items: center;
            gap: .4rem;
        }
        .demo-actions-login {
            display: grid;
            gap: .65rem;
        }
        .login-divider {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin: 1.25rem 0 .25rem;
            color: #8696a0;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .login-divider::before,
        .login-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e8ecf1;
        }
        .login-footer-note {
            margin-top: 1.25rem;
            text-align: center;
            font-size: .78rem;
            color: #8696a0;
        }
        .login-footer-note i { color: #128c7e; }
        .hp-field {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
            opacity: 0;
            pointer-events: none;
        }
        .mobile-brand {
            display: none;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 900px) {
            .mobile-brand { display: block; }
            .mobile-brand .brand-logo { justify-content: center; margin-bottom: 0; }
            .login-resumen-link { display: block; }
        }
    </style>
</head>
<body>
    <div class="login-shell">
        <aside class="login-brand">
            <div class="brand-top">
                <div class="brand-logo">
                    <div class="brand-logo-icon"><img src="{{ asset('storage/img/dpikeologo.jpg') }}" alt="DPIKEOS" style="width:34px;height:34px;object-fit:contain;border-radius:8px"></div>
                    <div class="brand-logo-text"><span>DPI</span>KEOS</div>
                </div>
                                <h2 class="brand-headline">Operación y pedidos DPIKEOS</h2>
                <p class="brand-sub">La operación central de DPIKEOS: pedidos, cocina, sucursales y atención por WhatsApp, en un solo lugar.</p>
                <div class="brand-features">
                    <div class="brand-feature"><i class="fas fa-utensils"></i> Menú visual y pedidos rápidos</div>
                    <div class="brand-feature"><i class="fas fa-fire-burner"></i> Comandas y control de cocina</div>
                    <div class="brand-feature"><i class="fas fa-chart-line"></i> Ventas, inventario y reportes</div>
                    <div class="brand-feature"><i class="fas fa-shield-halved"></i> Acceso seguro solo para tu equipo</div>
                </div>
            </div>
            <div class="brand-footer">&copy; {{ date('Y') }} DPIKEOS · Uso exclusivo de personal autorizado</div>
        </aside>

        <main class="login-panel">
            <div style="width:100%;max-width:420px">
                <div class="mobile-brand">
                    <div class="brand-logo">
                        <div class="brand-logo-icon"><img src="{{ asset('storage/img/dpikeologo.jpg') }}" alt="DPIKEOS" style="width:34px;height:34px;object-fit:contain;border-radius:8px"></div>
                        <div class="brand-logo-text" style="color:#111b21"><span style="color:#e85d04">DPI</span>KEOS</div>
                    </div>
                </div>

                <div class="login-card">
                    <div class="login-card-header">
                        <h1>Iniciar sesión</h1>
                        <p>Accede al panel de administración de tu agencia de ventas.</p>
                    </div>
                    <div class="login-card-body">
                        <form id="login-form" action="{{ route('login') }}" method="POST" autocomplete="off" novalidate>
                            @csrf

                            {{-- Honeypot anti-bot: los bots suelen rellenar este campo oculto --}}
                            <div class="hp-field" aria-hidden="true">
                                <label for="company_website">Sitio web de la empresa</label>
                                <input
                                    type="text"
                                    id="company_website"
                                    name="company_website"
                                    tabindex="-1"
                                    autocomplete="off"
                                    value=""
                                >
                            </div>

                            <input type="hidden" name="_form_loaded_at" id="_form_loaded_at" value="{{ time() }}">

                            @if ($errors->any() && !$errors->has('username') && !$errors->has('password'))
                                <div class="alert-error">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>
                                        @foreach ($errors->all() as $error)
                                            {{ $error }}
                                        @endforeach
                                    </span>
                                </div>
                            @endif

                            <div class="form-group">
                                <label for="username" class="form-label">Usuario</label>
                                <div class="input-wrap">
                                    <i class="fas fa-user field-icon"></i>
                                    <input
                                        id="username"
                                        name="username"
                                        type="text"
                                        class="form-control no-toggle @error('username') is-invalid @enderror"
                                        value="{{ old('username') }}"
                                        placeholder="Ej. admin"
                                        required
                                        autofocus
                                        autocomplete="username"
                                        maxlength="60"
                                        autocapitalize="none"
                                        spellcheck="false"
                                    >
                                </div>
                                @error('username')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="password" class="form-label">Contraseña</label>
                                <div class="input-wrap">
                                    <i class="fas fa-lock field-icon"></i>
                                    <input
                                        id="password"
                                        name="password"
                                        type="password"
                                        class="form-control @error('password') is-invalid @enderror"
                                        placeholder="••••••••"
                                        required
                                        autocomplete="current-password"
                                        maxlength="255"
                                    >
                                    <button
                                        type="button"
                                        class="toggle-password"
                                        id="toggle-password"
                                        aria-label="Mostrar contraseña"
                                        title="Mostrar contraseña"
                                    >
                                        <i class="fas fa-eye" id="toggle-password-icon"></i>
                                    </button>
                                </div>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn-login" id="submit-btn">
                                <i class="fas fa-arrow-right-to-bracket"></i> Acceder al panel
                            </button>
                        </form>
                        <p class="login-footer-note">
                            <i class="fas fa-lock"></i> Conexión cifrada · Protección anti-bots activa
                        </p>
<br>
                        <div class="login-divider">Prueba la demo</div>

                        <div class="demo-actions-login">
                            @if ($demoWhatsappUrl)
                                <a
                                    href="{{ $demoWhatsappUrl }}"
                                    class="btn-demo"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <i class="fab fa-whatsapp"></i>
                                    Probar bot en WhatsApp
                                </a>
                            @endif

                            @if ($isDemoLogin)
                                <div class="demo-panel-box">
                                    <div class="demo-title"><i class="fas fa-desktop"></i> Credenciales demo del panel</div>
                                    <div>Usuario: <strong>{{ $demoPanelUser }}</strong></div>
                                    <div>Contraseña: <strong>{{ $demoPanelPassword }}</strong></div>
                                    <div style="margin-top:.5rem;font-size:.8rem;opacity:.85">Los campos ya están completados — pulsa «Acceder al panel».</div>
                                </div>
                            @else
                                <a href="{{ route('login', ['demo' => 1]) }}" class="btn-demo-panel">
                                    <i class="fas fa-desktop"></i>
                                    Demo del panel ({{ $demoPanelUser }})
                                </a>
                            @endif
                        </div>

                        <div class="login-resumen-link">
                            <a href="{{ route('public.resumen') }}" target="_blank" rel="noopener noreferrer">
                                <i class="fas fa-layer-group"></i>
                                Ver resumen de la plataforma
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        (function () {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.getElementById('toggle-password');
            const toggleIcon = document.getElementById('toggle-password-icon');
            const form = document.getElementById('login-form');
            const submitBtn = document.getElementById('submit-btn');
            const loadedAtField = document.getElementById('_form_loaded_at');
            const usernameInput = document.getElementById('username');
            const isDemoLogin = @json($isDemoLogin);
            const demoUser = @json($demoPanelUser);
            const demoPass = @json($demoPanelPassword);

            if (isDemoLogin && usernameInput) {
                usernameInput.value = demoUser;
                if (passwordInput) {
                    passwordInput.value = demoPass;
                }
            }

            if (loadedAtField && !loadedAtField.value) {
                loadedAtField.value = Math.floor(Date.now() / 1000);
            }

            if (toggleBtn && passwordInput) {
                toggleBtn.addEventListener('click', function () {
                    const isHidden = passwordInput.type === 'password';
                    passwordInput.type = isHidden ? 'text' : 'password';
                    toggleIcon.classList.toggle('fa-eye', !isHidden);
                    toggleIcon.classList.toggle('fa-eye-slash', isHidden);
                    toggleBtn.setAttribute('aria-label', isHidden ? 'Ocultar contraseña' : 'Mostrar contraseña');
                    toggleBtn.setAttribute('title', isHidden ? 'Ocultar contraseña' : 'Mostrar contraseña');
                });
            }

            if (form) {
                form.addEventListener('submit', function () {
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
                    }
                });
            }
        })();
    </script>
</body>
</html>
