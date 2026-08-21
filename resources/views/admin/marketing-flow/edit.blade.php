@extends('admin.layouts.app')

@push('styles')
@php
    $flowPrimary = $chatbotConfig?->primary_color ?? '#128c7e';
    $flowSecondary = $chatbotConfig?->secondary_color ?? '#075e54';
@endphp
<style>
:root {
    --flow-bot-primary: {{ $flowPrimary }};
    --flow-bot-secondary: {{ $flowSecondary }};
}
/* ── Página flujo: aislar de Tailwind global ── */
body.flow-builder-page .top-navbar { display: none; }
body.flow-builder-page .main-content {
    padding: 0;
    background: #eef1f4;
}
body.flow-builder-page .content-header {
    margin: 0;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #dee2e6;
}

.flow-builder-root {
    padding: 1.25rem 1.5rem 2rem;
    max-width: 1600px;
    margin: 0 auto;
}

/* Toolbar */
.flow-builder-toolbar {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.1rem 1.35rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
}

/* Grid 3 columnas */
.flow-builder-grid {
    display: grid;
    grid-template-columns: 272px minmax(0, 1fr) 300px;
    gap: 1.25rem;
    align-items: start;
}

/* Nav pasos */
.flow-steps-nav {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    position: sticky;
    top: 1rem;
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
}
.flow-steps-nav-header {
    padding: .9rem 1rem;
    background: linear-gradient(135deg, var(--flow-bot-secondary), var(--flow-bot-primary));
    color: #fff;
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.flow-scenario-block { border-bottom: 1px solid #f1f5f9; }
.flow-scenario-block:last-child { border-bottom: 0; }
.flow-scenario-title {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #64748b;
    padding: .85rem 1rem .4rem;
    display: flex;
    align-items: center;
    gap: .45rem;
}
.flow-scenario-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}
.flow-step-nav {
    display: flex;
    align-items: flex-start;
    gap: .7rem;
    width: 100%;
    border: 0;
    background: transparent;
    text-align: left;
    padding: .7rem 1rem;
    cursor: pointer;
    transition: background .15s, border-color .15s;
    border-left: 3px solid transparent;
}
.flow-step-nav:hover { background: #f8fafc; }
.flow-step-nav.active {
    background: color-mix(in srgb, var(--flow-bot-primary) 12%, white);
    border-left-color: var(--flow-bot-primary);
}
.flow-step-nav.disabled-step { opacity: .5; }
.flow-step-nav-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #475569;
    flex-shrink: 0;
    font-size: .82rem;
}
.flow-step-nav.active .flow-step-nav-icon {
    background: var(--flow-bot-primary);
    color: #fff;
}
.flow-step-nav-label {
    font-size: .84rem;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.3;
}
.flow-step-nav-meta {
    font-size: .7rem;
    color: #94a3b8;
    margin-top: .15rem;
}
.flow-step-nav-meta .badge-status {
    display: inline-block;
    padding: .1rem .4rem;
    border-radius: 4px;
    font-size: .65rem;
    font-weight: 600;
}
.badge-status.on { background: #dcfce7; color: #166534; }
.badge-status.off { background: #fee2e2; color: #991b1b; }

/* Editor */
.flow-editor-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
    overflow: hidden;
    min-height: 560px;
}
.flow-editor-header {
    padding: 1.1rem 1.35rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    background: #fafbfc;
}
.flow-editor-header h2 {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.flow-editor-header-meta {
    display: flex;
    align-items: center;
    gap: .65rem;
    flex-wrap: wrap;
}
.flow-interactive-badge {
    font-size: .72rem;
    padding: .3rem .65rem;
    border-radius: 999px;
    background: #e0f2fe;
    color: #0369a1;
    font-weight: 600;
}
.flow-step-panel {
    display: none !important;
    padding: 1.35rem;
}
.flow-step-panel.active {
    display: block !important;
}

/* Secciones del formulario */
.flow-section {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.1rem 1.2rem;
    margin-bottom: 1.1rem;
    background: #fff;
}
.flow-section-title {
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #475569;
    margin-bottom: .85rem;
    display: flex;
    align-items: center;
    gap: .45rem;
}
.flow-section-title i { color: #128c7e; }

.flow-var-chip {
    display: inline-block;
    font-size: .74rem;
    padding: .22rem .55rem;
    margin: .12rem;
    border-radius: 6px;
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    cursor: pointer;
    transition: background .12s;
}
.flow-var-chip:hover { background: #dbeafe; }

.flow-save-bar {
    position: sticky;
    bottom: 0;
    z-index: 50;
    background: rgba(255,255,255,.97);
    backdrop-filter: blur(10px);
    border: 1px solid #e2e8f0;
    padding: .9rem 1.25rem;
    margin-top: 1.25rem;
    border-radius: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 -4px 24px rgba(15, 23, 42, .08);
}

/* Preview */
.flow-preview-column {
    position: sticky;
    top: 1rem;
}
.flow-preview-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
}
.wa-preview-label {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #64748b;
    margin-bottom: .65rem;
    font-weight: 700;
}
.wa-phone {
    background: #efeae2;
    border-radius: 22px;
    border: 7px solid #111;
    overflow: hidden;
    box-shadow: 0 10px 32px rgba(0,0,0,.12);
}
.wa-phone-header {
    background: var(--flow-bot-secondary);
    color: #fff;
    padding: .8rem 1rem;
    font-size: .84rem;
    font-weight: 600;
}
.wa-phone-body {
    min-height: 300px;
    max-height: 420px;
    overflow-y: auto;
    padding: 1rem;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23d4cdc4' fill-opacity='0.25'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") #e5ddd5;
}
.wa-bubble {
    background: #fff;
    border-radius: 0 10px 10px 10px;
    padding: .7rem .9rem;
    max-width: 94%;
    box-shadow: 0 1px 2px rgba(0,0,0,.08);
    font-size: .83rem;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-word;
}
.wa-bubble-header { font-weight: 700; font-size: .78rem; margin-bottom: .35rem; }
.wa-bubble-header-image { margin: -.7rem -.9rem .5rem; border-radius: 10px 10px 0 0; overflow: hidden; }
.wa-bubble-header-image img { display: block; width: 100%; max-height: 130px; object-fit: cover; }
.wa-bubble-footer { font-size: .72rem; color: #667781; margin-top: .45rem; }
.wa-btn-preview {
    display: block; text-align: center; padding: .5rem;
    margin-top: .3rem; border-top: 1px solid #e9edef;
    color: #027eb5; font-size: .79rem; font-weight: 500;
}
.wa-list-cta {
    display: block; text-align: center; padding: .55rem; margin-top: .45rem;
    background: #f0f2f5; border-radius: 6px; color: #027eb5; font-size: .79rem;
}
.flow-actions-legend {
    margin-top: 1rem;
    max-height: 200px;
    overflow-y: auto;
    font-size: .76rem;
    color: #64748b;
}
.flow-actions-legend code { font-size: .7rem; color: #0f172a; }

.flow-hint {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 10px;
    padding: .75rem 1rem;
    font-size: .82rem;
    color: #166534;
    margin-bottom: 1rem;
}
.flow-hint.info { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
.flow-hint.catalog { background: #f5f3ff; border-color: #ddd6fe; color: #5b21b6; }

@media (max-width: 1200px) {
    .flow-builder-grid { grid-template-columns: 240px minmax(0, 1fr); }
    .flow-preview-column {
        grid-column: 1 / -1;
        display: grid;
        grid-template-columns: 1fr 300px;
        gap: 1rem;
    }
}
@media (max-width: 768px) {
    .flow-builder-root { padding: 1rem; }
    .flow-builder-grid { grid-template-columns: 1fr; }
    .flow-preview-column { grid-template-columns: 1fr; }
    .flow-steps-nav { max-height: 280px; position: static; }
}
</style>
@endpush

@section('content')
<div class="content-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-project-diagram text-success me-2"></i>Flujo del bot</h1>
        <p class="text-muted mb-0 small">Configure mensajes, botones y acciones. Seleccione un paso a la izquierda.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('admin.marketing-flow.graph.edit') }}" class="btn btn-sm btn-outline-dark">
            <i class="fas fa-diagram-project me-1"></i>Ver como flujo visual (beta)
        </a>
        @if($profile)
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                <i class="fab fa-whatsapp me-1"></i>{{ $profile->business_name }}
            </span>
        @endif
    </div>
</div>

<div class="flow-builder-root">
@if(!$profile)
    <div class="alert alert-warning">Configure primero el perfil de WhatsApp Business antes de editar el flujo.</div>
@else
    <div class="flow-builder-toolbar">
        <div class="row g-3 align-items-end">
            <div class="col-lg-8 col-md-7">
                <label class="form-label small fw-semibold text-secondary mb-1">Nombre del flujo</label>
                <input type="text" form="flow-builder-form" name="flow_name" class="form-control"
                    value="{{ old('flow_name', $flow->name ?? 'Flujo principal de ventas') }}" required>
            </div>
            <div class="col-lg-4 col-md-5 text-md-end">
                <span class="badge bg-light text-dark border px-3 py-2" id="flow-step-counter">Paso 1 de {{ count($stepLabels) }}</span>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.marketing-flow.update') }}" id="flow-builder-form" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @php $steps = $flow?->steps ?? collect(); @endphp

        <div class="flow-builder-grid">
            {{-- Columna 1: Pasos --}}
            <nav class="flow-steps-nav" aria-label="Pasos del flujo">
                <div class="flow-steps-nav-header">
                    <i class="fas fa-list-ol me-1"></i> Pasos del flujo
                </div>
                @foreach($scenarioGroups as $scenarioNum => $group)
                    <div class="flow-scenario-block">
                        <div class="flow-scenario-title">
                            <span class="flow-scenario-dot" style="background:{{ $group['color'] }}"></span>
                            {{ $group['label'] }}
                        </div>
                        @foreach($group['steps'] as $stepKey)
                            @if(isset($stepLabels[$stepKey]))
                                @php
                                    $step = $steps->firstWhere('step_key', $stepKey);
                                    $enabled = old("steps.$stepKey.is_enabled", $step->is_enabled ?? true);
                                    $type = old("steps.$stepKey.interactive_type", $step->config['interactive_type'] ?? 'button');
                                @endphp
                                <button type="button"
                                    class="flow-step-nav {{ $loop->parent->first && $loop->first ? 'active' : '' }} {{ $enabled ? '' : 'disabled-step' }}"
                                    data-step-nav="{{ $stepKey }}"
                                    data-type="{{ $type }}">
                                    <span class="flow-step-nav-icon">
                                        <i class="fas {{ $stepIcons[$stepKey] ?? 'fa-comment' }}"></i>
                                    </span>
                                    <span class="flex-grow-1">
                                        <div class="flow-step-nav-label">{{ $stepLabels[$stepKey] }}</div>
                                        <div class="flow-step-nav-meta">
                                            <span class="badge-status step-status-label {{ $enabled ? 'on' : 'off' }}">{{ $enabled ? 'Activo' : 'Inactivo' }}</span>
                                            {{ $interactiveTypes[$type] ?? $type }}
                                        </div>
                                    </span>
                                </button>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </nav>

            {{-- Columna 2: Editor --}}
            <div class="flow-editor-card">
                <div class="flow-editor-header">
                    <div>
                        <h2 id="flow-active-title">{{ reset($stepLabels) }}</h2>
                        <span class="flow-interactive-badge" id="flow-active-type">—</span>
                    </div>
                    <div class="flow-editor-header-meta">
                        <div class="form-check form-switch mb-0" id="flow-active-toggle-wrap">
                            <input class="form-check-input" type="checkbox" id="flow-active-toggle" checked>
                            <label class="form-check-label small fw-semibold" for="flow-active-toggle">Paso activo</label>
                        </div>
                    </div>
                </div>

                @foreach($stepLabels as $stepKey => $label)
                    @php
                        $step = $steps->firstWhere('step_key', $stepKey);
                        $config = $step->config ?? [];
                        $listSectionsRaw = '';
                        foreach ($config['list']['sections'] ?? [] as $section) {
                            $listSectionsRaw .= '## ' . ($section['title'] ?? 'Opciones') . "\n";
                            foreach ($section['rows'] ?? [] as $row) {
                                $listSectionsRaw .= ($row['id'] ?? '') . '|' . ($row['title'] ?? '') . '|' . ($row['description'] ?? '') . '|' . ($row['action'] ?? '') . "\n";
                            }
                            $listSectionsRaw .= "\n";
                        }
                        $headerMode = old("steps.$stepKey.header_type", $step?->getHeaderMode() ?? 'default');
                        $headerImageUrl = $step?->getHeaderImageUrl();
                        $messageImagePath = $config['message_image_path'] ?? null;
                        $messageImageUrl = $messageImagePath ? asset('storage/' . ltrim($messageImagePath, '/')) : null;
                    @endphp
                    <div class="flow-step-panel {{ $loop->first ? 'active' : '' }}" data-step-panel="{{ $stepKey }}">
                        <input type="hidden" name="steps[{{ $stepKey }}][step_key]" value="{{ $stepKey }}">
                        <input type="hidden" name="steps[{{ $stepKey }}][name]" value="{{ $label }}">
                        <input type="hidden" name="steps[{{ $stepKey }}][sort_order]" value="{{ $step->sort_order ?? $loop->iteration }}">
                        <input type="checkbox" class="d-none step-enable-checkbox" id="enable_{{ $stepKey }}"
                            name="steps[{{ $stepKey }}][is_enabled]" value="1"
                            data-step="{{ $stepKey }}"
                            @checked(old("steps.$stepKey.is_enabled", $step->is_enabled ?? true))>

                        @if($stepKey === \App\Enums\MarketingStepKey::MAIN_MENU)
                            <div class="flow-hint"><i class="fas fa-lightbulb me-1"></i> Menú principal del bot. Use <strong>Lista</strong> para más de 3 opciones.</div>
                        @elseif($stepKey === \App\Enums\MarketingStepKey::PRODUCTS_MENU)
                            <div class="flow-hint catalog"><i class="fas fa-bag-shopping me-1"></i> Catálogo dinámico: elige tipo <strong>Lista</strong> y el origen de productos abajo.</div>
                        @endif

                        @if($stepKey === \App\Enums\MarketingStepKey::PRODUCTS_MENU)
                        <div class="flow-section mt-3">
                            <div class="flow-section-title"><i class="fas fa-bolt"></i> Pedido rápido con WhatsApp Flow</div>
                            <p class="text-muted small mb-2">Mantiene el catálogo por listas y abre un Flow nativo al elegir un producto. Publique primero el Flow en Meta y pegue aquí su ID.</p>
                            <div class="row g-2">
                                <div class="col-md-8">
                                    <label class="form-label small">Flow ID de pedido rápido</label>
                                    <input type="text" name="steps[{{ $stepKey }}][quick_order_flow_id]" class="form-control form-control-sm" value="{{ old("steps.$stepKey.quick_order_flow_id", $config['quick_order_flow']['flow_id'] ?? '') }}" placeholder="ID publicado en Meta">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">CTA</label>
                                    <input type="text" name="steps[{{ $stepKey }}][quick_order_flow_cta]" class="form-control form-control-sm" maxlength="20" value="{{ old("steps.$stepKey.quick_order_flow_cta", $config['quick_order_flow']['cta'] ?? 'Pedir ahora') }}">
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="flow-section">
                            <div class="flow-section-title"><i class="fas fa-comment-dots"></i> Mensaje</div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Tipo de mensaje</label>
                                    <select name="steps[{{ $stepKey }}][interactive_type]" class="form-select form-select-sm interactive-type" data-step="{{ $stepKey }}">
                                        @foreach($interactiveTypes as $value => $typeLabel)
                                            <option value="{{ $value }}" @selected(old("steps.$stepKey.interactive_type", $config['interactive_type'] ?? 'button') === $value)>{{ $typeLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Pie de mensaje <span class="text-muted fw-normal">(opcional)</span></label>
                                    <input type="text" name="steps[{{ $stepKey }}][footer_text]" class="form-control form-control-sm preview-input" data-step="{{ $stepKey }}" data-field="footer" maxlength="60"
                                        value="{{ old("steps.$stepKey.footer_text", $config['footer'] ?? '') }}" placeholder="Máx. 60 caracteres">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Encabezado WhatsApp</label>
                                <select name="steps[{{ $stepKey }}][header_type]" class="form-select form-select-sm header-type-select preview-input mb-2" data-step="{{ $stepKey }}" data-field="header_type">
                                    <option value="default" @selected($headerMode === 'default')>Automático (nombre empresa)</option>
                                    <option value="text" @selected($headerMode === 'text')>Texto personalizado</option>
                                    <option value="image" @selected($headerMode === 'image')>Imagen en encabezado</option>
                                    <option value="none" @selected($headerMode === 'none')>Sin encabezado</option>
                                </select>
                                <div class="form-text small mb-2">Para botones y listas: la imagen aparece arriba del mensaje en WhatsApp.</div>
                                <div class="header-field header-field-text {{ in_array($headerMode, ['text'], true) ? '' : 'd-none' }}" data-step="{{ $stepKey }}">
                                    <input type="text" name="steps[{{ $stepKey }}][header_text]" class="form-control form-control-sm preview-input" data-step="{{ $stepKey }}" data-field="header_text" maxlength="60"
                                        value="{{ old("steps.$stepKey.header_text", $config['header']['text'] ?? '') }}" placeholder="Texto del encabezado">
                                </div>
                                <div class="header-field header-field-image {{ $headerMode === 'image' ? '' : 'd-none' }}" data-step="{{ $stepKey }}">
                                    <input type="file" name="steps[{{ $stepKey }}][header_image]" class="form-control form-control-sm header-image-input preview-input" data-step="{{ $stepKey }}" accept="image/jpeg,image/png,image/webp">
                                    @if($headerImageUrl)
                                        <div class="mt-2 d-flex align-items-center gap-2">
                                            <img src="{{ $headerImageUrl }}" alt="" class="header-image-preview rounded border" data-step="{{ $stepKey }}" data-current-src="{{ $headerImageUrl }}" style="max-height:70px;max-width:140px;object-fit:cover;">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" name="steps[{{ $stepKey }}][remove_header_image]" value="1" id="remove_header_{{ $stepKey }}">
                                                <label class="form-check-label small" for="remove_header_{{ $stepKey }}">Quitar imagen</label>
                                            </div>
                                        </div>
                                    @else
                                        <img src="" alt="" class="header-image-preview rounded border d-none mt-2" data-step="{{ $stepKey }}" style="max-height:70px;max-width:140px;object-fit:cover;">
                                    @endif
                                </div>
                            </div>
                            <div class="mb-3 message-image-field {{ ($config['interactive_type'] ?? 'button') === 'text' ? '' : 'd-none' }}" data-step="{{ $stepKey }}">
                                <label class="form-label small fw-semibold">Imagen del mensaje <span class="text-muted fw-normal">(solo texto)</span></label>
                                <input type="file" name="steps[{{ $stepKey }}][message_image]" class="form-control form-control-sm message-image-input preview-input" data-step="{{ $stepKey }}" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text small">Envía la imagen con el texto como pie de foto (caption).</div>
                                @if($messageImageUrl)
                                    <div class="mt-2 d-flex align-items-center gap-2">
                                        <img src="{{ $messageImageUrl }}" alt="" class="message-image-preview rounded border" data-step="{{ $stepKey }}" data-current-src="{{ $messageImageUrl }}" style="max-height:70px;max-width:140px;object-fit:cover;">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" name="steps[{{ $stepKey }}][remove_message_image]" value="1" id="remove_message_{{ $stepKey }}">
                                            <label class="form-check-label small" for="remove_message_{{ $stepKey }}">Quitar imagen</label>
                                        </div>
                                    </div>
                                @else
                                    <img src="" alt="" class="message-image-preview rounded border d-none mt-2" data-step="{{ $stepKey }}" style="max-height:70px;max-width:140px;object-fit:cover;">
                                @endif
                            </div>
                            <label class="form-label small fw-semibold">Texto del mensaje</label>
                            <textarea name="steps[{{ $stepKey }}][message_template]" rows="5"
                                class="form-control preview-input message-template" data-step="{{ $stepKey }}" data-field="message">{{ old("steps.$stepKey.message_template", $step->message_template ?? '') }}</textarea>
                            <div class="mt-2">
                                <span class="text-muted small">Variables:</span>
                                @foreach(\App\Enums\MarketingStepKey::templateVariables($stepKey) as $var)
                                    <span class="flow-var-chip" data-var="{{ $var }}" data-step="{{ $stepKey }}">{{ '{' . '{' . $var . '}' . '}' }}</span>
                                @endforeach
                            </div>
                        </div>

                        @if($stepKey === \App\Enums\MarketingStepKey::PRODUCTS_MENU)
                        <div class="flow-section">
                            <div class="flow-section-title"><i class="fas fa-store"></i> Catálogo</div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Origen</label>
                                    <select name="steps[{{ $stepKey }}][catalog_source]" class="form-select form-select-sm">
                                        @php $catalogSource = old("steps.$stepKey.catalog_source", $config['catalog_source'] ?? 'products'); @endphp
                                        <option value="products" @selected($catalogSource === 'products')>Productos directos</option>
                                        <option value="categories" @selected($catalogSource === 'categories')>Por categorías</option>
                                        <option value="manual" @selected($catalogSource === 'manual')>Solo manual</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Máx. productos</label>
                                    <input type="number" min="1" max="8" name="steps[{{ $stepKey }}][max_product_rows]" class="form-control form-control-sm"
                                        value="{{ old("steps.$stepKey.max_product_rows", $config['max_product_rows'] ?? 8) }}">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="nav_{{ $stepKey }}" name="steps[{{ $stepKey }}][include_navigation]" value="1"
                                            @checked(old("steps.$stepKey.include_navigation", $config['include_navigation'] ?? true))>
                                        <label class="form-check-label small" for="nav_{{ $stepKey }}">Botones de navegación</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="flow-section panel-interactive" data-step="{{ $stepKey }}">
                            <div class="flow-section-title"><i class="fas fa-hand-pointer"></i> Interacción</div>
                            <div class="panel-buttons" data-step="{{ $stepKey }}">
                                <label class="form-label small fw-semibold">Botones</label>
                                <p class="text-muted small mb-1"><code>id|titulo|accion|respuesta_opcional</code></p>
                                <textarea name="steps[{{ $stepKey }}][buttons]" rows="4" class="form-control font-monospace small preview-input" data-step="{{ $stepKey }}" data-field="buttons">@foreach(($step?->getButtons()) ?? [] as $button){{ $button['id'] }}|{{ $button['title'] }}|{{ $button['action'] ?? $button['id'] }}
@endforeach</textarea>
                            </div>
                            <div class="panel-list d-none mt-3" data-step="{{ $stepKey }}">
                                <label class="form-label small fw-semibold">Texto del botón de lista</label>
                                <input type="text" name="steps[{{ $stepKey }}][list_button]" class="form-control form-control-sm mb-2 preview-input" data-step="{{ $stepKey }}" data-field="list_button" maxlength="20"
                                    value="{{ old("steps.$stepKey.list_button", $config['list']['button'] ?? 'Ver opciones') }}">
                                <label class="form-label small fw-semibold">Filas de la lista</label>
                                <p class="text-muted small mb-1"><code>id|titulo|descripcion|accion</code> · secciones con <code>## Título</code></p>
                                <textarea name="steps[{{ $stepKey }}][list_sections]" rows="5" class="form-control font-monospace small preview-input" data-step="{{ $stepKey }}" data-field="list_sections">{{ old("steps.$stepKey.list_sections", trim($listSectionsRaw)) }}</textarea>
                                @if($stepKey === \App\Enums\MarketingStepKey::MAIN_MENU)
                                    <p class="form-text small mt-1 mb-0">Acciones: {{ implode(', ', array_keys($buttonActions)) }}</p>
                                @endif
                            </div>
                            <div class="panel-flow d-none mt-3" data-step="{{ $stepKey }}">
                                <div class="flow-hint info small mb-2">WhatsApp Flow de Meta (formularios nativos).</div>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label small">Flow ID</label>
                                        <input type="text" name="steps[{{ $stepKey }}][flow_id]" class="form-control form-control-sm" value="{{ old("steps.$stepKey.flow_id", $config['flow']['flow_id'] ?? '') }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small">Flow token</label>
                                        <input type="text" name="steps[{{ $stepKey }}][flow_token]" class="form-control form-control-sm" value="{{ old("steps.$stepKey.flow_token", $config['flow']['flow_token'] ?? $stepKey) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small">Texto CTA</label>
                                        <input type="text" name="steps[{{ $stepKey }}][flow_cta]" class="form-control form-control-sm preview-input" data-step="{{ $stepKey }}" data-field="flow_cta" maxlength="20"
                                            value="{{ old("steps.$stepKey.flow_cta", $config['flow']['cta'] ?? 'Continuar') }}">
                                    </div>
                                </div>
                            </div>
                            <div class="panel-cta d-none mt-3" data-step="{{ $stepKey }}">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label small">Texto botón URL</label>
                                        <input type="text" name="steps[{{ $stepKey }}][cta_button_text]" class="form-control form-control-sm preview-input" data-step="{{ $stepKey }}" data-field="cta_button_text" maxlength="20"
                                            value="{{ old("steps.$stepKey.cta_button_text", $config['cta_url']['button_text'] ?? 'Abrir enlace') }}">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label small">URL destino</label>
                                        <input type="url" name="steps[{{ $stepKey }}][cta_url]" class="form-control form-control-sm" value="{{ old("steps.$stepKey.cta_url", $config['cta_url']['url'] ?? '') }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($stepKey === \App\Enums\MarketingStepKey::MAIN_MENU)
                        <div class="flow-section">
                            <div class="flow-section-title"><i class="fas fa-wand-magic-sparkles"></i> Acciones personalizadas</div>
                            <p class="text-muted small mb-2">En botones use <code>custom:clave</code> y defina: <code>clave|Mensaje de respuesta</code></p>
                            <textarea name="steps[{{ $stepKey }}][custom_actions]" rows="3" class="form-control font-monospace small">@php
                                $customRaw = old("steps.$stepKey.custom_actions");
                                if ($customRaw === null) {
                                    foreach ($config['custom_actions'] ?? [] as $key => $message) {
                                        echo $key . '|' . $message . "\n";
                                    }
                                } else { echo $customRaw; }
                            @endphp</textarea>
                        </div>
                        @endif

                        @if($stepKey === \App\Enums\MarketingStepKey::PAYMENT_PROOF)
                        <div class="flow-section">
                            <div class="flow-section-title"><i class="fas fa-receipt"></i> Comprobante de pago</div>
                            <div class="form-check form-switch mb-3">
                                <input type="hidden" name="steps[{{ $stepKey }}][require_proof]" value="0">
                                <input type="checkbox" class="form-check-input" id="require_proof_{{ $stepKey }}"
                                    name="steps[{{ $stepKey }}][require_proof]" value="1"
                                    {{ old("steps.$stepKey.require_proof", $config['require_proof'] ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="require_proof_{{ $stepKey }}">
                                    Solicitar comprobante al confirmar el pedido
                                </label>
                            </div>
                            <p class="text-muted small mb-2">Métodos de pago que requieren comprobante:</p>
                            @php
                                $selectedMethods = old("steps.$stepKey.require_for_methods", $config['require_for_methods'] ?? ['transferencia', 'tarjeta']);
                            @endphp
                            <div class="d-flex flex-wrap gap-3 mb-3">
                                @foreach(['transferencia' => '🏦 Transferencia', 'tarjeta' => '💳 Tarjeta', 'efectivo' => '💵 Efectivo'] as $methodKey => $methodLabel)
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input"
                                        name="steps[{{ $stepKey }}][require_for_methods][]"
                                        value="{{ $methodKey }}" id="proof_method_{{ $stepKey }}_{{ $methodKey }}"
                                        {{ in_array($methodKey, $selectedMethods, true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="proof_method_{{ $stepKey }}_{{ $methodKey }}">{{ $methodLabel }}</label>
                                </div>
                                @endforeach
                            </div>
                            <label class="form-label small">Mensaje tras recibir comprobante</label>
                            <textarea name="steps[{{ $stepKey }}][success_message]" rows="2" class="form-control form-control-sm">{{ old("steps.$stepKey.success_message", $config['success_message'] ?? '') }}</textarea>
                        </div>
                        @endif

                        <div class="d-flex justify-content-between pt-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm flow-nav-prev" disabled>
                                <i class="fas fa-arrow-left me-1"></i> Anterior
                            </button>
                            <button type="button" class="btn btn-primary btn-sm flow-nav-next">
                                Siguiente <i class="fas fa-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Columna 3: Preview --}}
            <div class="flow-preview-column">
                <div class="flow-preview-card">
                    <div class="wa-preview-label"><i class="fab fa-whatsapp text-success me-1"></i> Vista previa</div>
                    <div class="wa-phone">
                        <div class="wa-phone-header d-flex align-items-center gap-2">
                            @if($chatbotConfig?->bot_avatar_url)
                                <img src="{{ $chatbotConfig->bot_avatar_url }}" alt="" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">
                            @else
                                <i class="fas fa-robot opacity-75"></i>
                            @endif
                            <span>{{ $chatbotConfig?->bot_name ?? ($profile->business_name ?? 'Bot Ventas') }}</span>
                        </div>
                        <div class="wa-phone-body">
                            <div class="wa-bubble">
                                <div class="wa-bubble-header d-none" id="wa-preview-header"></div>
                                <div id="wa-preview-body">Seleccione un paso.</div>
                                <div class="wa-bubble-footer d-none" id="wa-preview-footer"></div>
                                <div id="wa-preview-actions"></div>
                            </div>
                        </div>
                    </div>
                    <div class="flow-actions-legend">
                        <strong class="d-block mb-1">Acciones del botón</strong>
                        @foreach($buttonActions as $code => $actionLabel)
                            <div class="mb-1"><code>{{ $code }}</code> — {{ $actionLabel }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="flow-save-bar">
            <span class="text-muted small"><i class="fas fa-info-circle me-1"></i> Los cambios se aplican al guardar.</span>
            <button type="submit" class="btn btn-success px-4">
                <i class="fas fa-save me-1"></i> Guardar flujo
            </button>
        </div>
    </form>
@endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const previewSamples = {
        nombre: 'María González',
        nombre_bot: @json($chatbotConfig?->bot_name ?? 'Asistente virtual'),
        nombre_empresa: @json($profile->business_name ?? 'Tienda Demo'),
        telefono_soporte: '593959520743',
        horario_atencion: 'Lun–Vie 9:00–18:00',
        total_productos: '24',
        total_categorias: '6',
        total: '45.90',
        moneda: 'USD',
        cantidad_items: '3',
        numero_pedido: 'PED-1024',
        estado_pedido: 'Confirmado',
    };
    const stepLabels = @json($stepLabels);
    const interactiveTypes = @json($interactiveTypes);
    const stepOrder = @json(array_keys($stepLabels));
    let activeStep = stepOrder[0] || null;

    function applyVariables(text) {
        if (!text) return '';
        return text.replace(/\{\{(\w+)\}\}/g, (_, key) => previewSamples[key] ?? ('{' + '{' + key + '}' + '}'));
    }
    function formatWhatsApp(text) {
        return applyVariables(text).replace(/\*([^*]+)\*/g, '<strong>$1</strong>');
    }
    function parseButtons(raw) {
        return raw.split(/\r?\n/).map(l => l.trim()).filter(Boolean).map(line => {
            const p = line.split('|').map(s => s.trim());
            return { title: p[1] || p[0] || 'Botón' };
        }).slice(0, 3);
    }
    function parseListRows(raw) {
        return raw.split(/\r?\n/).map(l => l.trim()).filter(l => l && !l.startsWith('##')).map(line => {
            const p = line.split('|').map(s => s.trim());
            return { title: p[1] || p[0], description: p[2] || '' };
        }).slice(0, 3);
    }
    function getPanel(stepKey) {
        return document.querySelector(`[data-step-panel="${stepKey}"]`);
    }
    function getField(stepKey, name) {
        const panel = getPanel(stepKey);
        if (!panel) return '';
        const el = panel.querySelector(`[data-field="${name}"]`) || panel.querySelector(`[name="steps[${stepKey}][${name}]"]`);
        if (!el) return '';
        if (el.type === 'file') return '';
        if (el.type === 'checkbox') return el.checked ? '1' : '';
        return el.value;
    }
    function getHeaderType(stepKey) {
        return getPanel(stepKey)?.querySelector('.header-type-select')?.value || 'default';
    }
    function getHeaderPreviewHtml(stepKey) {
        const panel = getPanel(stepKey);
        const type = panel?.querySelector('.interactive-type')?.value || 'text';
        const mode = getHeaderType(stepKey);
        if (mode === 'none') {
            if (type === 'text') {
                const fileInput = panel?.querySelector('.message-image-input');
                const previewImg = panel?.querySelector('.message-image-preview');
                let src = fileInput?.files?.[0] ? URL.createObjectURL(fileInput.files[0]) : (previewImg?.dataset.currentSrc || previewImg?.src || '');
                if (src) return `<div class="wa-bubble-header-image"><img src="${src}" alt=""></div>`;
            }
            return '';
        }
        if (mode === 'image') {
            const fileInput = panel?.querySelector('.header-image-input');
            const previewImg = panel?.querySelector('.header-image-preview');
            let src = fileInput?.files?.[0] ? URL.createObjectURL(fileInput.files[0]) : (previewImg?.dataset.currentSrc || previewImg?.src || '');
            if (src) return `<div class="wa-bubble-header-image"><img src="${src}" alt=""></div>`;
            return '<div class="wa-bubble-header-image bg-light text-muted d-flex align-items-center justify-content-center" style="min-height:60px;font-size:.72rem;">Imagen</div>';
        }
        if (mode === 'text') {
            const text = getField(stepKey, 'header_text');
            if (text) return applyVariables(text);
        }
        if (mode === 'default') return applyVariables(previewSamples.nombre_empresa || 'Tienda');
        return '';
    }
    function toggleMessageImageField(stepKey) {
        const panel = getPanel(stepKey);
        if (!panel) return;
        const type = panel.querySelector('.interactive-type')?.value;
        panel.querySelectorAll('.message-image-field').forEach(el => {
            el.classList.toggle('d-none', type !== 'text');
        });
    }
    function toggleHeaderFields(stepKey) {
        const panel = getPanel(stepKey);
        if (!panel) return;
        const mode = getHeaderType(stepKey);
        panel.querySelectorAll('.header-field').forEach(el => el.classList.add('d-none'));
        if (mode === 'text') panel.querySelector('.header-field-text')?.classList.remove('d-none');
        if (mode === 'image') panel.querySelector('.header-field-image')?.classList.remove('d-none');
    }
    function updatePreview() {
        if (!activeStep) return;
        const type = getPanel(activeStep)?.querySelector('.interactive-type')?.value || 'text';
        const headerEl = document.getElementById('wa-preview-header');
        const bodyEl = document.getElementById('wa-preview-body');
        const footerEl = document.getElementById('wa-preview-footer');
        const actionsEl = document.getElementById('wa-preview-actions');
        const headerPreview = getHeaderPreviewHtml(activeStep);
        if (headerPreview) {
            if (headerPreview.startsWith('<div')) headerEl.innerHTML = headerPreview;
            else headerEl.textContent = headerPreview;
            headerEl.classList.remove('d-none');
        } else {
            headerEl.innerHTML = '';
            headerEl.classList.add('d-none');
        }
        bodyEl.innerHTML = formatWhatsApp(getField(activeStep, 'message')) || '<span class="text-muted">Sin mensaje.</span>';
        const footer = getField(activeStep, 'footer');
        if (footer) { footerEl.textContent = applyVariables(footer); footerEl.classList.remove('d-none'); }
        else footerEl.classList.add('d-none');
        actionsEl.innerHTML = '';
        if (type === 'button') {
            parseButtons(getField(activeStep, 'buttons')).forEach(btn => {
                actionsEl.innerHTML += `<div class="wa-btn-preview">${btn.title}</div>`;
            });
        } else if (type === 'list') {
            actionsEl.innerHTML = `<div class="wa-list-cta">${getField(activeStep, 'list_button') || 'Ver opciones'}</div>`;
            parseListRows(getField(activeStep, 'list_sections')).forEach(row => {
                actionsEl.innerHTML += `<div class="wa-btn-preview text-start small">${row.title}</div>`;
            });
        } else if (type === 'flow') {
            actionsEl.innerHTML = `<div class="wa-list-cta">${getField(activeStep, 'flow_cta') || 'Continuar'}</div>`;
        } else if (type === 'cta_url') {
            actionsEl.innerHTML = `<div class="wa-list-cta">${getField(activeStep, 'cta_button_text') || 'Abrir enlace'}</div>`;
        }
    }
    function toggleInteractivePanels(stepKey) {
        const panel = getPanel(stepKey);
        if (!panel) return;
        const type = panel.querySelector('.interactive-type')?.value;
        panel.querySelectorAll('.panel-buttons, .panel-list, .panel-flow, .panel-cta').forEach(el => el.classList.add('d-none'));
        if (type === 'button') panel.querySelector('.panel-buttons')?.classList.remove('d-none');
        if (type === 'list') panel.querySelector('.panel-list')?.classList.remove('d-none');
        if (type === 'flow') panel.querySelector('.panel-flow')?.classList.remove('d-none');
        if (type === 'cta_url') panel.querySelector('.panel-cta')?.classList.remove('d-none');
        const navBtn = document.querySelector(`[data-step-nav="${stepKey}"]`);
        if (navBtn) {
            const enabled = document.getElementById(`enable_${stepKey}`)?.checked;
            const statusEl = navBtn.querySelector('.step-status-label');
            if (statusEl) {
                statusEl.textContent = enabled ? 'Activo' : 'Inactivo';
                statusEl.className = 'badge-status step-status-label ' + (enabled ? 'on' : 'off');
            }
            const meta = navBtn.querySelector('.flow-step-nav-meta');
            if (meta && statusEl) {
                meta.innerHTML = '';
                meta.appendChild(statusEl);
                meta.append(' ' + (interactiveTypes[type] || type));
            }
            navBtn.classList.toggle('disabled-step', !enabled);
        }
        if (stepKey === activeStep) {
            document.getElementById('flow-active-type').textContent = interactiveTypes[type] || type;
        }
        toggleMessageImageField(stepKey);
    }
    function syncHeaderToggle(stepKey) {
        const cb = document.getElementById(`enable_${stepKey}`);
        const headerToggle = document.getElementById('flow-active-toggle');
        if (cb && headerToggle) {
            headerToggle.checked = cb.checked;
            headerToggle.onchange = () => { cb.checked = headerToggle.checked; toggleInteractivePanels(stepKey); };
        }
    }
    function selectStep(stepKey) {
        activeStep = stepKey;
        document.querySelectorAll('.flow-step-nav').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.stepNav === stepKey);
        });
        document.querySelectorAll('.flow-step-panel').forEach(panel => {
            panel.classList.toggle('active', panel.dataset.stepPanel === stepKey);
        });
        document.getElementById('flow-active-title').textContent = stepLabels[stepKey] || stepKey;
        const idx = stepOrder.indexOf(stepKey);
        document.getElementById('flow-step-counter').textContent = `Paso ${idx + 1} de ${stepOrder.length}`;
        const panel = getPanel(stepKey);
        panel?.querySelector('.flow-nav-prev')?.toggleAttribute('disabled', idx <= 0);
        panel?.querySelector('.flow-nav-next')?.toggleAttribute('disabled', idx >= stepOrder.length - 1);
        syncHeaderToggle(stepKey);
        toggleInteractivePanels(stepKey);
        toggleHeaderFields(stepKey);
        updatePreview();
    }

    document.querySelectorAll('[data-step-nav]').forEach(btn => {
        btn.addEventListener('click', () => selectStep(btn.dataset.stepNav));
    });
    document.querySelectorAll('.interactive-type').forEach(select => {
        select.addEventListener('change', () => {
            toggleInteractivePanels(select.dataset.step);
            if (select.dataset.step === activeStep) updatePreview();
        });
    });
    document.querySelectorAll('.header-type-select').forEach(select => {
        select.addEventListener('change', () => {
            toggleHeaderFields(select.dataset.step);
            if (select.dataset.step === activeStep) updatePreview();
        });
    });
    document.querySelectorAll('.header-image-input').forEach(input => {
        input.addEventListener('change', () => {
            const previewImg = getPanel(input.dataset.step)?.querySelector('.header-image-preview');
            if (previewImg && input.files?.[0]) {
                previewImg.src = URL.createObjectURL(input.files[0]);
                previewImg.classList.remove('d-none');
            }
            if (input.dataset.step === activeStep) updatePreview();
        });
    });
    document.querySelectorAll('.message-image-input').forEach(input => {
        input.addEventListener('change', () => {
            const previewImg = getPanel(input.dataset.step)?.querySelector('.message-image-preview');
            if (previewImg && input.files?.[0]) {
                previewImg.src = URL.createObjectURL(input.files[0]);
                previewImg.classList.remove('d-none');
            }
            if (input.dataset.step === activeStep) updatePreview();
        });
    });
    document.querySelectorAll('.preview-input').forEach(input => {
        const fn = () => { if (input.dataset.step === activeStep) updatePreview(); };
        input.addEventListener('input', fn);
        input.addEventListener('change', fn);
    });
    document.querySelectorAll('.flow-var-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            const ta = getPanel(chip.dataset.step)?.querySelector('.message-template');
            if (!ta) return;
            const token = '{' + '{' + chip.dataset.var + '}' + '}';
            const start = ta.selectionStart, end = ta.selectionEnd;
            ta.value = ta.value.slice(0, start) + token + ta.value.slice(end);
            ta.focus();
            ta.selectionStart = ta.selectionEnd = start + token.length;
            if (chip.dataset.step === activeStep) updatePreview();
        });
    });
    document.querySelectorAll('.flow-nav-prev, .flow-nav-next').forEach(btn => {
        btn.addEventListener('click', () => {
            const idx = stepOrder.indexOf(activeStep);
            const next = btn.classList.contains('flow-nav-next') ? idx + 1 : idx - 1;
            if (stepOrder[next]) selectStep(stepOrder[next]);
        });
    });

    document.querySelectorAll('.interactive-type').forEach(s => toggleInteractivePanels(s.dataset.step));
    if (activeStep) selectStep(activeStep);
})();
</script>
@endpush
