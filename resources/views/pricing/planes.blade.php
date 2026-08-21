<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Planes — ChatbotVentas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0e0d;
            --surface: #111916;
            --surface-2: #1a2420;
            --surface2: #1a2420;
            --border: rgba(37, 211, 102, .14);
            --text: #f0f4f2;
            --muted: #8b9a94;
            --green: #25d366;
            --green-dark: #128c7e;
            --accent: #25d366;
            --accent-dim: rgba(37, 211, 102, .1);
            --blue: #53bdeb;
            --purple: #a78bfa;
            --gold: #fb923c;
            --orange: #fb923c;
            --red: #f15c6d;
            --radius: 16px;
            --shadow: 0 20px 50px rgba(0, 0, 0, .4);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            font-family: Inter, system-ui, sans-serif;
            background: var(--bg);
            background-image: radial-gradient(ellipse 80% 55% at 50% -8%, rgba(37, 211, 102, .16), transparent 55%);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Topbar — coherente con /resumen */
        .topbar {
            position: sticky; top: 0; z-index: 200;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            padding: 14px 28px;
            background: rgba(10, 14, 13, .88);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border);
        }
        .topbar-brand { text-decoration: none; color: inherit; }
        .brand { font-weight: 800; font-size: 1.05rem; letter-spacing: -.02em; }
        .brand span { color: var(--green); }
        .brand-sub { font-size: .62rem; text-transform: uppercase; letter-spacing: .14em; color: var(--muted); font-weight: 600; }
        .topbar-nav { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .topbar-link {
            padding: 7px 14px; border-radius: 999px; font-size: .78rem; font-weight: 600;
            text-decoration: none; color: var(--muted); border: 1px solid transparent; transition: .2s;
        }
        .topbar-link:hover { color: var(--green); border-color: var(--border); background: rgba(37, 211, 102, .06); }
        .topbar-link.active { color: var(--green); border-color: var(--green); background: rgba(37, 211, 102, .1); }
        .topbar-link.primary {
            background: var(--green); color: #0a0e0d !important; border-color: var(--green);
        }
        .topbar-link.primary:hover { filter: brightness(1.08); }

        .wrap {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 48px 24px calc(100px + env(safe-area-inset-bottom, 0px));
        }

        /* Header */
        .hero {
            text-align: center;
            margin-bottom: 40px;
            padding: 24px 0 8px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: rgba(37, 211, 102, .08);
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--green);
            margin-bottom: 20px;
        }

        .badge::before {
            content: '';
            width: 7px; height: 7px; border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 10px var(--green);
        }

        .hero h1 {
            font-size: clamp(1.75rem, 4vw, 2.6rem);
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -.03em;
            margin-bottom: 16px;
            max-width: 780px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero h1 em {
            font-style: normal;
            color: var(--green);
        }

        .hero > p {
            color: var(--muted);
            font-size: 1.05rem;
            max-width: 640px;
            margin: 0 auto 24px;
            line-height: 1.55;
        }

        .urgency-banner {
            max-width: 680px; margin: 0 auto 24px;
            padding: 14px 18px; border-radius: var(--radius);
            background: linear-gradient(135deg, rgba(251, 146, 60, .12), rgba(239, 68, 68, .08));
            border: 1px solid rgba(251, 146, 60, .35);
            text-align: left;
        }
        .urgency-banner p { font-size: .86rem; color: #fcd9b6; line-height: 1.5; margin: 0; }
        .urgency-banner strong { color: var(--orange); }

        /* Billing toggle */
        .billing-toggle {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 6px;
        }

        .billing-toggle button {
            background: none;
            border: none;
            color: var(--muted);
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 999px;
            cursor: pointer;
            transition: all .25s;
        }

        .billing-toggle button.active {
            background: var(--accent);
            color: #0a0e0d;
        }

        .save-tag {
            font-size: 12px;
            color: var(--gold);
            font-weight: 600;
        }

        /* View tabs */
        .view-tabs {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 40px 0 32px;
            flex-wrap: wrap;
        }

        .view-tabs button {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--muted);
            font-family: inherit;
            font-size: .78rem;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 999px;
            cursor: pointer;
            transition: .2s;
        }

        .view-tabs button.active {
            color: var(--green);
            border-color: var(--green);
            background: rgba(37, 211, 102, .1);
        }

        .view-tabs button:hover:not(.active) {
            color: var(--text);
            border-color: rgba(37, 211, 102, .25);
        }

        /* Plans grid */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            align-items: stretch;
        }

        @media (max-width: 960px) {
            .plans-grid { grid-template-columns: 1fr; max-width: 420px; margin: 0 auto; }
        }

        .plan-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 32px 28px;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: transform .3s, box-shadow .3s, border-color .3s;
            cursor: pointer;
        }

        .plan-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
        }

        .plan-card.selected {
            border-color: var(--accent);
            box-shadow: 0 0 0 1px var(--accent), var(--shadow);
        }

        .plan-card.featured {
            border-color: rgba(37,211,102,.4);
            background: linear-gradient(180deg, rgba(37,211,102,.06) 0%, var(--surface) 40%);
        }

        .plan-card.featured::before {
            content: 'Más popular';
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--accent);
            color: #0a0e0d;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: 4px 14px;
            border-radius: 999px;
        }

        .plan-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 20px;
        }

        .plan-card[data-plan="starter"] .plan-icon { background: rgba(59,130,246,.15); }
        .plan-card[data-plan="pro"] .plan-icon { background: rgba(37,211,102,.15); }
        .plan-card[data-plan="enterprise"] .plan-icon { background: rgba(168,85,247,.15); }

        .plan-name {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .plan-title {
            font-size: 1.65rem;
            font-weight: 800;
            letter-spacing: -.02em;
            margin-bottom: 6px;
        }

        .plan-desc {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 24px;
            min-height: 44px;
        }

        .plan-price {
            margin-bottom: 8px;
        }

        .plan-price .amount {
            font-size: 2.75rem;
            font-weight: 700;
            line-height: 1;
        }

        .plan-price .currency {
            font-size: 1.25rem;
            font-weight: 600;
            vertical-align: super;
            margin-right: 2px;
        }

        .plan-price .period {
            font-size: 14px;
            color: var(--muted);
            font-weight: 500;
        }

        .plan-price .from-label {
            font-size: 13px;
            color: var(--muted);
            display: block;
            margin-bottom: 4px;
        }

        .plan-tax-note {
            display: block;
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
            font-weight: 500;
        }

        .annual-note {
            font-size: 12px;
            color: var(--gold);
            margin-bottom: 24px;
            min-height: 18px;
        }

        .plan-cta {
            width: 100%;
            padding: 14px 20px;
            border-radius: 10px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all .2s;
            margin-bottom: 28px;
        }

        .plan-card[data-plan="starter"] .plan-cta {
            background: var(--surface-2);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .plan-card[data-plan="pro"] .plan-cta {
            background: var(--accent);
            color: #0a0e0d;
        }

        .plan-card[data-plan="enterprise"] .plan-cta {
            background: linear-gradient(135deg, #a855f7, #6366f1);
            color: #fff;
        }

        .plan-cta:hover { filter: brightness(1.08); transform: scale(1.01); }

        .features-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--muted);
            margin-bottom: 14px;
        }

        .feature-list li.feature-group {
            padding: 12px 0 4px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--accent);
            border-bottom: none;
        }

        .feature-list li.feature-group:not(:first-child) {
            margin-top: 6px;
            padding-top: 14px;
            border-top: 1px solid rgba(255,255,255,.06);
        }

        .feature-list li.feature-group.muted {
            color: var(--muted);
        }

        .feature-list {
            list-style: none;
            flex: 1;
        }

        .feature-list li {
            position: relative;
            padding: 7px 0 7px 28px;
            font-size: 14px;
            line-height: 1.45;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,.04);
            overflow: hidden;
        }

        .feature-list li:last-child { border-bottom: none; }

        .feature-list .icon {
            position: absolute;
            left: 0;
            top: 9px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
        }

        .feature-list .icon.yes { background: rgba(37,211,102,.2); color: var(--accent); }
        .feature-list .icon.no { background: rgba(241,92,109,.15); color: var(--red); }
        .feature-list .icon.partial { background: rgba(245,158,11,.15); color: var(--gold); }

        .feature-list li.excluded { color: var(--muted); }

        .feature-list .tag {
            float: right;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 8px;
            white-space: nowrap;
        }

        .tag-limited { background: rgba(245,158,11,.15); color: var(--gold); }
        .tag-full { background: rgba(37,211,102,.15); color: var(--accent); }

        /* Comparison table */
        .comparison-panel {
            display: none;
            animation: fadeIn .4s ease;
        }

        .comparison-panel.active { display: block; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .comparison-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--surface);
        }

        table.comparison {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        table.comparison th,
        table.comparison td {
            padding: 14px 18px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        table.comparison th {
            background: var(--surface-2);
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        table.comparison th:first-child,
        table.comparison td:first-child {
            min-width: 240px;
            color: var(--muted);
        }

        table.comparison td:not(:first-child) { text-align: center; }

        table.comparison tr:last-child td { border-bottom: none; }

        table.comparison tr:hover td { background: rgba(255,255,255,.02); }

        .cell-yes { color: var(--accent); font-weight: 600; }
        .cell-no { color: var(--red); opacity: .7; }
        .cell-partial { color: var(--gold); font-weight: 500; }

        .category-row td {
            background: rgba(255,255,255,.03) !important;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted) !important;
        }

        /* Selected plan summary */
        .selection-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(10, 14, 13, .95);
            backdrop-filter: blur(14px);
            border-top: 1px solid var(--border);
            padding: 16px 24px;
            transform: translateY(100%);
            transition: transform .35s cubic-bezier(.4,0,.2,1);
            z-index: 100;
        }

        .selection-bar.visible { transform: translateY(0); }

        .selection-bar-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .selection-info strong { color: var(--accent); }

        .selection-bar button {
            background: var(--accent);
            color: #0a0e0d;
            border: none;
            font-family: inherit;
            font-weight: 700;
            padding: 12px 28px;
            border-radius: 10px;
            cursor: pointer;
        }

        .selection-bar .close-sel {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
        }

        /* FAQ */
        .faq {
            margin-top: 64px;
        }

        .faq h2 {
            font-size: clamp(1.2rem, 3vw, 1.55rem);
            font-weight: 800;
            letter-spacing: -.02em;
            text-align: center;
            margin-bottom: 32px;
        }

        .faq-item {
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 10px;
            overflow: hidden;
            background: var(--surface);
        }

        .faq-q {
            width: 100%;
            background: none;
            border: none;
            color: var(--text);
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            text-align: left;
            padding: 18px 22px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .faq-q:hover { background: rgba(255,255,255,.02); }

        .faq-q .arrow {
            transition: transform .25s;
            color: var(--muted);
            font-size: 12px;
        }

        .faq-item.open .faq-q .arrow { transform: rotate(180deg); }

        .faq-a {
            max-height: 0;
            overflow: hidden;
            transition: max-height .35s ease;
        }

        .faq-item.open .faq-a { max-height: 600px; }

        .faq-a p,
        .faq-a ul {
            padding: 0 22px 18px;
            color: var(--muted);
            font-size: 14px;
        }

        .faq-a ul {
            padding-left: 40px;
            margin-top: -8px;
        }

        .faq-a li { margin-bottom: 6px; }

        .faq-a p:last-child,
        .faq-a ul:last-child { padding-bottom: 18px; }

        /* Costos y riesgos */
        .info-sections {
            margin-top: 64px;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        .info-sections > h2 {
            font-size: clamp(1.2rem, 3vw, 1.55rem);
            font-weight: 800;
            letter-spacing: -.02em;
            text-align: center;
            margin-bottom: 8px;
        }

        .info-sections > .section-lead {
            text-align: center;
            color: var(--muted);
            font-size: 15px;
            max-width: 720px;
            margin: 0 auto 24px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
        }

        .info-card {
            border-radius: var(--radius);
            padding: 28px;
            border: 1px solid var(--border);
        }

        .info-card.costs {
            background: linear-gradient(135deg, rgba(59,130,246,.08), var(--surface));
            border-color: rgba(59,130,246,.25);
        }

        .info-card.risks {
            background: linear-gradient(135deg, rgba(241,92,109,.08), var(--surface));
            border-color: rgba(241,92,109,.25);
        }

        .info-card h3 {
            font-size: 1.25rem;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-card .card-sub {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 20px;
        }

        .info-card ul {
            list-style: none;
            font-size: 14px;
        }

        .info-card ul li {
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,.05);
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .info-card ul li:last-child { border-bottom: none; }

        .info-card .li-icon { flex-shrink: 0; margin-top: 2px; }

        .cost-table {
            width: 100%;
            font-size: 13px;
            margin: 16px 0;
            border-collapse: collapse;
        }

        .cost-table th,
        .cost-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,.06);
        }

        .cost-table th {
            color: var(--muted);
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .cost-table td:last-child { text-align: right; white-space: nowrap; }

        .disclaimer-box {
            background: rgba(245,158,11,.08);
            border: 1px solid rgba(245,158,11,.25);
            border-radius: 12px;
            padding: 16px 20px;
            font-size: 13px;
            color: var(--muted);
            margin-top: 20px;
        }

        .disclaimer-box strong { color: var(--gold); }

        .risk-level {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 4px;
            margin-left: auto;
            flex-shrink: 0;
        }

        .risk-high { background: rgba(241,92,109,.2); color: var(--red); }
        .risk-med { background: rgba(245,158,11,.2); color: var(--gold); }
        .risk-low { background: rgba(37,211,102,.15); color: var(--accent); }

        .tips-list {
            margin-top: 16px;
            padding: 16px;
            background: rgba(37,211,102,.06);
            border-radius: 10px;
            border: 1px solid rgba(37,211,102,.15);
        }

        .tips-list h4 {
            font-size: 13px;
            color: var(--accent);
            margin-bottom: 10px;
        }

        .tips-list ul {
            font-size: 13px;
            color: var(--muted);
            padding-left: 18px;
            list-style: disc;
        }

        .tips-list li { margin-bottom: 4px; border: none; padding: 0; display: list-item; }

        .hero-notice {
            display: inline-flex;
            align-items: flex-start;
            gap: 10px;
            text-align: left;
            max-width: 640px;
            margin: 24px auto 0;
            padding: 14px 18px;
            background: rgba(245,158,11,.08);
            border: 1px solid rgba(245,158,11,.2);
            border-radius: 12px;
            font-size: 13px;
            color: var(--muted);
        }

        .hero-notice strong { color: var(--gold); }

        .info-panel { display: none; animation: fadeIn .4s ease; }
        .info-panel.active { display: block; }

        /* Cards panel toggle */
        .cards-panel { display: block; }
        .cards-panel.hidden { display: none; }

        .footnote {
            text-align: center;
            margin-top: 40px;
            font-size: .78rem;
            color: var(--muted);
        }

        .footnote a { color: var(--green); text-decoration: none; }
        .footnote a:hover { text-decoration: underline; }

        .faq.hidden, .footnote.hidden, .demo-section.hidden { display: none; }
        .meta-panel { display: none; animation: fadeIn .4s ease; }
        .meta-panel.active { display: block; }

        .meta-rates-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        @media (max-width: 900px) {
            .meta-rates-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 480px) {
            .meta-rates-grid { grid-template-columns: 1fr; }
        }

        .rate-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
        }

        .rate-card .rate-icon {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .rate-card .rate-type {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 6px;
            line-height: 1.3;
        }

        .rate-card .rate-desc {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 14px;
            line-height: 1.45;
            min-height: 52px;
        }

        .rate-card .rate-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gold);
        }

        .rate-card .rate-value small {
            font-size: 12px;
            font-weight: 500;
            color: var(--muted);
        }

        .rate-card .rate-unit {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
        }

        .meta-markup-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: rgba(245,158,11,.08);
            border: 1px solid rgba(245,158,11,.22);
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 24px;
        }

        .meta-markup-note strong { color: var(--gold); }

        .scenario-volumes {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .scenario-volumes span {
            display: inline-block;
            background: rgba(255,255,255,.04);
            padding: 2px 8px;
            border-radius: 6px;
            margin: 2px 4px 2px 0;
        }

        .scenario-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        @media (max-width: 768px) {
            .scenario-grid { grid-template-columns: 1fr; }
        }

        .scenario-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px;
            cursor: pointer;
            transition: border-color .2s, transform .2s;
        }

        .scenario-card:hover,
        .scenario-card.active {
            border-color: var(--accent);
            transform: translateY(-2px);
        }

        .scenario-card h4 {
            font-size: 1rem;
            margin-bottom: 4px;
        }

        .scenario-card .hint {
            font-size: 12px;
            color: var(--accent);
            margin-bottom: 10px;
        }

        .scenario-card p {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 14px;
        }

        .scenario-estimate {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 8px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }

        .scenario-estimate .label { font-size: 12px; color: var(--muted); }
        .scenario-estimate .value { font-size: 1.25rem; font-weight: 700; color: var(--gold); }

        .calculator-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 28px;
            margin-bottom: 24px;
        }

        .calculator-box h3 {
            font-size: 1.1rem;
            margin-bottom: 8px;
        }

        .calculator-box .calc-intro {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .calc-field {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 14px;
        }

        .calc-field-head {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }

        .calc-field-head .calc-icon {
            font-size: 1.35rem;
            line-height: 1;
            margin-top: 2px;
        }

        .calc-field-head .calc-text {
            flex: 1;
            min-width: 0;
        }

        .calc-field-head .calc-text strong {
            display: block;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .calc-field-head .calc-help {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.45;
            margin: 0;
        }

        .calc-field-head .calc-count {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--accent);
            white-space: nowrap;
            padding-left: 8px;
        }

        .calc-field input[type=range] {
            width: 100%;
            accent-color: var(--accent);
            margin: 0;
        }

        .calc-row {
            margin-bottom: 18px;
        }

        .calc-row label {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .calc-row label span:last-child {
            color: var(--accent);
            font-weight: 600;
        }

        .calc-row input[type=range] {
            width: 100%;
            accent-color: var(--accent);
        }

        .calc-total {
            background: linear-gradient(135deg, rgba(37,211,102,.08), rgba(59,130,246,.06));
            border: 1px solid rgba(37,211,102,.22);
            border-radius: 14px;
            padding: 0;
            overflow: hidden;
            margin-top: 8px;
        }

        .calc-total-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }

        @media (max-width: 600px) {
            .calc-total-grid { grid-template-columns: 1fr; }
        }

        .calc-total-item {
            padding: 20px 22px;
            border-bottom: 1px solid rgba(255,255,255,.06);
        }

        .calc-total-grid .calc-total-item:first-child {
            border-right: 1px solid rgba(255,255,255,.06);
        }

        @media (max-width: 600px) {
            .calc-total-grid .calc-total-item:first-child { border-right: none; }
        }

        .calc-total-item.grand {
            grid-column: 1 / -1;
            background: rgba(37,211,102,.06);
            border-bottom: none;
            text-align: center;
            padding: 22px;
        }

        .calc-total-item .lbl {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .07em;
            margin-bottom: 6px;
        }

        .calc-total-item .val {
            font-size: 1.65rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .calc-total-item .sub {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
        }

        .calc-total-item.meta .val { color: var(--gold); font-size: 1.35rem; }
        .calc-total-item.grand .val { color: var(--accent); font-size: 2rem; }
        .calc-total-item.grand .val .range-sep { color: var(--muted); font-weight: 500; font-size: 1.25rem; }

        .plan-cta-link {
            display: block;
            text-align: center;
            text-decoration: none;
        }

        .selection-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .selection-bar .confirm-wa {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 10px;
            text-decoration: none;
            background: var(--accent);
            color: #0a0e0d;
            font-weight: 700;
            font-size: 14px;
            white-space: nowrap;
        }

        .hero-notice a { color: var(--gold); }

        .demo-section {
            margin-top: 48px;
            padding: 40px 32px;
            border-radius: var(--radius);
            text-align: center;
            background: linear-gradient(160deg, rgba(18, 140, 126, .22), rgba(37, 211, 102, .06));
            border: 1px solid rgba(37, 211, 102, .28);
            box-shadow: 0 16px 48px rgba(0, 0, 0, .25);
        }

        .demo-section h2 {
            font-size: clamp(1.2rem, 3vw, 1.55rem);
            font-weight: 800;
            letter-spacing: -.02em;
            margin-bottom: 10px;
        }

        .demo-section .demo-lead {
            color: var(--muted);
            font-size: .92rem;
            max-width: 560px;
            margin: 0 auto 28px;
            line-height: 1.55;
        }

        .demo-actions {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
        }

        .demo-actions-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 14px;
            width: 100%;
        }

        .demo-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 28px;
            border-radius: 999px;
            font-size: .92rem;
            font-weight: 700;
            text-decoration: none;
            transition: transform .2s, filter .2s, box-shadow .2s;
            white-space: nowrap;
        }

        .demo-btn svg { width: 20px; height: 20px; flex-shrink: 0; }

        .demo-btn:hover { transform: translateY(-2px); }

        .demo-btn-wa {
            background: var(--green);
            color: #0a0e0d;
            border: 2px solid var(--green);
            box-shadow: 0 0 28px rgba(37, 211, 102, .35);
        }

        .demo-btn-wa:hover {
            filter: brightness(1.08);
            box-shadow: 0 0 36px rgba(37, 211, 102, .45);
        }

        .demo-btn-panel {
            background: var(--surface-2);
            color: var(--text);
            border: 2px solid var(--border);
        }

        .demo-btn-panel:hover {
            border-color: var(--blue);
            color: var(--blue);
        }

        .demo-creds {
            margin-top: 6px;
            font-size: .78rem;
            color: var(--muted);
        }

        .demo-creds strong {
            color: var(--green);
            font-family: ui-monospace, monospace;
        }

        .demo-creds-label {
            display: block;
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 4px;
            color: var(--muted);
        }

        .page-footer {
            text-align: center;
            padding: 32px 24px 16px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: .78rem;
            margin-top: 40px;
        }

        /* Ventaja panel vs WhatsApp normal */
        .panel-value-section {
            margin-top: 56px;
            margin-bottom: 0;
        }

        .panel-value-section > h2 {
            font-size: clamp(1.2rem, 3vw, 1.55rem);
            font-weight: 800;
            letter-spacing: -.02em;
            text-align: center;
            margin-bottom: 10px;
        }

        .panel-value-section > .section-lead {
            text-align: center;
            color: var(--muted);
            font-size: 15px;
            max-width: 680px;
            margin: 0 auto 28px;
            line-height: 1.55;
        }

        .vs-whatsapp {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 28px;
            max-width: 720px;
            margin-left: auto;
            margin-right: auto;
        }

        @media (max-width: 600px) {
            .vs-whatsapp { grid-template-columns: 1fr; }
        }

        .vs-col {
            border-radius: 12px;
            padding: 18px 20px;
            font-size: 13px;
        }

        .vs-col.bad {
            background: rgba(241,92,109,.06);
            border: 1px solid rgba(241,92,109,.2);
        }

        .vs-col.good {
            background: rgba(37,211,102,.06);
            border: 1px solid rgba(37,211,102,.22);
        }

        .vs-col h4 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 12px;
        }

        .vs-col.bad h4 { color: var(--red); }
        .vs-col.good h4 { color: var(--accent); }

        .vs-col ul {
            list-style: none;
            color: var(--muted);
            line-height: 1.5;
        }

        .vs-col ul li {
            padding: 5px 0;
            padding-left: 18px;
            position: relative;
        }

        .vs-col.bad ul li::before {
            content: '✕';
            position: absolute;
            left: 0;
            color: var(--red);
            font-size: 11px;
        }

        .vs-col.good ul li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--accent);
            font-size: 11px;
        }

        .panel-value-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        @media (max-width: 768px) {
            .panel-value-grid { grid-template-columns: 1fr; }
        }

        .panel-value-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px 20px;
        }

        .panel-value-card .pvc-icon {
            font-size: 1.75rem;
            margin-bottom: 12px;
        }

        .panel-value-card h3 {
            font-size: 1rem;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .panel-value-card p {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.55;
            margin-bottom: 12px;
        }

        .panel-value-card ul {
            list-style: none;
            font-size: 12px;
            color: var(--muted);
        }

        .panel-value-card ul li {
            padding: 4px 0 4px 16px;
            position: relative;
        }

        .panel-value-card ul li::before {
            content: '→';
            position: absolute;
            left: 0;
            color: var(--accent);
        }

        .panel-value-card .plan-tag {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 6px;
            background: rgba(37,211,102,.12);
            color: var(--accent);
            margin-top: 4px;
        }

        .comparison-scroll-hint {
            display: none;
            font-size: 12px;
            color: var(--muted);
            text-align: center;
            margin-bottom: 10px;
        }

        /* ─── Responsive móvil ─── */
        @media (max-width: 768px) {
            .topbar {
                flex-wrap: wrap;
                padding: 12px 16px;
                gap: 10px;
            }
            .topbar-nav { width: 100%; justify-content: center; }

            .wrap {
                padding: 28px 16px calc(130px + env(safe-area-inset-bottom, 0px));
            }

            .hero {
                margin-bottom: 28px;
            }

            .hero p {
                font-size: 0.95rem;
                margin-bottom: 20px;
            }

            .hero-notice {
                flex-direction: row;
                text-align: left;
                font-size: 12px;
                padding: 12px 14px;
                margin-top: 16px;
            }

            .view-tabs {
                justify-content: flex-start;
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scroll-snap-type: x proximity;
                margin: 24px -16px 24px;
                padding: 0 16px 6px;
                gap: 6px;
                scrollbar-width: none;
            }

            .view-tabs::-webkit-scrollbar { display: none; }

            .view-tabs button {
                flex-shrink: 0;
                scroll-snap-align: start;
                font-size: 13px;
                padding: 10px 16px;
            }

            .plan-card {
                padding: 24px 20px;
            }

            .plan-card:hover {
                transform: none;
            }

            .plan-price .amount {
                font-size: 2.25rem;
            }

            .feature-list li {
                font-size: 13px;
                padding-left: 26px;
            }

            .feature-list .tag {
                float: none;
                display: inline-block;
                margin-left: 6px;
                vertical-align: middle;
            }

            .demo-actions-row {
                flex-direction: column;
            }

            .demo-section {
                padding: 28px 16px;
                margin-top: 32px;
            }

            .demo-btn {
                width: 100%;
                min-width: unset;
            }

            .panel-value-section > h2 {
                font-size: 1.35rem;
            }

            .panel-value-card h3 {
                font-size: 0.95rem;
            }

            .comparison-scroll-hint { display: block; }

            .comparison-table-wrap {
                margin: 0 -4px;
                border-radius: 12px;
            }

            table.comparison {
                font-size: 12px;
                min-width: 540px;
            }

            table.comparison th,
            table.comparison td {
                padding: 10px 12px;
            }

            table.comparison th:first-child,
            table.comparison td:first-child {
                position: sticky;
                left: 0;
                z-index: 2;
                min-width: 130px;
                max-width: 150px;
                background: var(--surface);
                box-shadow: 4px 0 12px rgba(0,0,0,.25);
            }

            table.comparison thead th:first-child {
                background: var(--surface-2);
                z-index: 3;
            }

            .info-sections > h2 {
                font-size: 1.5rem;
            }

            .info-sections > .section-lead {
                font-size: 14px;
                padding: 0 4px;
            }

            .info-card {
                padding: 20px 18px;
            }

            .info-card ul li {
                flex-wrap: wrap;
            }

            .info-card ul li .risk-level {
                margin-left: 0;
                margin-top: 6px;
            }

            .rate-card .rate-desc {
                min-height: auto;
            }

            .scenario-estimate {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .scenario-estimate .value {
                font-size: 1.1rem;
            }

            .calculator-box {
                padding: 20px 16px;
            }

            .calc-field {
                padding: 14px;
            }

            .calc-field-head {
                flex-wrap: wrap;
                gap: 8px;
            }

            .calc-field-head .calc-count {
                margin-left: auto;
                padding-left: 0;
                font-size: 1rem;
            }

            .calc-total-item .val {
                font-size: 1.35rem;
            }

            .calc-total-item.grand .val {
                font-size: 1.5rem;
            }

            .calc-total-item.grand .val .range-sep {
                font-size: 1rem;
            }

            .faq {
                margin-top: 40px;
            }

            .faq h2 {
                font-size: 1.5rem;
            }

            .faq-q {
                font-size: 14px;
                padding: 14px 16px;
            }

            .faq-a p,
            .faq-a ul {
                padding-left: 16px;
                padding-right: 16px;
                font-size: 13px;
            }

            .selection-bar {
                padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0px));
            }

            .selection-bar-inner {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .selection-info {
                font-size: 14px;
                text-align: center;
            }

            .selection-actions {
                width: 100%;
            }

            .selection-bar .close-sel,
            .selection-bar .confirm-wa {
                flex: 1;
                padding: 14px 12px;
                font-size: 13px;
                text-align: center;
                white-space: normal;
            }

            .meta-markup-note {
                font-size: 12px;
                padding: 12px 14px;
            }

            .disclaimer-box {
                font-size: 12px;
                padding: 14px;
            }
        }

        @media (max-width: 380px) {
            .hero h1 {
                font-size: 1.85rem;
            }

            .badge {
                font-size: 11px;
                padding: 5px 12px;
            }

            .view-tabs button {
                font-size: 12px;
                padding: 9px 12px;
            }

            .plan-title {
                font-size: 1.5rem;
            }

            .scenario-volumes span {
                display: block;
                margin: 4px 0;
            }
        }

        @media (hover: none) and (pointer: coarse) {
            .plan-card:hover,
            .scenario-card:hover {
                transform: none;
            }

            .plan-cta:hover,
            .plan-cta-link:hover {
                transform: none;
            }
        }
    </style>
</head>
<body data-whatsapp="{{ $whatsappNumber }}">

<header class="topbar">
    <a href="{{ route('login') }}" class="topbar-brand">
        <div class="brand">Chatbot<span>Ventas</span></div>
        <div class="brand-sub">WhatsApp Business API · Panel web</div>
    </a>
    <nav class="topbar-nav">
        <a href="{{ route('public.resumen') }}" class="topbar-link">Qué incluye</a>
        <a href="{{ route('pricing.index') }}" class="topbar-link active">Planes</a>
        <a href="{{ route('login') }}" class="topbar-link primary">Ingresar</a>
    </nav>
</header>

<div class="wrap">
    <header class="hero">
        <div class="badge">Planes y precios</div>
        <h1>Invierte en vender más — <em>antes que tu competencia</em></h1>
        <p>Automatiza ventas y atención por WhatsApp con bot + panel web. Elige el nivel que necesitas hoy y escala cuando crezcas.</p>

        <div class="urgency-banner">
            <p><strong>Quien responde primero, cierra.</strong> Mientras evalúas, otros negocios ya atienden 24/7 con catálogo, pedidos y confirmaciones automáticas en el mismo canal donde están tus clientes.</p>
        </div>

        <div class="hero-notice">
            <span>⚠️</span>
            <span>Los precios de los planes (<strong>sin IVA</strong>) cubren solo la plataforma (bot + panel). Los consumos de mensajes y plantillas de Meta/WhatsApp se facturan <strong>aparte</strong>. <a href="#" data-goto="meta">Ver costos Meta ↓</a></span>
        </div>
    </header>

    <div class="view-tabs">
        <button type="button" class="active" data-view="cards">Ver planes</button>
        <button type="button" data-view="comparison">Comparativa</button>
        <button type="button" data-view="meta">Costos Meta</button>
        <button type="button" data-view="costs">Riesgos</button>
    </div>

    <!-- Tarjetas de planes -->
    <section class="cards-panel" id="cardsPanel">

        <div class="plans-grid">

            <!-- STARTER $60 -->
            <article class="plan-card" data-plan="starter" data-price="60">
                <div class="plan-icon">🚀</div>
                <div class="plan-name">Plan Esencial</div>
                <h2 class="plan-title">Starter</h2>
                <p class="plan-desc">Todo el bot y el panel admin para operar tu negocio por WhatsApp, sin campañas masivas.</p>

                <div class="plan-price">
                    <span class="amount"><span class="currency">$</span><span class="price-value">60</span></span>
                    <span class="period">/ mes</span>
                    <span class="plan-tax-note">sin IVA</span>
                </div>
                <a href="{{ $whatsappUrls['starter'] }}" class="plan-cta plan-cta-link" data-select="starter" target="_blank" rel="noopener">Elegir Starter</a>

                <div class="features-label">Incluye</div>
                <ul class="feature-list">
                    <li class="feature-group">Bot WhatsApp</li>
                    <li><span class="icon yes">✓</span> Menú: productos, mis pedidos e info del negocio</li>
                    <li><span class="icon yes">✓</span> Info negocio: horarios, contacto, pagos y envíos</li>
                    <li><span class="icon yes">✓</span> Catálogo: <strong>{{ $plans['starter']['limits']['max_products'] }}</strong> productos · <strong>{{ $plans['starter']['limits']['max_categories'] }}</strong> categorías <span class="tag tag-limited">Límite</span></li>
                    <li><span class="icon yes">✓</span> Carrito, checkout y pedidos ORD</li>
                    <li><span class="icon yes">✓</span> Notas en la compra (opcional)</li>
                    <li><span class="icon yes">✓</span> Método de pago: transferencia, tarjeta o efectivo</li>
                    <li><span class="icon yes">✓</span> Seguimiento de pedidos en el chat</li>
                    <li><span class="icon yes">✓</span> Pide asesor → alerta en panel</li>
                    <li><span class="icon yes">✓</span> Palabras clave automáticas</li>
                    <li><span class="icon yes">✓</span> Config del bot: nombre, avatar y mensajes</li>
                    <li class="feature-group">Panel web</li>
                    <li><span class="icon yes">✓</span> Módulo Chats: en vivo + alerta asesor</li>
                    <li><span class="icon yes">✓</span> Módulo Clientes: editar datos y gestiones</li>
                    <li><span class="icon yes">✓</span> Módulo Pedidos: estados, filtros y Excel</li>
                    <li><span class="icon yes">✓</span> Catálogo: agregar, editar e inactivar productos</li>
                    <li><span class="icon yes">✓</span> Categorías y flujo del bot editable</li>
                    <li><span class="icon yes">✓</span> Módulo Usuarios: roles y permisos ({{ $plans['starter']['limits']['admin_users'] }})</li>
                    <li><span class="icon yes">✓</span> Dashboard: indicadores y reportes de ventas</li>
                    <li><span class="icon yes">✓</span> Historial de conversaciones</li>
                    <li><span class="icon yes">✓</span> Uso del plan: productos y categorías</li>
                    <li><span class="icon yes">✓</span> <strong>{{ $plans['starter']['limits']['storage_gb'] }} GB</strong> almacenamiento <span class="tag tag-limited">Límite</span></li>
                    <li class="feature-group muted">No incluye</li>
                    <li class="excluded"><span class="icon no">✕</span> Segmentación CRM automática</li>
                    <li class="excluded"><span class="icon no">✕</span> Pedidos avanzados (factura, notas internas)</li>
                    <li class="excluded"><span class="icon no">✕</span> Consumo Meta · colores del bot</li>
                    <li class="excluded"><span class="icon no">✕</span> Campañas masivas</li>
                    <li class="excluded"><span class="icon no">✕</span> Imágenes/PDF y comprobantes del cliente</li>
                    <li class="excluded"><span class="icon no">✕</span> Integraciones y reportes avanzados</li>
                </ul>
            </article>

            <!-- PRO $90 -->
            <article class="plan-card featured selected" data-plan="pro" data-price="90">
                <div class="plan-icon">⚡</div>
                <div class="plan-name">Plan Profesional</div>
                <h2 class="plan-title">Pro</h2>
                <p class="plan-desc">Ideal para negocios que venden, cobran comprobantes y hacen marketing activo por WhatsApp.</p>

                <div class="plan-price">
                    <span class="amount"><span class="currency">$</span><span class="price-value">90</span></span>
                    <span class="period">/ mes</span>
                    <span class="plan-tax-note">sin IVA</span>
                </div>
                <a href="{{ $whatsappUrls['pro'] }}" class="plan-cta plan-cta-link" data-select="pro" target="_blank" rel="noopener">Elegir Pro</a>

                <div class="features-label">Todo Starter, más</div>
                <ul class="feature-list">
                    <li class="feature-group">Bot WhatsApp</li>
                    <li><span class="icon yes">✓</span> <strong>{{ $plans['pro']['limits']['max_products'] }}</strong> productos · <strong>{{ $plans['pro']['limits']['max_categories'] }}</strong> categorías <span class="tag tag-full">Ampliado</span></li>
                    <li><span class="icon yes">✓</span> Recibe imágenes y PDF del cliente</li>
                    <li><span class="icon yes">✓</span> Comprobante de pago en el flujo</li>
                    <li><span class="icon yes">✓</span> Plantillas masivas y campañas</li>
                    <li><span class="icon yes">✓</span> Envío programado por segmento</li>
                    <li><span class="icon yes">✓</span> Pedido masivo por formulario web (enlace en chat)</li>
                    <li><span class="icon yes">✓</span> Imágenes en productos y cabeceras</li>
                    <li class="feature-group">Panel web</li>
                    <li><span class="icon yes">✓</span> <strong>{{ $plans['pro']['limits']['admin_users'] }} usuarios</strong> admin <span class="tag tag-full">Ampliado</span></li>
                    <li><span class="icon yes">✓</span> <strong>{{ $plans['pro']['limits']['storage_gb'] }} GB</strong> almacenamiento <span class="tag tag-full">Ampliado</span></li>
                    <li><span class="icon yes">✓</span> Segmentación: VIP, frecuentes, pide agente</li>
                    <li><span class="icon yes">✓</span> Pedidos: factura, notas y feedback</li>
                    <li><span class="icon yes">✓</span> Consumo Meta en dashboard</li>
                    <li><span class="icon yes">✓</span> Colores y avatar del bot</li>
                    <li><span class="icon yes">✓</span> Alertas externas WhatsApp / email</li>
                    <li><span class="icon yes">✓</span> Reportes de pedidos y clientes</li>
                    <li><span class="icon yes">✓</span> Soporte ticket (48 h)</li>
                    <li class="feature-group muted">No incluye</li>
                    <li class="excluded"><span class="icon no">✕</span> Integraciones ERP / CRM</li>
                    <li class="excluded"><span class="icon no">✕</span> Reportes ejecutivos</li>
                    <li class="excluded"><span class="icon no">✕</span> Desarrollo a medida</li>
                </ul>
            </article>

            <!-- ENTERPRISE $130+ -->
            <article class="plan-card" data-plan="enterprise" data-price="130">
                <div class="plan-icon">👑</div>
                <div class="plan-name">Plan Empresarial</div>
                <h2 class="plan-title">Enterprise</h2>
                <p class="plan-desc">Para empresas que necesitan integraciones, reportes y acompañamiento cercano de nuestro equipo.</p>

                <div class="plan-price">
                    <span class="from-label">Desde</span>
                    <span class="amount"><span class="currency">$</span><span class="price-value">130</span></span>
                    <span class="period">/ mes</span>
                    <span class="plan-tax-note">sin IVA</span>
                </div>
                <a href="{{ $whatsappUrls['enterprise'] }}" class="plan-cta plan-cta-link" data-select="enterprise" target="_blank" rel="noopener">Solicitar cotización</a>

                <div class="features-label">Todo Pro, más</div>
                <ul class="feature-list">
                    <li class="feature-group">Bot WhatsApp</li>
                    <li><span class="icon yes">✓</span> <strong>{{ $plans['enterprise']['limits']['max_products'] }}</strong> productos · <strong>{{ $plans['enterprise']['limits']['max_categories'] }}</strong> categorías <span class="tag tag-full">Ampliable</span></li>
                    <li><span class="icon yes">✓</span> IA ChatGPT (opcional)</li>
                    <li class="feature-group">Panel web</li>
                    <li><span class="icon yes">✓</span> Usuarios admin ilimitados</li>
                    <li><span class="icon yes">✓</span> <strong>{{ $plans['enterprise']['limits']['storage_gb'] }} GB</strong> almacenamiento ampliable</li>
                    <li><span class="icon yes">✓</span> Integraciones CRM, ERP, webhooks</li>
                    <li><span class="icon yes">✓</span> Reportes avanzados y exportación</li>
                    <li><span class="icon yes">✓</span> Dashboard ejecutivo</li>
                    <li><span class="icon yes">✓</span> Ajustes menores incluidos</li>
                    <li><span class="icon yes">✓</span> Soporte prioritario y canal directo</li>
                    <li><span class="icon yes">✓</span> Onboarding y revisiones mensuales</li>
                    <li><span class="icon partial">~</span> Desarrollos mayores aparte</li>
                </ul>
            </article>
        </div>

        <section class="panel-value-section" id="panel-web">
            <h2>Lo que <em style="font-style:italic;color:var(--accent);">no ves</em> en WhatsApp normal</h2>
            <p class="section-lead">Desde el teléfono solo chateas. El panel web es donde controlas el negocio: cuántos clientes escribieron, cuántos pedidos entraron, qué se dijo en cada conversación y en qué estado va cada venta.</p>

            <div class="vs-whatsapp">
                <div class="vs-col bad">
                    <h4>📱 Solo WhatsApp en el móvil</h4>
                    <ul>
                        <li>Chats mezclados sin reportes ni totales</li>
                        <li>No sabes cuántos pedidos hubo este mes</li>
                        <li>Si cambias de celular, pierdes contexto</li>
                        <li>No distingues fácil bot vs humano en el historial</li>
                        <li>Sin listado centralizado de clientes por número</li>
                    </ul>
                </div>
                <div class="vs-col good">
                    <h4>🖥️ Con nuestro panel admin</h4>
                    <ul>
                        <li>Historial completo de cada conversación</li>
                        <li>Reportes de clientes activos y pedidos</li>
                        <li>Trazabilidad: ORD-XXXX, estados, pagos, comprobantes</li>
                        <li>Estadísticas por contacto (respuestas, actividad)</li>
                        <li>Todo el equipo ve la misma información</li>
                        <li>Módulo Clientes con perfil, KPIs y datos de facturación</li>
                        <li>Dashboard con barras de uso del plan y consumo Meta</li>
                    </ul>
                </div>
            </div>

            <div class="panel-value-grid">
                <article class="panel-value-card">
                    <div class="pvc-icon">👥</div>
                    <h3>Módulo Clientes (CRM)</h3>
                    <p>Conoce quién te escribe, cuánto compra y quién necesita atención — sin exportar chats a Excel.</p>
                    <ul>
                        <li>Listado con búsqueda, filtros y última actividad</li>
                        <li>Perfil con pedidos, notas y datos de facturación</li>
                        <li>Segmentos automáticos: VIP, frecuente, sin responder (Pro+)</li>
                        <li>Acceso directo al chat desde la ficha del cliente</li>
                    </ul>
                    <span class="plan-tag">Starter básico · Pro avanzado</span>
                </article>

                <article class="panel-value-card">
                    <div class="pvc-icon">📋</div>
                    <h3>Gestión operativa de pedidos</h3>
                    <p>Tabla centralizada para tu equipo: estados, totales y seguimiento sin perderse en el móvil.</p>
                    <ul>
                        <li>Vista en tabla con filtros por estado y fecha</li>
                        <li>Modal completo: ítems, checklist del agente, observaciones</li>
                        <li>Facturación y feedback del cliente (Pro+)</li>
                        <li>Exportación Excel: una fila por producto con datos del cliente</li>
                        <li>Enlace al chat del contacto en un clic</li>
                    </ul>
                    <span class="plan-tag">Starter operativo · Pro avanzado</span>
                </article>

                <article class="panel-value-card">
                    <div class="pvc-icon">📊</div>
                    <h3>Reportes de clientes y pedidos</h3>
                    <p>Consulta cuántos contactos escribieron, cuántos pedidos generó el bot, montos y estados — por cliente o en global.</p>
                    <ul>
                        <li>Listado de pedidos con número ORD, fecha e ítems</li>
                        <li>Clientes por número de WhatsApp y última actividad</li>
                        <li>Cantidades vendidas y totales por período</li>
                        <li>Estados: pendiente, pagado, completado, cancelado</li>
                    </ul>
                    <span class="plan-tag">Todos los planes · avanzado en Enterprise</span>
                </article>

                <article class="panel-value-card">
                    <div class="pvc-icon">🔍</div>
                    <h3>Trazabilidad de cada venta</h3>
                    <p>Cada pedido queda registrado con su recorrido: qué compró, cómo pagó, si envió comprobante y quién lo atendió después.</p>
                    <ul>
                        <li>Historial de cambios de estado del pedido</li>
                        <li>Notas del cliente al confirmar compra</li>
                        <li>Comprobantes de pago archivados en el chat</li>
                        <li>Vinculación contacto ↔ pedido ↔ conversación</li>
                    </ul>
                    <span class="plan-tag">Starter en adelante</span>
                </article>

                <article class="panel-value-card">
                    <div class="pvc-icon">💬</div>
                    <h3>Historial de conversaciones</h3>
                    <p>Todo lo que el cliente y el bot (o tu equipo) se dijeron, guardado en el panel — no solo en un teléfono.</p>
                    <ul>
                        <li>Hilo completo por contacto con fecha y hora</li>
                        <li>Texto, botones, imágenes y documentos</li>
                        <li>Identificación: mensaje del cliente, bot o asesor</li>
                        <li>Alerta cuando pide hablar con un humano (banner, contador, chat resaltado)</li>
                        <li>Marca «atendido» cuando tu equipo ya respondió</li>
                        <li>Búsqueda visual desde sidebar de chats</li>
                    </ul>
                    <span class="plan-tag">Todos los planes</span>
                </article>

                <article class="panel-value-card">
                    <div class="pvc-icon">📈</div>
                    <h3>Estadísticas y dashboard</h3>
                    <p>Métricas que WhatsApp no te da: tiempos de respuesta, mensajes por día, hora pico y rendimiento del bot.</p>
                    <ul>
                        <li>Stats por contacto dentro del chat (Starter/Pro)</li>
                        <li>Barras de uso del plan: productos, categorías y GB</li>
                        <li>Estimado de consumo Meta del mes (Pro+)</li>
                        <li>Dashboard global y exportación (Enterprise)</li>
                    </ul>
                    <span class="plan-tag">Básico → Enterprise avanzado</span>
                </article>
            </div>
        </section>
    </section>

    <!-- Tabla comparativa -->
    <section class="comparison-panel" id="comparisonPanel">
        <p class="comparison-scroll-hint">← Desliza horizontalmente para ver todos los planes →</p>
        <div class="comparison-table-wrap">
            <table class="comparison">
                <thead>
                    <tr>
                        <th>Funcionalidad</th>
                        <th>Starter<br><small>$60/mes · sin IVA</small></th>
                        <th>Pro<br><small>$90/mes · sin IVA</small></th>
                        <th>Enterprise<br><small>desde $130/mes · sin IVA</small></th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="category-row"><td colspan="4">Bot WhatsApp</td></tr>
                    <tr>
                        <td>Menú: productos, mis pedidos e info del negocio</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Info del negocio (horarios, contacto, pagos, envíos)</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Palabras clave y respuestas automáticas</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Seguimiento «Mis pedidos» en el chat</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Cliente pide asesor humano</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Cliente envía imágenes / PDF</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>IA conversacional (ChatGPT)</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-partial">Opcional</td>
                    </tr>

                    <tr>
                        <td>Catálogo por categorías y búsqueda por SKU</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Carrito, checkout y confirmación de pedido</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Notas en la compra (opcional)</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Método de pago: transferencia, tarjeta o efectivo</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Comprobante de pago en flujo de venta</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Imágenes en productos y mensajes del flujo</td>
                        <td class="cell-partial">Solo salida</td>
                        <td class="cell-yes">Entrada y salida</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Promociones y precios especiales</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Productos en catálogo</td>
                        <td class="cell-partial">Hasta {{ $plans['starter']['limits']['max_products'] }}</td>
                        <td class="cell-partial">Hasta {{ $plans['pro']['limits']['max_products'] }}</td>
                        <td class="cell-partial">Hasta {{ $plans['enterprise']['limits']['max_products'] }}</td>
                    </tr>
                    <tr>
                        <td>Categorías</td>
                        <td class="cell-partial">Hasta {{ $plans['starter']['limits']['max_categories'] }}</td>
                        <td class="cell-partial">Hasta {{ $plans['pro']['limits']['max_categories'] }}</td>
                        <td class="cell-partial">Hasta {{ $plans['enterprise']['limits']['max_categories'] }}</td>
                    </tr>
                    <tr>
                        <td>Pedido masivo por formulario web (enlace en chat)</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Plantillas masivas y campañas</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Envío programado por segmento</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Alertas externas WhatsApp / email</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>

                    <tr class="category-row"><td colspan="4">Panel web</td></tr>
                    <tr>
                        <td>Historial completo de conversaciones por cliente</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Identificar mensajes: cliente, bot o asesor humano</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Listado centralizado de pedidos (número, total, estado)</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Reporte de clientes por número y actividad</td>
                        <td class="cell-partial">Básico</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Cantidades y totales de pedidos por período</td>
                        <td class="cell-partial">En panel pedidos</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Trazabilidad de estados (pendiente → pagado → completado)</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Estadísticas por contacto (tiempos, mensajes, actividad)</td>
                        <td class="cell-yes">Básicas</td>
                        <td class="cell-yes">Básicas</td>
                        <td class="cell-yes">Avanzadas</td>
                    </tr>
                    <tr>
                        <td>Dashboard global y exportación de reportes</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-yes">✓</td>
                    </tr>

                    <tr>
                        <td>Chat en vivo</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Alerta al pedir asesor (banner, contador)</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Gestión de pedidos y estados</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Catálogo: agregar, editar e inactivar productos</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Categorías y flujo del bot editable</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Módulo Clientes: editar datos y gestiones</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Módulo Usuarios y roles</td>
                        <td class="cell-partial">{{ $plans['starter']['limits']['admin_users'] }} usuarios</td>
                        <td class="cell-partial">{{ $plans['pro']['limits']['admin_users'] }} usuarios</td>
                        <td class="cell-yes">Ilimitados</td>
                    </tr>
                    <tr>
                        <td>Dashboard: reportes e indicadores de ventas</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">Avanzado</td>
                    </tr>
                    <tr>
                        <td>Segmentación automática (VIP, frecuentes, pide agente)</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Gestión avanzada de pedidos (factura, notas internas, feedback)</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Exportación Excel de pedidos</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Uso del plan (productos y categorías)</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Almacenamiento (imágenes y archivos)</td>
                        <td class="cell-partial">{{ $plans['starter']['limits']['storage_gb'] }} GB</td>
                        <td class="cell-partial">{{ $plans['pro']['limits']['storage_gb'] }} GB</td>
                        <td class="cell-partial">{{ $plans['enterprise']['limits']['storage_gb'] }} GB</td>
                    </tr>
                    <tr>
                        <td>Estimado de consumo Meta en dashboard</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Personalización visual del bot (colores en panel)</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-yes">✓</td>
                        <td class="cell-yes">✓</td>
                    </tr>

                    <tr class="category-row"><td colspan="4">Servicio</td></tr>
                    <tr>
                        <td>Integraciones (CRM, ERP, APIs, webhooks)</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Ajustes menores incluidos (sin costo extra)</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                    <tr>
                        <td>Soporte</td>
                        <td class="cell-partial">Email · 72 h</td>
                        <td class="cell-partial">Ticket · 48 h</td>
                        <td class="cell-yes">Prioritario · directo</td>
                    </tr>
                    <tr>
                        <td>Onboarding y revisiones mensuales</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-no">✕</td>
                        <td class="cell-yes">✓</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Costos Meta aproximados -->
    <section class="meta-panel" id="metaPanel">
        <div class="info-sections">
            <h2 id="costos-meta">¿Cuánto pagarás a Meta por WhatsApp?</h2>
            <p class="section-lead">Además de tu plan ($60 / $90 / $130+, sin IVA), Meta cobra por cada conversación. Aquí te explicamos en palabras simples qué significa cada tipo de mensaje y cuánto podrías gastar al mes.</p>

            <div class="meta-rates-grid">
                @foreach($metaRates['per_conversation'] ?? [] as $key => $rate)
                <div class="rate-card">
                    <div class="rate-icon">{{ $rate['icon'] ?? '💬' }}</div>
                    <div class="rate-type">{{ $rate['label'] }}</div>
                    <div class="rate-desc">{{ $rate['description'] ?? '' }}</div>
                    <div class="rate-value">${{ number_format($rate['min'], 3) }}<small> – ${{ number_format($rate['max'], 3) }}</small></div>
                    <div class="rate-unit">por conversación · {{ $metaRates['region'] ?? 'Latam' }}</div>
                </div>
                @endforeach
            </div>

            <h3 style="font-size:1.15rem;margin-bottom:8px;">Ejemplos reales de gasto mensual (solo Meta)</h3>
            <p style="font-size:13px;color:var(--muted);margin-bottom:16px;">Haz clic en un ejemplo para cargarlo en la calculadora de abajo.</p>
            <div class="scenario-grid" id="scenarioGrid">
                @foreach($metaScenarios as $scenario)
                <div class="scenario-card" data-scenario="{{ $scenario['id'] }}"
                     data-service="{{ $scenario['service_conv'] }}"
                     data-utility="{{ $scenario['utility_conv'] }}"
                     data-marketing="{{ $scenario['marketing_conv'] }}"
                     data-min="{{ $scenario['estimate_min'] }}"
                     data-max="{{ $scenario['estimate_max'] }}">
                    <h4>{{ $scenario['title'] }}</h4>
                    <div class="hint">Plan recomendado: {{ $scenario['plan_hint'] }}</div>
                    <p>{{ $scenario['description'] }}</p>
                    <div class="scenario-volumes">
                        <span>💬 {{ number_format($scenario['service_conv']) }} clientes escribieron</span>
                        <span>📋 {{ number_format($scenario['utility_conv']) }} avisos del bot</span>
                        @if($marketingEnabled ?? false)
                        <span>📢 {{ number_format($scenario['marketing_conv']) }} promos enviadas</span>
                        @endif
                    </div>
                    <div class="scenario-estimate">
                        <span class="label">Meta al mes (aprox.)</span>
                        <span class="value">${{ number_format($scenario['estimate_min'], 0) }} – ${{ number_format($scenario['estimate_max'], 0) }}</span>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="calculator-box">
                <h3>🧮 Calcula tu total mensual</h3>
                <p class="calc-intro">Mueve las barras según cuántos clientes esperas. Sumamos tu plan + el consumo estimado de Meta para que veas el costo real aproximado.</p>

                <div class="calc-row">
                    <label>Tu plan de plataforma</label>
                    <select id="calcPlan" style="width:100%;padding:12px;border-radius:10px;background:var(--surface-2);border:1px solid var(--border);color:var(--text);font-family:inherit;font-size:14px;">
                        <option value="60">Starter — $60/mes sin IVA</option>
                        <option value="90" selected>Pro — $90/mes sin IVA</option>
                        <option value="130">Enterprise — desde $130/mes sin IVA</option>
                    </select>
                </div>

                <div class="calc-field">
                    <div class="calc-field-head">
                        <span class="calc-icon">💬</span>
                        <div class="calc-text">
                            <strong>Clientes que te escriben al mes</strong>
                            <p class="calc-help">Personas que te mandan hola, preguntan, compran o usan el bot. Ellas inician el chat.</p>
                        </div>
                        <span class="calc-count" id="valService">600</span>
                    </div>
                    <input type="range" id="calcService" min="0" max="5000" step="50" value="600">
                </div>

                <div class="calc-field">
                    <div class="calc-field-head">
                        <span class="calc-icon">📋</span>
                        <div class="calc-text">
                            <strong>Avisos automáticos del bot</strong>
                            <p class="calc-help">Confirmaciones de pedido, “recibimos tu pago”, cambios de estado. Son informativos, no publicidad.</p>
                        </div>
                        <span class="calc-count" id="valUtility">120</span>
                    </div>
                    <input type="range" id="calcUtility" min="0" max="2000" step="20" value="120">
                </div>

                @if($marketingEnabled ?? false)
                <div class="calc-field" id="calcMarketingField">
                    <div class="calc-field-head">
                        <span class="calc-icon">📢</span>
                        <div class="calc-text">
                            <strong>Promociones que tú envías</strong>
                            <p class="calc-help">Ofertas, recordatorios o campañas masivas a tu lista (plan Pro). Tú inicias el mensaje — suele ser lo más costoso.</p>
                        </div>
                        <span class="calc-count" id="valMarketing">0</span>
                    </div>
                    <input type="range" id="calcMarketing" min="0" max="5000" step="50" value="0">
                </div>
                @endif

                <div class="calc-total">
                    <div class="calc-total-grid">
                        <div class="calc-total-item">
                            <div class="lbl">Tu plan (plataforma)</div>
                            <div class="val" id="totalPlatform">$90.00</div>
                            <div class="sub">Bot + panel admin</div>
                        </div>
                        <div class="calc-total-item meta">
                            <div class="lbl">Consumo Meta (aprox.)</div>
                            <div class="val" id="totalMeta">—</div>
                            <div class="sub">Según tarifas de referencia</div>
                        </div>
                    </div>
                    <div class="calc-total-item grand">
                        <div class="lbl">Total mensual estimado</div>
                        <div class="val" id="totalGrand">—</div>
                        <div class="sub">Plataforma + Meta · cifras orientativas</div>
                    </div>
                </div>
            </div>

            <div class="disclaimer-box">
                <strong>Importante:</strong> Son estimaciones, no facturas exactas. Meta puede cambiar tarifas sin aviso. No incluye IVA ni cargos extra del proveedor de API. Revisa la <a href="https://developers.facebook.com/docs/whatsapp/pricing" target="_blank" rel="noopener" style="color:var(--gold);">tarifa oficial de Meta</a> antes de lanzar campañas grandes.
            </div>
        </div>
    </section>

    <!-- Riesgos -->
    <section class="info-panel" id="costsPanel">
        <div class="info-sections">
            <h2>Riesgos y costos adicionales</h2>
            <p class="section-lead">Tu plan mensual no elimina el riesgo de restricción o bloqueo del número si se incumplen las políticas de Meta.</p>

            <div class="info-grid">
                <!-- Resumen costos -->
                <article class="info-card costs">
                    <h3>💳 Resumen: qué pagas aparte</h3>
                    <p class="card-sub">Además del plan de plataforma, el cliente cubre directamente los consumos de Meta.</p>
                    <ul>
                        <li><span class="li-icon">💬</span><span>Cuando te escriben, avisos del bot y promociones que envías (Meta cobra cada tipo aparte)</span></li>
                        <li><span class="li-icon">📨</span><span>Promociones masivas (plan Pro) — cada envío exitoso tiene costo en Meta</span></li>
                        <li><span class="li-icon">📱</span><span>Registro y mantenimiento del número WhatsApp Business API</span></li>
                    </ul>
                    <p style="font-size:13px;color:var(--muted);margin-top:12px;">Usa la pestaña <strong>Costos Meta</strong> para estimar tu gasto mensual aproximado.</p>
                    <div class="disclaimer-box">
                        <strong>Importante:</strong> Nosotros cobramos el plan de plataforma. Los cargos de Meta son responsabilidad del cliente.
                    </div>
                </article>

                <!-- Riesgos (keep existing risks card content) -->
                <article class="info-card risks">
                    <h3>🚫 Riesgos si bloquean tu WhatsApp</h3>
                    <p class="card-sub">Meta puede limitar, suspender o bloquear permanentemente un número. La plataforma no garantiza que esto no ocurra.</p>

                    <ul>
                        <li>
                            <span class="li-icon">⛔</span>
                            <span><strong>Envío masivo no solicitado (spam)</strong> — contactar personas que no te escribieron ni dieron consentimiento</span>
                            <span class="risk-level risk-high">Alto</span>
                        </li>
                        <li>
                            <span class="li-icon">📵</span>
                            <span><strong>Muchas denuncias o bloqueos</strong> — si los usuarios marcan “Reportar” o “Bloquear” con frecuencia, baja tu calificación de calidad</span>
                            <span class="risk-level risk-high">Alto</span>
                        </li>
                        <li>
                            <span class="li-icon">📋</span>
                            <span><strong>Plantillas rechazadas o mal usadas</strong> — contenido promocional en plantilla de utilidad, enlaces no permitidos, categoría incorrecta</span>
                            <span class="risk-level risk-high">Alto</span>
                        </li>
                        <li>
                            <span class="li-icon">📱</span>
                            <span><strong>Usar WhatsApp personal para negocio a escala</strong> — migrar un número con historial de uso irregular a API sin cumplir políticas</span>
                            <span class="risk-level risk-med">Medio</span>
                        </li>
                        <li>
                            <span class="li-icon">🔴</span>
                            <span><strong>Calidad del número en rojo</strong> — Meta restringe envíos; persistir puede llevar a suspensión del Business Manager</span>
                            <span class="risk-level risk-high">Alto</span>
                        </li>
                        <li>
                            <span class="li-icon">⚖️</span>
                            <span><strong>Contenido prohibido</strong> — productos/servicios vetados, información engañosa, suplantación de identidad</span>
                            <span class="risk-level risk-high">Alto</span>
                        </li>
                        <li>
                            <span class="li-icon">🤖</span>
                            <span><strong>Automatización agresiva</strong> — respuestas repetitivas, mensajes fuera de horario sin opt-in, bots que no ofrecen salida a humano</span>
                            <span class="risk-level risk-med">Medio</span>
                        </li>
                        <li>
                            <span class="li-icon">💸</span>
                            <span><strong>Impago con Meta/proveedor</strong> — deuda en la cuenta de API puede cortar el servicio de mensajería</span>
                            <span class="risk-level risk-med">Medio</span>
                        </li>
                    </ul>

                    <div class="disclaimer-box">
                        <strong>Consecuencias posibles:</strong> límite de envíos diarios, rechazo de plantillas, número desconectado de la API, restricción del WhatsApp Business Manager o <strong>pérdida definitiva del número</strong>. Recuperar un número bloqueado no siempre es posible.
                    </div>

                    <div class="tips-list">
                        <h4>✅ Cómo reducir el riesgo</h4>
                        <ul>
                            <li>Solo escribe a contactos que te hayan escrito antes o hayan aceptado recibir mensajes.</li>
                            <li>Usa plantillas masivas (plan Pro) con moderación y segmentación, no listas frías.</li>
                            <li>Ofrece siempre opción de hablar con un humano y de dejar de recibir promociones.</li>
                            <li>Revisa la calidad del número en Meta Business Suite regularmente.</li>
                            <li>Asegura que tus plantillas coinciden con su categoría (marketing vs utilidad).</li>
                            <li>No compartas el mismo número para spam y soporte sin estrategia clara.</li>
                            <li>Mantén saldo o método de pago activo en la cuenta de Meta/API.</li>
                        </ul>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="faq">
        <h2>Preguntas frecuentes</h2>

        <div class="faq-item">
            <button type="button" class="faq-q">¿Qué gano con el panel web que no tengo en WhatsApp del celular?<span class="arrow">▼</span></button>
            <div class="faq-a">
                <p>En el móvil solo ves chats uno por uno. En el <strong>panel admin</strong> tienes:</p>
                <ul>
                    <li><strong>Historial completo</strong> de cada conversación (cliente, bot y asesor)</li>
                    <li><strong>Reportes</strong> de cuántos clientes escribieron y cuántos pedidos se generaron</li>
                    <li><strong>Trazabilidad</strong> de cada venta: número de pedido, productos, pagos y estados</li>
                    <li><strong>Listado centralizado</strong> de contactos por número, sin depender de un solo teléfono</li>
                </ul>
                <p>Todo tu equipo puede operar desde el navegador con la misma información.</p>
            </div>
        </div>

        <div class="faq-item">
            <button type="button" class="faq-q">¿El precio del plan incluye los mensajes de WhatsApp?<span class="arrow">▼</span></button>
            <div class="faq-a">
                <p><strong>No.</strong> El plan ($60, $90 o $130+, sin IVA) cubre únicamente la plataforma: bot, panel admin, usuarios y funciones descritas. Por separado debes pagar a Meta (directamente o vía proveedor BSP) por:</p>
                <ul>
                    <li>Conversaciones entrantes y salientes</li>
                    <li>Plantillas de marketing, utilidad y autenticación</li>
                    <li>Mantenimiento del número en WhatsApp Business API</li>
                </ul>
                <p>El monto depende del país, la categoría del mensaje y cuántos contactos escribes. Un bot con alto tráfico puede generar una factura de Meta mayor que el plan de software.</p>
            </div>
        </div>

        <div class="faq-item">
            <button type="button" class="faq-q">¿Cuánto cuestan las plantillas masivas en el plan Pro?<span class="arrow">▼</span></button>
            <div class="faq-a">
                <p>Las plantillas masivas no tienen costo en nuestro plan Pro — <strong>la función está incluida</strong>. Pero cada envío exitoso genera un cargo de Meta según la categoría de la plantilla (marketing suele ser la más cara). Antes de una campaña grande, estima: (contactos × tarifa Meta por país). Nosotros no controlamos ni absorbemos esos cargos.</p>
            </div>
        </div>

        <div class="faq-item">
            <button type="button" class="faq-q">¿Pueden bloquearme el WhatsApp al usar el bot?<span class="arrow">▼</span></button>
            <div class="faq-a">
                <p><strong>Sí, es un riesgo real.</strong> Meta puede limitar o bloquear números que violen sus políticas. Causas frecuentes:</p>
                <ul>
                    <li>Enviar promociones a personas que no te contactaron (spam)</li>
                    <li>Alta tasa de reportes y bloqueos por parte de usuarios</li>
                    <li>Plantillas mal categorizadas o con contenido no permitido</li>
                    <li>Calidad del número en estado amarillo o rojo sin corregir</li>
                </ul>
                <p>La plataforma facilita el envío responsable, pero <strong>el cumplimiento de las políticas de Meta es responsabilidad del titular del negocio</strong>. Un bloqueo puede implicar perder el número y no siempre hay recuperación.</p>
            </div>
        </div>

        <div class="faq-item">
            <button type="button" class="faq-q">¿Qué pasa si me bloquean mientras pago el plan?<span class="arrow">▼</span></button>
            <div class="faq-a">
                <p>El plan de plataforma y los cargos de Meta son independientes. Si Meta suspende tu número, el bot deja de enviar/recibir mensajes hasta regularizar la situación con Meta — aunque sigas pagando el plan. Te asesoramos en buenas prácticas, pero no podemos revertir sanciones de Meta ni garantizar la continuidad del número.</p>
            </div>
        </div>

        <div class="faq-item">
            <button type="button" class="faq-q">¿Qué significa que Starter no incluye plantillas masivas?<span class="arrow">▼</span></button>
            <div class="faq-a"><p>En el plan Starter el bot responde cuando el cliente escribe (conversación entrante). No puedes enviar campañas masivas ni plantillas aprobadas por Meta a listas de contactos. Eso está disponible desde el plan Pro.</p></div>
        </div>

        <div class="faq-item">
            <button type="button" class="faq-q">¿Qué son las imágenes del cliente en el plan Pro?<span class="arrow">▼</span></button>
            <div class="faq-a"><p>El cliente puede enviar fotos o PDF por WhatsApp — por ejemplo comprobantes de transferencia, documentos o capturas. El bot los procesa dentro del flujo de venta. En Starter solo recibes texto; las imágenes entrantes no activan flujos automáticos.</p></div>
        </div>

        <div class="faq-item">
            <button type="button" class="faq-q">¿Por qué Enterprise dice "desde $130"?<span class="arrow">▼</span></button>
            <div class="faq-a"><p>El precio base cubre integraciones estándar, reportes y soporte personalizado. Si necesitas conexiones complejas (ERP custom, múltiples APIs, desarrollos a medida), el valor se ajusta según alcance. Los ajustes menores (textos, botones, campos del flujo) van incluidos.</p></div>
        </div>

        <div class="faq-item">
            <button type="button" class="faq-q">¿Puedo cambiar de plan después?<span class="arrow">▼</span></button>
            <div class="faq-a"><p>Sí. Puedes subir de Starter a Pro o Enterprise en cualquier momento. Al bajar de plan, las funciones no incluidas se desactivan pero tus datos (contactos, pedidos, productos) se conservan.</p></div>
        </div>

        <div class="faq-item">
            <button type="button" class="faq-q">¿El precio incluye la API de WhatsApp Business?<span class="arrow">▼</span></button>
            <div class="faq-a"><p>No. Los planes cubren la plataforma (bot + panel). Los costos de Meta se facturan aparte. Usa la pestaña <strong>Costos Meta</strong> para estimar tu gasto mensual.</p></div>
        </div>
    </section>

    <section class="demo-section" id="demo">
        <h2>Pruébalo antes de contratar</h2>
        <p class="demo-lead">Comprueba en minutos cómo responde el bot y cómo se ve tu operación en el panel — sin compromiso.</p>
        <div class="demo-actions">
            <div class="demo-actions-row">
                @if($demoBotUrl)
                <a href="{{ $demoBotUrl }}" class="demo-btn demo-btn-wa" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.884 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Probar bot en WhatsApp
                </a>
                @endif
                <a href="{{ $demoPanelUrl }}" class="demo-btn demo-btn-panel">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                    Probar panel web
                </a>
            </div>
            <div class="demo-creds">
                <span class="demo-creds-label">Acceso demo del panel</span>
                Usuario: <strong>{{ $demoPanelUser }}</strong> · contraseña precargada al entrar
            </div>
        </div>
    </section>

    <p class="footnote">
        Precios sin IVA · No incluyen consumos Meta/WhatsApp ·
        <a href="{{ route('public.resumen') }}">Ver qué incluye la plataforma</a> ·
        <a href="#" data-goto="meta">Costos Meta</a>
    </p>

    <footer class="page-footer">
        ChatbotVentas · By Siglo Tecnológico
    </footer>
</div>

<div class="selection-bar visible" id="selectionBar">
    <div class="selection-bar-inner">
        <div class="selection-info">
            Plan seleccionado: <strong id="selectedPlanName">Pro</strong> —
            <strong id="selectedPlanPrice">$90</strong>/mes <span style="font-weight:500;color:var(--muted)">sin IVA</span>
        </div>
        <div class="selection-actions">
            <button type="button" class="close-sel" id="clearSelection">Cambiar</button>
            <a href="{{ $whatsappUrls['pro'] }}" id="confirmPlan" class="confirm-wa" target="_blank" rel="noopener">Contratar por WhatsApp</a>
        </div>
    </div>
</div>

<script>
(function () {
    const planNames = { starter: 'Starter', pro: 'Pro', enterprise: 'Enterprise' };
    const whatsappUrls = @json($whatsappUrls);
    const metaRates = @json($metaRates['per_conversation'] ?? []);
    const marketingEnabled = @json($marketingEnabled ?? false);

    let selectedPlan = 'pro';

    const cards = document.querySelectorAll('.plan-card');
    const viewBtns = document.querySelectorAll('[data-view]');
    const cardsPanel = document.getElementById('cardsPanel');
    const comparisonPanel = document.getElementById('comparisonPanel');
    const metaPanel = document.getElementById('metaPanel');
    const costsPanel = document.getElementById('costsPanel');
    const selectionBar = document.getElementById('selectionBar');
    const selectedPlanName = document.getElementById('selectedPlanName');
    const selectedPlanPrice = document.getElementById('selectedPlanPrice');
    const confirmPlan = document.getElementById('confirmPlan');

    function getPrice(card) {
        return parseInt(card.dataset.price, 10);
    }

    function selectPlan(planId) {
        selectedPlan = planId;
        cards.forEach(c => c.classList.toggle('selected', c.dataset.plan === planId));
        const card = document.querySelector(`.plan-card[data-plan="${planId}"]`);
        if (card) {
            selectedPlanName.textContent = planNames[planId];
            const price = getPrice(card);
            selectedPlanPrice.textContent = planId === 'enterprise' ? 'desde $' + price : '$' + price;
            if (confirmPlan && whatsappUrls[planId]) {
                confirmPlan.href = whatsappUrls[planId];
            }
        }
        selectionBar.classList.add('visible');
    }

    function switchView(view) {
        viewBtns.forEach(b => b.classList.toggle('active', b.dataset.view === view));
        cardsPanel.classList.toggle('hidden', view !== 'cards');
        comparisonPanel.classList.toggle('active', view === 'comparison');
        metaPanel.classList.toggle('active', view === 'meta');
        costsPanel.classList.toggle('active', view === 'costs');
        document.querySelector('.faq')?.classList.toggle('hidden', view !== 'cards');
        document.querySelector('.demo-section')?.classList.toggle('hidden', view !== 'cards');
        document.querySelector('.footnote')?.classList.toggle('hidden', view !== 'cards');
    }

    viewBtns.forEach(btn => {
        btn.addEventListener('click', () => switchView(btn.dataset.view));
    });

    document.querySelectorAll('[data-goto]').forEach(el => {
        el.addEventListener('click', e => {
            e.preventDefault();
            switchView(el.dataset.goto);
            setTimeout(() => document.getElementById('costos-meta')?.scrollIntoView({ behavior: 'smooth' }), 100);
        });
    });

    cards.forEach(card => {
        card.addEventListener('click', e => {
            if (e.target.closest('.plan-cta')) return;
            selectPlan(card.dataset.plan);
        });
    });

    document.getElementById('clearSelection')?.addEventListener('click', () => {
        selectionBar.classList.remove('visible');
        cards.forEach(c => c.classList.remove('selected'));
    });

    document.querySelectorAll('.faq-q').forEach(btn => {
        btn.addEventListener('click', () => {
            const item = btn.closest('.faq-item');
            const wasOpen = item.classList.contains('open');
            document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
            if (!wasOpen) item.classList.add('open');
        });
    });

    // Calculadora Meta
    const calcService = document.getElementById('calcService');
    const calcUtility = document.getElementById('calcUtility');
    const calcMarketing = document.getElementById('calcMarketing');
    const calcPlan = document.getElementById('calcPlan');

    function fmtUsd(amount) {
        return '$' + Number(amount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function fmtUsdRange(min, max) {
        return fmtUsd(min) + ' <span class="range-sep">–</span> ' + fmtUsd(max);
    }

    function estimateMeta(service, utility, marketing) {
        const s = metaRates.service || { min: 0.016, max: 0.029 };
        const u = metaRates.utility || { min: 0.036, max: 0.055 };
        let min = service * s.min + utility * u.min;
        let max = service * s.max + utility * u.max;
        if (marketingEnabled && metaRates.marketing) {
            const m = metaRates.marketing;
            min += marketing * m.min;
            max += marketing * m.max;
        }
        return {
            min: Math.round(min * 100) / 100,
            max: Math.round(max * 100) / 100
        };
    }

    function updateCalculator() {
        if (!calcService) return;
        const service = parseInt(calcService.value, 10);
        const utility = parseInt(calcUtility.value, 10);
        const marketing = marketingEnabled && calcMarketing ? parseInt(calcMarketing.value, 10) : 0;
        const platform = parseInt(calcPlan.value, 10);

        document.getElementById('valService').textContent = service.toLocaleString('es-EC');
        document.getElementById('valUtility').textContent = utility.toLocaleString('es-EC');
        if (marketingEnabled && document.getElementById('valMarketing')) {
            document.getElementById('valMarketing').textContent = marketing.toLocaleString('es-EC');
        }

        const meta = estimateMeta(service, utility, marketing);
        document.getElementById('totalPlatform').textContent = fmtUsd(platform);
        document.getElementById('totalMeta').innerHTML = fmtUsdRange(meta.min, meta.max);
        document.getElementById('totalGrand').innerHTML =
            fmtUsdRange(platform + meta.min, platform + meta.max) + '<span style="font-size:1rem;color:var(--muted);font-weight:500;"> /mes</span>';
    }

    const calcInputs = [calcService, calcUtility, calcPlan];
    if (marketingEnabled && calcMarketing) calcInputs.push(calcMarketing);
    calcInputs.forEach(el => {
        el?.addEventListener('input', updateCalculator);
        el?.addEventListener('change', updateCalculator);
    });

    document.querySelectorAll('.scenario-card').forEach(card => {
        card.addEventListener('click', () => {
            document.querySelectorAll('.scenario-card').forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            calcService.value = card.dataset.service;
            calcUtility.value = card.dataset.utility;
            if (marketingEnabled && calcMarketing) {
                calcMarketing.value = card.dataset.marketing || 0;
            }
            updateCalculator();
        });
    });

    updateCalculator();
})();
</script>

</body>
</html>
