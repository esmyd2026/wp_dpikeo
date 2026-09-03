<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $businessName }} — Pide por WhatsApp</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --accent: {{ $landing['accent_color'] }};
            --accent-rgb: {{ $accentRgb['r'] }}, {{ $accentRgb['g'] }}, {{ $accentRgb['b'] }};
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #0b141a;
            color: #e9edef;
        }

        /* Navbar */
        .nav {
            position: sticky;
            top: 0;
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.75rem;
            background: rgba(11, 20, 26, .82);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,.06);
        }
        .nav-brand { display: flex; align-items: center; gap: .65rem; text-decoration: none; }
        .nav-brand img { width: 34px; height: 34px; object-fit: contain; border-radius: 9px; }
        .nav-brand span { font-weight: 800; color: #fff; font-size: 1.1rem; letter-spacing: -.02em; }
        .nav-brand span em { color: var(--accent); font-style: normal; }
        .nav-links { display: flex; align-items: center; gap: 1.5rem; }
        .nav-link {
            color: rgba(233,237,239,.75);
            text-decoration: none;
            font-size: .88rem;
            font-weight: 600;
            transition: color .15s;
        }
        .nav-link:hover { color: #fff; }
        .nav-login {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .55rem 1.1rem;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.22);
            color: #fff;
            text-decoration: none;
            font-size: .85rem;
            font-weight: 700;
            transition: background .15s, border-color .15s, transform .12s;
        }
        .nav-login:hover { background: rgba(255,255,255,.08); border-color: var(--accent); transform: translateY(-1px); }
        .nav-toggle { display: none; background: none; border: 0; color: #fff; font-size: 1.3rem; cursor: pointer; }

        @media (max-width: 720px) {
            .nav-links { position: fixed; inset: 64px 0 auto 0; flex-direction: column; align-items: stretch; gap: 0; background: #0b141a; border-bottom: 1px solid rgba(255,255,255,.08); padding: .5rem 1.75rem 1.25rem; transform: translateY(-8px); opacity: 0; pointer-events: none; transition: opacity .15s, transform .15s; }
            .nav-links.open { transform: translateY(0); opacity: 1; pointer-events: auto; }
            .nav-links .nav-link { padding: .75rem 0; border-bottom: 1px solid rgba(255,255,255,.06); }
            .nav-login { justify-content: center; margin-top: .75rem; }
            .nav-toggle { display: block; }
        }

        /* Hero */
        .hero {
            position: relative;
            overflow: hidden;
            padding: 5rem 1.75rem 6rem;
            background: radial-gradient(circle at 20% 15%, rgba(var(--accent-rgb),.14), transparent 45%),
                        linear-gradient(160deg, #111b21 0%, #0b141a 55%, #075e54 130%);
        }
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M60 0H0v60' fill='none' stroke='%23ffffff' stroke-opacity='0.035'/%3E%3C/svg%3E");
            pointer-events: none;
        }
        .hero-inner {
            position: relative;
            z-index: 1;
            max-width: 1180px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.05fr .95fr;
            gap: 3rem;
            align-items: center;
        }
        @media (max-width: 900px) { .hero-inner { grid-template-columns: 1fr; } }
        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .4rem .9rem;
            border-radius: 999px;
            background: rgba(var(--accent-rgb),.12);
            border: 1px solid rgba(var(--accent-rgb),.35);
            color: var(--accent);
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .03em;
            text-transform: uppercase;
            margin-bottom: 1.25rem;
        }
        .hero h1 {
            margin: 0 0 1rem;
            font-size: clamp(2.1rem, 4.2vw, 3.1rem);
            font-weight: 800;
            line-height: 1.12;
            letter-spacing: -.02em;
            color: #fff;
        }
        .hero h1 em { color: var(--accent); font-style: normal; }
        .hero p.lead {
            margin: 0 0 2rem;
            font-size: 1.05rem;
            line-height: 1.65;
            color: rgba(233,237,239,.78);
            max-width: 480px;
        }
        .hero-actions { display: flex; flex-wrap: wrap; gap: .9rem; }
        .btn-cta {
            display: inline-flex;
            align-items: center;
            gap: .65rem;
            padding: 1rem 1.6rem;
            border-radius: 14px;
            background: var(--accent);
            color: #06170f;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 800;
            box-shadow: 0 10px 30px rgba(var(--accent-rgb),.3);
            transition: transform .15s, box-shadow .15s;
        }
        .btn-cta:hover { transform: translateY(-2px); box-shadow: 0 14px 36px rgba(var(--accent-rgb),.4); }
        .btn-cta i { font-size: 1.2rem; }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: .6rem;
            padding: 1rem 1.5rem;
            border-radius: 14px;
            background: transparent;
            border: 1px solid rgba(255,255,255,.25);
            color: #fff;
            text-decoration: none;
            font-size: .95rem;
            font-weight: 700;
            transition: background .15s, border-color .15s, transform .15s;
        }
        .btn-secondary:hover { background: rgba(255,255,255,.06); border-color: rgba(255,255,255,.45); transform: translateY(-2px); }
        .hero-trust { margin-top: 2rem; display: flex; align-items: center; gap: .6rem; color: rgba(233,237,239,.55); font-size: .8rem; }
        .hero-trust i { color: var(--accent); }

        /* Phone mockup */
        .phone {
            position: relative;
            max-width: 320px;
            margin: 0 auto;
            background: #111b21;
            border: 8px solid #1b262c;
            border-radius: 34px;
            box-shadow: 0 30px 70px rgba(0,0,0,.45);
            overflow: hidden;
        }
        .phone-header {
            background: #202c33;
            padding: .9rem 1rem;
            display: flex;
            align-items: center;
            gap: .6rem;
        }
        .phone-header .avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #128c7e);
            display: flex; align-items: center; justify-content: center; color: #06170f; font-weight: 800;
        }
        .phone-header .who { font-size: .85rem; color: #fff; font-weight: 700; }
        .phone-header .who small { display: block; font-weight: 400; color: rgba(233,237,239,.55); font-size: .7rem; }
        .phone-body {
            padding: 1rem .85rem;
            display: flex;
            flex-direction: column;
            gap: .55rem;
            min-height: 360px;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M60 0H0v60' fill='none' stroke='%23ffffff' stroke-opacity='0.03'/%3E%3C/svg%3E");
        }
        .bubble {
            max-width: 82%;
            padding: .55rem .75rem;
            border-radius: 12px;
            font-size: .8rem;
            line-height: 1.45;
        }
        .bubble.in { align-self: flex-start; background: #202c33; color: #e9edef; border-top-left-radius: 3px; }
        .bubble.out { align-self: flex-end; background: #005c4b; color: #e9edef; border-top-right-radius: 3px; }
        .bubble b { color: var(--accent); }
        .bubble.btns { display: flex; flex-direction: column; gap: .4rem; padding: 0; background: transparent; }
        .bubble.btns span { background: #202c33; color: #53bdeb; text-align: center; padding: .5rem; border-radius: 8px; font-weight: 700; }

        /* Sections */
        .section { padding: 4.5rem 1.75rem; max-width: 1080px; margin: 0 auto; }
        .section-head { text-align: center; max-width: 560px; margin: 0 auto 2.75rem; }
        .section-head .kicker { color: var(--accent); font-weight: 700; font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; margin-bottom: .6rem; }
        .section-head h2 { margin: 0 0 .6rem; font-size: 1.9rem; font-weight: 800; color: #fff; letter-spacing: -.02em; }
        .section-head p { margin: 0; color: rgba(233,237,239,.65); font-size: .95rem; }

        .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
        @media (max-width: 800px) { .steps { grid-template-columns: 1fr; } }
        .step-card {
            background: #111b21;
            border: 1px solid rgba(255,255,255,.06);
            border-radius: 18px;
            padding: 1.75rem 1.5rem;
        }
        .step-num {
            width: 40px; height: 40px; border-radius: 12px;
            background: rgba(var(--accent-rgb),.12);
            border: 1px solid rgba(var(--accent-rgb),.35);
            color: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; margin-bottom: 1rem;
        }
        .step-card h3 { margin: 0 0 .5rem; font-size: 1.05rem; font-weight: 700; color: #fff; }
        .step-card p { margin: 0; font-size: .85rem; color: rgba(233,237,239,.62); line-height: 1.55; }

        .cta-band {
            margin: 0 1.75rem 4.5rem;
            max-width: 1080px;
            margin-left: auto; margin-right: auto;
            background: linear-gradient(120deg, #075e54, #128c7e 60%, var(--accent));
            border-radius: 24px;
            padding: 3rem 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
            flex-wrap: wrap;
        }
        .cta-band h3 { margin: 0 0 .4rem; font-size: 1.5rem; font-weight: 800; color: #fff; }
        .cta-band p { margin: 0; color: rgba(255,255,255,.85); font-size: .92rem; max-width: 420px; }
        .cta-band .btn-cta { background: #fff; color: #075e54; box-shadow: 0 10px 30px rgba(0,0,0,.2); }

        footer.site-footer {
            border-top: 1px solid rgba(255,255,255,.06);
            padding: 2.5rem 1.75rem;
        }
        .footer-inner {
            max-width: 1080px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .footer-links { display: flex; gap: 1.5rem; }
        .footer-links a { color: rgba(233,237,239,.6); text-decoration: none; font-size: .82rem; }
        .footer-links a:hover { color: #fff; }
        .footer-copy { color: rgba(233,237,239,.4); font-size: .78rem; }
    </style>
</head>
<body>
    <nav class="nav">
        <a href="{{ url('/') }}" class="nav-brand">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $businessName }}">
            @endif
            <span><em>{{ substr($businessName, 0, 3) }}</em>{{ substr($businessName, 3) }}</span>
        </a>
        <button type="button" class="nav-toggle" id="navToggle" aria-label="Abrir menú"><i class="fas fa-bars"></i></button>
        <div class="nav-links" id="navLinks">
            <a href="#como-funciona" class="nav-link">Cómo funciona</a>
            <a href="{{ route('legal.privacy') }}" class="nav-link">Privacidad</a>
            <a href="{{ route('login') }}" class="nav-login"><i class="fas fa-arrow-right-to-bracket"></i> Iniciar sesión</a>
        </div>
    </nav>

    <header class="hero">
        <div class="hero-inner">
            <div>
                <div class="hero-eyebrow"><i class="fab fa-whatsapp"></i> {{ $landing['hero_eyebrow'] }}</div>
                <h1>{!! str_replace($landing['hero_title_highlight'], '<em>'.$landing['hero_title_highlight'].'</em>', e($landing['hero_title'])) !!}</h1>
                <p class="lead">{{ $landing['hero_subtitle'] }}</p>
                <div class="hero-actions">
                    @if($orderWhatsappUrl)
                        <a href="{{ $orderWhatsappUrl }}" class="btn-cta" target="_blank" rel="noopener noreferrer">
                            <i class="fab fa-whatsapp"></i> {{ $landing['order_cta_label'] }}
                        </a>
                    @endif
                    <a href="#como-funciona" class="btn-secondary">
                        <i class="fas fa-circle-play"></i> Ver cómo funciona
                    </a>
                </div>
                <div class="hero-trust"><i class="fas fa-shield-halved"></i> Tus datos están protegidos según la <a href="{{ route('legal.privacy') }}" style="color:inherit;text-decoration:underline">política de privacidad</a>.</div>
            </div>

            <div class="phone">
                <div class="phone-header">
                    <div class="avatar">{{ substr($businessName, 0, 1) }}</div>
                    <div class="who">{{ $businessName }} <small>en línea</small></div>
                </div>
                <div class="phone-body">
                    <div class="bubble in">{{ $landing['phone_msg_1'] }}</div>
                    <div class="bubble out">{{ $landing['phone_msg_2'] }}</div>
                    <div class="bubble in">{{ $landing['phone_msg_3'] }}</div>
                    <div class="bubble btns">
                        <span>{{ $landing['phone_btn_1'] }}</span>
                        <span>{{ $landing['phone_btn_2'] }}</span>
                    </div>
                    <div class="bubble in">{{ $landing['phone_msg_4'] }}</div>
                </div>
            </div>
        </div>
    </header>

    @if($canOrderOnline)
        <section class="section" id="ordenar-online" style="padding-top:0">
            <div class="cta-band" style="margin:0">
                <div>
                    <h3>{{ $landing['order_online_title'] }}</h3>
                    <p>{{ $landing['order_online_text'] }}</p>
                </div>
                <a href="{{ route('landing.start-order') }}" class="btn-cta">
                    <i class="fas fa-cart-shopping"></i> {{ $landing['order_online_cta_label'] }}
                </a>
            </div>
        </section>
    @endif

    <section class="section" id="como-funciona">
        <div class="section-head">
            <div class="kicker">Así de simple</div>
            <h2>Tres pasos y listo</h2>
            <p>No necesitas crear una cuenta ni instalar nada — todo pasa dentro de WhatsApp.</p>
        </div>
        <div class="steps">
            <div class="step-card">
                <div class="step-num">1</div>
                <h3>{{ $landing['step_1_title'] }}</h3>
                <p>{{ $landing['step_1_text'] }}</p>
            </div>
            <div class="step-card">
                <div class="step-num">2</div>
                <h3>{{ $landing['step_2_title'] }}</h3>
                <p>{{ $landing['step_2_text'] }}</p>
            </div>
            <div class="step-card">
                <div class="step-num">3</div>
                <h3>{{ $landing['step_3_title'] }}</h3>
                <p>{{ $landing['step_3_text'] }}</p>
            </div>
        </div>
    </section>

    <div class="cta-band">
        <div>
            <h3>{{ $landing['cta_band_title'] }}</h3>
            <p>{{ $landing['cta_band_subtitle'] }}</p>
        </div>
        @if($orderWhatsappUrl)
            <a href="{{ $orderWhatsappUrl }}" class="btn-cta" target="_blank" rel="noopener noreferrer">
                <i class="fab fa-whatsapp"></i> {{ $landing['order_cta_label'] }}
            </a>
        @endif
    </div>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-copy">&copy; {{ date('Y') }} {{ $businessName }} · Todos los derechos reservados</div>
            <div class="footer-links">
                <a href="{{ route('legal.privacy') }}">Privacidad</a>
                <a href="{{ route('login') }}">Iniciar sesión</a>
            </div>
        </div>
    </footer>

    <script>
        document.getElementById('navToggle')?.addEventListener('click', function () {
            document.getElementById('navLinks')?.classList.toggle('open');
        });
    </script>
</body>
</html>
