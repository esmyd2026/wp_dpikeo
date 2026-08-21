@php
    $usage = $planLimits['usage'] ?? [];
    $compact = $compact ?? false;
@endphp
<div class="plan-limits-widget {{ $compact ? 'plan-limits-widget--compact' : '' }}">
    <style>
        .plan-limits-widget {
            background: #fff;
            border: 1px solid #e3e7ee;
            border-radius: 12px;
            padding: 1rem 1.15rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 4px rgba(0,0,0,.04);
        }
        .plan-limits-widget--compact { padding: .85rem 1rem; }
        .plan-limits-widget .plw-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            margin-bottom: .85rem;
        }
        .plan-limits-widget .plw-head h3 {
            margin: 0;
            font-size: .95rem;
            font-weight: 700;
            color: #1a1d21;
        }
        .plan-limits-widget .plw-plan {
            font-size: .75rem;
            font-weight: 600;
            color: #128c7e;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 999px;
            padding: .2rem .65rem;
        }
        .plan-limits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: .75rem;
        }
        .plan-limit-item .pl-label {
            display: flex;
            justify-content: space-between;
            font-size: .78rem;
            color: #6c757d;
            margin-bottom: .35rem;
        }
        .plan-limit-item .pl-label strong { color: #212529; }
        .plan-limit-bar {
            height: 8px;
            background: #eef2f7;
            border-radius: 999px;
            overflow: hidden;
        }
        .plan-limit-bar > span {
            display: block;
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #128c7e, #25d366);
            transition: width .3s;
        }
        .plan-limit-item.is-warning .plan-limit-bar > span { background: linear-gradient(90deg, #d97706, #f59e0b); }
        .plan-limit-item.is-danger .plan-limit-bar > span { background: linear-gradient(90deg, #dc2626, #f15c6d); }
        .plan-limit-item.is-danger .pl-label strong { color: #dc2626; }
    </style>

    <div class="plw-head">
        <h3><i class="fas fa-layer-group me-1 text-muted"></i> Cuotas del plan</h3>
        <span class="plw-plan">{{ $planLimits['plan_label'] ?? 'Plan' }}</span>
    </div>

    <div class="plan-limits-grid">
        <div class="plan-limit-item {{ ($planLimits['products_percent'] ?? 0) >= 100 ? 'is-danger' : (($planLimits['products_percent'] ?? 0) >= 80 ? 'is-warning' : '') }}">
            <div class="pl-label">
                <span>Productos</span>
                <strong>{{ $usage['products'] ?? 0 }} / {{ $planLimits['max_products'] ?? 0 }}</strong>
            </div>
            <div class="plan-limit-bar"><span style="width: {{ $planLimits['products_percent'] ?? 0 }}%"></span></div>
        </div>

        <div class="plan-limit-item {{ ($planLimits['categories_percent'] ?? 0) >= 100 ? 'is-danger' : (($planLimits['categories_percent'] ?? 0) >= 80 ? 'is-warning' : '') }}">
            <div class="pl-label">
                <span>Categorías</span>
                <strong>{{ $usage['categories'] ?? 0 }} / {{ $planLimits['max_categories'] ?? 0 }}</strong>
            </div>
            <div class="plan-limit-bar"><span style="width: {{ $planLimits['categories_percent'] ?? 0 }}%"></span></div>
        </div>

        <div class="plan-limit-item {{ ($planLimits['storage_percent'] ?? 0) >= 100 ? 'is-danger' : (($planLimits['storage_percent'] ?? 0) >= 80 ? 'is-warning' : '') }}">
            <div class="pl-label">
                <span>Espacio en servidor</span>
                <strong>{{ $usage['storage_human'] ?? '0 B' }} / {{ number_format($planLimits['storage_gb'] ?? 0, 0) }} GB</strong>
            </div>
            <div class="plan-limit-bar"><span style="width: {{ max(1, $planLimits['storage_percent'] ?? 0) }}%"></span></div>
        </div>
    </div>
</div>
