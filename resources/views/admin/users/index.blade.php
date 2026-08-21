@extends('admin.layouts.app')

@section('header', 'Usuarios')

@section('content')
@php
    $isToday = $date->isSameDay(now());
    $dateLabel = $isToday ? 'Hoy' : $date->translatedFormat('d M Y');
@endphp

<style>
    .users-page {
        --u-accent: #128c7e;
        --u-accent-dark: #075e54;
        --u-surface: #ffffff;
        --u-border: #e8ecf1;
        --u-muted: #64748b;
        --u-text: #0f172a;
        max-width: 1100px;
    }

    .users-top {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    .users-top h2 {
        margin: 0 0 .35rem;
        font-size: 1.45rem;
        font-weight: 700;
        color: var(--u-text);
        letter-spacing: -0.02em;
    }

    .users-top .lead {
        margin: 0;
        color: var(--u-muted);
        font-size: .9rem;
        max-width: 520px;
    }

    .users-top-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        align-items: center;
    }

    .btn-u-primary {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: .55rem 1rem;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--u-accent) 0%, var(--u-accent-dark) 100%);
        color: #fff !important;
        font-size: .875rem;
        font-weight: 600;
        text-decoration: none;
        border: none;
        box-shadow: 0 4px 14px rgba(18, 140, 126, .28);
        transition: transform .15s, box-shadow .15s;
    }

    .btn-u-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(18, 140, 126, .35);
        color: #fff;
    }

    .users-summary {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: .75rem;
        margin-bottom: 1rem;
    }

    @media (max-width: 900px) {
        .users-summary { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 480px) {
        .users-summary { grid-template-columns: 1fr; }
    }

    .u-stat {
        background: var(--u-surface);
        border: 1px solid var(--u-border);
        border-radius: 14px;
        padding: 1rem 1.1rem;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .04);
    }

    .u-stat .lbl {
        font-size: .72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--u-muted);
    }

    .u-stat .val {
        font-size: 1.65rem;
        font-weight: 800;
        color: var(--u-text);
        line-height: 1.2;
        margin-top: .15rem;
        letter-spacing: -0.03em;
    }

    .u-stat .sub {
        font-size: .75rem;
        color: #94a3b8;
        margin-top: .15rem;
    }

    .u-stat.accent {
        background: linear-gradient(145deg, #f0fdfa 0%, #ecfdf5 100%);
        border-color: #99f6e4;
    }

    .users-toolbar {
        background: var(--u-surface);
        border: 1px solid var(--u-border);
        border-radius: 14px;
        padding: .85rem 1rem;
        margin-bottom: 1rem;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .75rem;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .04);
    }

    .users-toolbar label {
        font-size: .82rem;
        font-weight: 600;
        color: var(--u-muted);
        margin: 0;
    }

    .users-toolbar input[type="date"] {
        border: 1px solid var(--u-border);
        border-radius: 8px;
        padding: .4rem .65rem;
        font-size: .875rem;
        color: var(--u-text);
    }

    .btn-u-ghost {
        border: 1px solid var(--u-border);
        background: #f8fafc;
        color: var(--u-text);
        border-radius: 8px;
        padding: .4rem .85rem;
        font-size: .82rem;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-u-ghost:hover { background: #f1f5f9; }

    .users-list {
        display: flex;
        flex-direction: column;
        gap: .75rem;
    }

    .user-card {
        background: var(--u-surface);
        border: 1px solid var(--u-border);
        border-radius: 16px;
        padding: 1rem 1.15rem;
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 1rem 1.25rem;
        align-items: center;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .04);
        transition: box-shadow .2s, border-color .2s;
    }

    .user-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
    }

    .user-card.inactive {
        opacity: .72;
        background: #fafafa;
    }

    @media (max-width: 768px) {
        .user-card {
            grid-template-columns: auto 1fr;
        }
        .user-card-actions { grid-column: 1 / -1; justify-content: flex-end; }
        .user-metrics { grid-column: 1 / -1; }
    }

    .user-avatar {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--u-accent) 0%, #25d366 100%);
        color: #fff;
        font-weight: 800;
        font-size: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        letter-spacing: -0.02em;
    }

    .user-card.inactive .user-avatar {
        background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
    }

    .user-main-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem .65rem;
        margin-bottom: .2rem;
    }

    .user-name {
        font-size: 1rem;
        font-weight: 700;
        color: var(--u-text);
        margin: 0;
        letter-spacing: -0.02em;
    }

    .user-role {
        font-size: .72rem;
        font-weight: 600;
        padding: .2rem .55rem;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
    }

    .user-role.super { background: #fef3c7; color: #92400e; }
    .user-role.admin { background: #dbeafe; color: #1e40af; }
    .user-role.agent { background: #dcfce7; color: #166534; }

    .user-status {
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        padding: .2rem .5rem;
        border-radius: 999px;
    }

    .user-status.on { background: #dcfce7; color: #15803d; }
    .user-status.off { background: #fee2e2; color: #b91c1c; }

    .user-meta {
        font-size: .82rem;
        color: var(--u-muted);
        display: flex;
        flex-wrap: wrap;
        gap: .35rem .75rem;
    }

    .user-meta span {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
    }

    .user-meta .uname {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: .78rem;
        color: #334155;
        background: #f1f5f9;
        padding: .1rem .4rem;
        border-radius: 4px;
    }

    .user-metrics {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
    }

    .u-metric {
        min-width: 72px;
        text-align: center;
        padding: .45rem .6rem;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #eef2f7;
    }

    .u-metric .n {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--u-text);
        line-height: 1;
    }

    .u-metric .n.zero { color: #cbd5e1; }

    .u-metric .t {
        font-size: .62rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #94a3b8;
        margin-top: .2rem;
    }

    .u-metric.highlight {
        background: #ecfdf5;
        border-color: #a7f3d0;
    }

    .u-metric.clickable {
        cursor: pointer;
        transition: transform .12s, box-shadow .12s;
    }

    .u-metric.clickable:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(18, 140, 126, .15);
    }

    .u-metric.clickable .t::after {
        content: ' ▾';
        font-size: .55rem;
        opacity: .7;
    }

    .user-activity-detail {
        margin-top: .75rem;
        padding: .75rem;
        background: #f8fafc;
        border: 1px solid #e8ecf1;
        border-radius: 12px;
    }

    .user-activity-detail.collapsed {
        display: none;
    }

    .u-detail-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: .5rem;
        font-size: .78rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .u-client-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: .4rem;
    }

    .u-client-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .55rem .65rem;
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 10px;
    }

    .u-client-item .who {
        min-width: 0;
    }

    .u-client-item .who strong {
        display: block;
        font-size: .875rem;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .u-client-item .who small {
        color: #64748b;
        font-size: .78rem;
    }

    .u-client-tags {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        align-items: center;
        flex-shrink: 0;
    }

    .u-tag {
        font-size: .68rem;
        font-weight: 600;
        padding: .15rem .45rem;
        border-radius: 999px;
        background: #e2e8f0;
        color: #475569;
    }

    .u-tag.msg { background: #dbeafe; color: #1d4ed8; }
    .u-tag.agent { background: #fef3c7; color: #b45309; }

    .u-client-link {
        font-size: .75rem;
        color: var(--u-accent-dark);
        text-decoration: none;
        font-weight: 600;
        white-space: nowrap;
    }

    .u-client-link:hover { text-decoration: underline; }

    .user-card-actions {
        display: flex;
        gap: .4rem;
        align-items: center;
    }

    .u-icon-btn {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        border: 1px solid var(--u-border);
        background: #fff;
        color: #475569;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        text-decoration: none;
        transition: background .15s, color .15s, border-color .15s;
    }

    .u-icon-btn:hover {
        background: #f0fdfa;
        border-color: #99f6e4;
        color: var(--u-accent-dark);
    }

    .u-icon-btn.warn:hover {
        background: #fffbeb;
        border-color: #fcd34d;
        color: #b45309;
    }

    .u-icon-btn.ok:hover {
        background: #ecfdf5;
        border-color: #6ee7b7;
        color: #047857;
    }

    .users-empty {
        text-align: center;
        padding: 3rem 1.5rem;
        background: var(--u-surface);
        border: 1px dashed var(--u-border);
        border-radius: 16px;
        color: var(--u-muted);
    }

    .users-footnote {
        margin-top: 1rem;
        padding: .85rem 1rem;
        background: #f8fafc;
        border-radius: 10px;
        font-size: .78rem;
        color: var(--u-muted);
        line-height: 1.5;
    }

    .alert-u {
        border-radius: 12px;
        padding: .75rem 1rem;
        margin-bottom: 1rem;
        font-size: .875rem;
    }

    .alert-u.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-u.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
</style>

<div class="users-page">
    <div class="users-top">
        <div>
            <h2>Equipo del panel</h2>
            <p class="lead">Accesos, contraseñas y rendimiento diario de quienes atienden chats.</p>
        </div>
        <div class="users-top-actions">
            @perm('users.create')
                <a href="{{ route('admin.users.create') }}" class="btn-u-primary">
                    <i class="fas fa-user-plus"></i> Nuevo usuario
                </a>
            @endperm
        </div>
    </div>

    @if(session('success'))
        <div class="alert-u success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-u error">{{ session('error') }}</div>
    @endif

    <div class="users-summary">
        <div class="u-stat">
            <div class="lbl">Usuarios</div>
            <div class="val">{{ $summary['total'] }}</div>
            <div class="sub">{{ $summary['active'] }} activos</div>
        </div>
        <div class="u-stat accent">
            <div class="lbl">Mensajes · {{ $dateLabel }}</div>
            <div class="val">{{ $summary['messages_today'] }}</div>
            <div class="sub">Enviados como humano</div>
        </div>
        <div class="u-stat accent">
            <div class="lbl">Clientes · {{ $dateLabel }}</div>
            <div class="val">{{ $summary['clients_today'] }}</div>
            <div class="sub">Atendidos en total</div>
        </div>
        <div class="u-stat">
            <div class="lbl">Inactivos</div>
            <div class="val">{{ $summary['total'] - $summary['active'] }}</div>
            <div class="sub">Sin acceso al panel</div>
        </div>
    </div>

    <form method="get" action="{{ route('admin.users.index') }}" class="users-toolbar">
        <label for="activity-date"><i class="far fa-calendar-alt me-1"></i> Métricas del día</label>
        <input type="date" id="activity-date" name="date" value="{{ $date->format('Y-m-d') }}">
        <button type="submit" class="btn-u-ghost">Actualizar</button>
        @if(!$isToday)
            <a href="{{ route('admin.users.index') }}" class="btn-u-ghost text-decoration-none">Ver hoy</a>
        @endif
    </form>

    <div class="users-list">
        @forelse($users as $user)
            @php
                $day = $stats[$user->id] ?? ['messages_sent' => 0, 'clients_served' => 0, 'agent_requests_closed' => 0, 'clients' => []];
                $clients = $day['clients'] ?? [];
                $hasActivity = ($day['messages_sent'] ?? 0) > 0 || ($day['clients_served'] ?? 0) > 0;
                $roleSlug = $user->roleModel?->slug ?? $user->role ?? 'admin';
                $roleClass = match ($roleSlug) {
                    'super_admin' => 'super',
                    'agent' => 'agent',
                    default => 'admin',
                };
                $initials = collect(explode(' ', $user->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('');
            @endphp
            <article class="user-card {{ !$user->isActive() ? 'inactive' : '' }}">
                <div class="user-avatar" aria-hidden="true">{{ strtoupper($initials) }}</div>

                <div class="user-info">
                    <div class="user-main-head">
                        <h3 class="user-name">{{ $user->name }}</h3>
                        <span class="user-role {{ $roleClass }}">{{ $user->roleLabel() }}</span>
                        <span class="user-status {{ $user->isActive() ? 'on' : 'off' }}">
                            {{ $user->isActive() ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                    <div class="user-meta">
                        <span><span class="uname">{{ $user->username }}</span></span>
                        <span><i class="far fa-envelope"></i> {{ $user->email }}</span>
                    </div>
                    <div class="user-metrics mt-2">
                        <div class="u-metric {{ $day['messages_sent'] ? 'highlight' : '' }}">
                            <div class="n {{ $day['messages_sent'] ? '' : 'zero' }}">{{ $day['messages_sent'] }}</div>
                            <div class="t">Mensajes</div>
                        </div>
                        <div class="u-metric {{ $day['clients_served'] ? 'highlight' : '' }}">
                            <div class="n {{ $day['clients_served'] ? '' : 'zero' }}">{{ $day['clients_served'] }}</div>
                            <div class="t">Clientes</div>
                        </div>
                        <div class="u-metric">
                            <div class="n {{ $day['agent_requests_closed'] ? '' : 'zero' }}">{{ $day['agent_requests_closed'] }}</div>
                            <div class="t">Asesor</div>
                        </div>
                    </div>

                    @if($hasActivity)
                        @if(count($clients) > 0)
                            <div class="user-activity-detail" id="user-activity-{{ $user->id }}">
                                <div class="u-detail-head">
                                    <span><i class="fas fa-users me-1"></i> Clientes atendidos · {{ $dateLabel }}</span>
                                </div>
                                <ul class="u-client-list">
                                    @foreach($clients as $client)
                                        <li class="u-client-item">
                                            <div class="who">
                                                <strong>{{ $client['name'] ?: 'Sin nombre' }}</strong>
                                                <small>{{ $client['phone'] }}</small>
                                            </div>
                                            <div class="u-client-tags">
                                                @if($client['messages'] > 0)
                                                    <span class="u-tag msg">{{ $client['messages'] }} msg</span>
                                                @endif
                                                @if($client['agent_closed'])
                                                    <span class="u-tag agent">Asesor</span>
                                                @endif
                                                <a href="{{ route('admin.chat', $client['id']) }}" class="u-client-link">Abrir chat</a>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <div class="user-activity-detail" style="font-size:.82rem;color:#64748b;">
                                <i class="fas fa-info-circle me-1"></i>
                                Hay actividad registrada ({{ $day['clients_served'] }} cliente(s)) pero no se encontraron los contactos en la base de datos.
                            </div>
                        @endif
                    @endif
                </div>

                <div class="user-card-actions">
                    @perm('users.update')
                        <a href="{{ route('admin.users.edit', $user) }}" class="u-icon-btn" title="Editar y cambiar contraseña">
                            <i class="fas fa-pen"></i>
                        </a>
                        @if($user->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}" class="d-inline"
                                  onsubmit="return confirm('¿{{ $user->isActive() ? 'Desactivar' : 'Activar' }} a {{ $user->name }}?');">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="u-icon-btn {{ $user->isActive() ? 'warn' : 'ok' }}"
                                        title="{{ $user->isActive() ? 'Desactivar acceso' : 'Activar acceso' }}">
                                    <i class="fas fa-{{ $user->isActive() ? 'user-slash' : 'user-check' }}"></i>
                                </button>
                            </form>
                        @endif
                    @endperm
                </div>
            </article>
        @empty
            <div class="users-empty">
                <i class="fas fa-users fa-2x mb-3 opacity-50"></i>
                <p class="mb-0">No hay usuarios en el panel.</p>
            </div>
        @endforelse
    </div>

    <p class="users-footnote mb-0">
        <strong>Clientes</strong> = contactos distintos con mensajes desde el panel o solicitud de asesor cerrada ese día.
        La lista aparece debajo de cada usuario con actividad.
    </p>
</div>
@endsection
