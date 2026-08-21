@extends('admin.layouts.app')

@section('header')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    body.dashboard-page {
        background: linear-gradient(180deg, #eef2f7 0%, #f8fafc 40%, #f1f5f9 100%) !important;
    }
    .dash-wrap { max-width: 1200px; margin: 0 auto; }
    .dash-top {
        display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between;
        gap: 1rem; margin-bottom: 1.25rem;
    }
    .dash-top h1 { font-size: 1.45rem; font-weight: 700; color: #1a1d21; margin: 0 0 .25rem; }
    .dash-top p { margin: 0; color: #6c757d; font-size: .88rem; }
    .dash-filter {
        display: flex; flex-wrap: wrap; align-items: center; gap: .5rem;
        background: #fff; border: 1px solid #e3e7ee; border-radius: 10px; padding: .5rem .75rem;
    }
    .dash-filter input {
        border: 1px solid #dee2e6; border-radius: 8px; padding: .4rem .6rem; font-size: .85rem;
    }
    .dash-filter button {
        background: #128c7e; color: #fff; border: none; border-radius: 8px;
        padding: .45rem 1rem; font-size: .85rem; font-weight: 600; cursor: pointer;
    }
    .hero-cost {
        background: linear-gradient(135deg, #075e54 0%, #128c7e 100%);
        color: #fff; border-radius: 16px; padding: 1.5rem 1.75rem; margin-bottom: 1rem;
        display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: center;
    }
    @media (max-width: 768px) { .hero-cost { grid-template-columns: 1fr; } }
    .hero-cost .label { font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; opacity: .85; margin-bottom: .35rem; }
    .hero-cost .amount { font-size: clamp(1.75rem, 4vw, 2.35rem); font-weight: 800; line-height: 1.15; }
    .hero-cost .sub { font-size: .88rem; opacity: .9; margin-top: .5rem; }
    .hero-cost .month-box {
        background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2);
        border-radius: 12px; padding: 1rem 1.25rem; min-width: 200px;
    }
    .hero-cost .month-box .lbl { font-size: .72rem; opacity: .8; text-transform: uppercase; }
    .hero-cost .month-box .val { font-size: 1.25rem; font-weight: 700; margin-top: .25rem; }
    .strategy-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.25rem; }
    @media (max-width: 768px) { .strategy-grid { grid-template-columns: 1fr; } }
    .strategy-card {
        position: relative; background: #fff; border: 1px solid rgba(15, 23, 42, 0.06);
        border-radius: 16px; padding: 1.15rem 1.25rem 1rem;
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06); overflow: hidden;
    }
    .strategy-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--accent, #128c7e); }
    .strategy-card[data-category="service"] { --accent: #6366f1; }
    .strategy-card[data-category="utility"] { --accent: #0d9488; }
    .strategy-card[data-category="marketing"] { --accent: #ea580c; }
    .strategy-card[data-category="authentication"] { --accent: #7c3aed; }
    .strategy-card .head { display: flex; align-items: flex-start; gap: .85rem; margin-bottom: 1rem; }
    .strategy-card .icon-wrap { width: 44px; height: 44px; border-radius: 12px; background: #f8fafc; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; }
    .strategy-card h3 { font-size: .98rem; font-weight: 700; color: #0f172a; margin: 0 0 .25rem; }
    .strategy-card .desc { font-size: .8rem; color: #64748b; line-height: 1.45; margin: 0; }
    .strategy-card .stats { display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding: .85rem 1rem; border-radius: 12px; background: #f8fafc; }
    .strategy-card .count { font-size: 1.65rem; font-weight: 800; color: #0f172a; line-height: 1; }
    .strategy-card .count-lbl { font-size: .68rem; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin-top: .2rem; font-weight: 600; }
    .strategy-card .cost { font-size: 1rem; font-weight: 800; color: #128c7e; }
    .strategy-card .cost-range { display: block; font-size: .72rem; color: #64748b; margin-top: .1rem; }
    .wa-metrics-row {
        display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.25rem;
    }
    @media (max-width: 992px) { .wa-metrics-row { grid-template-columns: repeat(2, 1fr); } }
    .wa-metric-card {
        background: #fff; border: 1px solid rgba(15, 23, 42, 0.06); border-radius: 14px;
        padding: 1rem 1rem 1rem 3.25rem; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05); position: relative;
    }
    .wa-metric-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: #128c7e; }
    .wa-metric-card .icon {
        position: absolute; left: .85rem; top: 1rem; width: 36px; height: 36px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center; background: #ecfdf5; color: #128c7e;
    }
    .wa-metric-card .lbl { font-size: .68rem; font-weight: 700; text-transform: uppercase; color: #64748b; }
    .wa-metric-card .val { font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-top: .2rem; }
    .wa-metric-card .sub { font-size: .76rem; color: #64748b; margin-top: .35rem; }
    .panel {
        background: #fff; border: 1px solid rgba(15, 23, 42, 0.06); border-radius: 14px;
        padding: 1.1rem 1.25rem; margin-bottom: 1rem; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
    }
    .panel h3 { font-size: .95rem; font-weight: 600; color: #1a1d21; margin: 0 0 1rem; }
    .panel-chart { height: 220px; position: relative; }
    .dash-note { font-size: .75rem; color: #9ca3af; margin-top: .75rem; line-height: 1.5; }
    .dash-note a { color: #128c7e; }
</style>
<script>document.body.classList.add('dashboard-page');</script>
@endsection

@section('content')
@php
    $m = $metrics;
    $c = $consumptionReport;
@endphp

<div class="dash-wrap">
    <div class="dash-top">
        <div>
            <h1>Reportes WhatsApp</h1>
            <p>Consumo Meta y actividad del canal · {{ $from->format('d/m/Y') }} — {{ $to->format('d/m/Y') }}</p>
        </div>
        @include('admin.partials.report-period-filter', ['action' => route('admin.reports.whatsapp')])
    </div>

    <section class="hero-cost" aria-label="Consumo estimado WhatsApp">
        <div>
            <div class="label">Consumo estimado en el período</div>
            <div class="amount">
                ${{ number_format($c['total_min'], 2) }}
                @if($c['total_max'] > $c['total_min'])
                    — ${{ number_format($c['total_max'], 2) }}
                @endif
            </div>
            <div class="sub">
                Costos estimados de WhatsApp · {{ $c['currency'] }}
                · No incluye el plan de plataforma
            </div>
        </div>
        <div class="month-box">
            <div class="lbl">Mes actual ({{ $c['month_label'] }})</div>
            <div class="val">${{ number_format($c['month_min'], 2) }} — ${{ number_format($c['month_max'], 2) }}</div>
            <div class="sub" style="margin-top:.5rem;font-size:.8rem">
                Proyección fin de mes: ~${{ number_format($c['projected_min'], 2) }} — ${{ number_format($c['projected_max'], 2) }}
            </div>
        </div>
    </section>

    <section class="strategy-grid" aria-label="Consumo por tipo de conversación">
        @foreach($c['categories'] as $key => $cat)
            <article class="strategy-card" data-category="{{ $key }}">
                <div class="head">
                    <span class="icon-wrap" aria-hidden="true">{{ $cat['icon'] }}</span>
                    <div>
                        <h3>{{ $cat['label'] }}</h3>
                        <p class="desc">{{ $cat['description'] }}</p>
                    </div>
                </div>
                <div class="stats">
                    <div>
                        <div class="count">{{ number_format($cat['count']) }}</div>
                        <div class="count-lbl">conversaciones est.</div>
                    </div>
                    <div class="text-end">
                        <div class="cost">${{ number_format($cat['cost_min'], 2) }}</div>
                        @if($cat['cost_max'] > $cat['cost_min'])
                            <span class="cost-range">hasta ${{ number_format($cat['cost_max'], 2) }}</span>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </section>

    <section class="wa-metrics-row" aria-label="Actividad del canal">
        <div class="wa-metric-card">
            <span class="icon"><i class="fas fa-comments"></i></span>
            <div class="lbl">Mensajes</div>
            <div class="val">{{ number_format($m['period_messages']) }}</div>
            <div class="sub">{{ $m['message_growth'] >= 0 ? '+' : '' }}{{ $m['message_growth'] }}% vs anterior</div>
        </div>
        <div class="wa-metric-card">
            <span class="icon"><i class="fas fa-headset"></i></span>
            <div class="lbl">Atención</div>
            <div class="val">{{ number_format($m['response_rate'], 0) }}%</div>
            <div class="sub">Respuesta en 24 h · {{ $m['avg_response_time_formatted'] }}</div>
        </div>
        <div class="wa-metric-card">
            <span class="icon"><i class="fas fa-users"></i></span>
            <div class="lbl">Contactos activos</div>
            <div class="val">{{ number_format($m['active_clients']) }}</div>
            <div class="sub">{{ number_format($m['new_contacts']) }} nuevos</div>
        </div>
        <div class="wa-metric-card">
            <span class="icon"><i class="fas fa-robot"></i></span>
            <div class="lbl">Bot / humano</div>
            <div class="val">{{ number_format($m['bot_messages']) }} / {{ number_format($m['human_messages']) }}</div>
            <div class="sub">Recibidos: {{ number_format($m['received']) }} · Enviados: {{ number_format($m['sent']) }}</div>
        </div>
    </section>

    <div class="panel">
        <h3>Tendencia de consumo diario (estimado)</h3>
        <div class="panel-chart">
            <canvas id="consumptionChart"></canvas>
        </div>
        <p class="dash-note">
            Estimación basada en mensajes registrados. Meta puede facturar distinto según categoría y país.
            @perm('pricing_settings.view')
            Tarifas configurables en <a href="{{ route('admin.pricing-settings.edit') }}#costos-meta">Parámetros → Costos Meta</a>.
            @endperm
        </p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const trend = {!! json_encode($c['daily_trend']) !!};
    const labels = trend.length ? trend.map(d => d.label) : ['Sin datos'];
    const data = trend.length ? trend.map(d => d.cost) : [0];

    new Chart(document.getElementById('consumptionChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Consumo est. (USD)',
                data,
                backgroundColor: 'rgba(18, 140, 126, 0.65)',
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => '$' + v } }
            }
        }
    });
});
</script>
@endsection
