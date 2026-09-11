@extends('admin.layouts.app')

@section('header', 'Palabras clave del bot')

@section('content')
@php
    // Qué formulario fue el que falló (el de crear, o el de editar tal id) --
    // sin esto, old() es global y "keywords" de un formulario se filtraba en
    // TODOS los demás con el mismo name, y el modal de crear no se reabría
    // solo ni conservaba lo escrito cuando la palabra ya existía.
    $failedFormId = old('_form_id');
    $matchesKnownForm = $failedFormId === 'new' || $entries->contains('id', (int) $failedFormId);
@endphp
<style>
    .kw-page { max-width: 960px; margin: 0 auto; }
    .kw-hero { border-radius:18px; padding:24px; margin-bottom:20px; color:#fff; background:linear-gradient(120deg,#075e54,#128c7e 60%,#25d366); }
    .kw-hero h2 { margin:0 0 6px; font-size:1.45rem; font-weight:900; }
    .kw-hero p { margin:0; opacity:.92; max-width:700px; }
    .kw-toolbar { display:flex; justify-content:flex-end; margin-bottom:14px; }
    .kw-fab { display:inline-flex; align-items:center; gap:.5rem; border:0; border-radius:10px; padding:.7rem 1.05rem; background:#128c7e; color:#fff; font:inherit; font-size:.85rem; font-weight:800; cursor:pointer; box-shadow:0 4px 14px rgba(18,140,126,.25); }
    .kw-fab:hover { background:#0f766e; }
    .kw-list { display:flex; flex-direction:column; gap:14px; }
    .kw-card { background:#fff; border:1px solid #e7e9ee; border-radius:16px; padding:0; box-shadow:0 5px 16px rgba(15,23,42,.05); overflow:hidden; }
    .kw-summary { list-style:none; cursor:pointer; padding:16px 18px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .kw-summary::-webkit-details-marker { display:none; }
    .kw-summary-main { min-width:0; flex:1 1 320px; }
    .kw-preview { margin:4px 0 0; color:#64748b; font-size:.82rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .kw-summary-meta { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .kw-chip { background:#f1f5f9; color:#475569; border-radius:999px; padding:4px 10px; font-size:.72rem; font-weight:700; white-space:nowrap; }
    .kw-chip.is-on { background:#dcfce7; color:#166534; }
    .kw-chip.is-off { background:#fee2e2; color:#991b1b; }
    .kw-chevron { color:#94a3b8; transition:transform .15s; }
    .kw-card[open] .kw-chevron { transform:rotate(180deg); }
    .kw-card > form.kw-form { padding:0 18px 18px; }
    .kw-form { display:grid; gap:11px; padding-top:4px; border-top:1px solid #f1f5f9; margin-top:2px; }
    .kw-form label { display:grid; gap:5px; font-size:.78rem; font-weight:800; color:#475569; }
    .kw-form input[type=text], .kw-form input[type=number], .kw-form textarea { width:100%; border:1px solid #cbd5e1; border-radius:9px; padding:9px 10px; font:inherit; font-size:.88rem; }
    .kw-form input:focus, .kw-form textarea:focus { outline:0; border-color:#128c7e; box-shadow:0 0 0 3px rgba(18,140,126,.12); }
    .kw-row { display:grid; grid-template-columns:1fr 140px; gap:8px; }
    .kw-check { display:flex!important; align-items:center; gap:7px; font-weight:700!important; }
    .kw-check input { width:auto!important; }
    .kw-badges { display:flex; flex-wrap:wrap; gap:6px; margin:0 0 4px; }
    .kw-badge { background:#e6f7f4; color:#075e54; border-radius:999px; padding:3px 10px; font-size:.75rem; font-weight:700; }
    .kw-branches { display:grid; gap:.45rem; margin-top:.4rem; }
    .kw-branch { display:flex; align-items:center; gap:.5rem; padding:.5rem .65rem; border:1px solid #e2e8f0; border-radius:9px; background:#fff; font-size:.82rem; }
    .kw-actions { display:flex; justify-content:space-between; align-items:center; gap:8px; margin-top:4px; }
    .kw-save { border:0; border-radius:9px; padding:10px 13px; color:#fff; background:#128c7e; font:inherit; font-weight:800; cursor:pointer; }
    .kw-delete { border:0; border-radius:9px; padding:10px 13px; color:#fff; background:#b91c1c; font:inherit; font-weight:800; cursor:pointer; margin:0 18px 18px; }
    .kw-error { color:#b91c1c; font-size:.74rem; font-weight:700; margin-top:-4px; }
    .kw-empty { text-align:center; padding:2rem; background:#fff; border:1px dashed #e2e8f0; border-radius:14px; color:#64748b; }
    .kw-modal { border:0; border-radius:16px; padding:0; width:min(560px, 92vw); box-shadow:0 20px 50px rgba(15,23,42,.25); }
    .kw-modal::backdrop { background:rgba(15,23,42,.45); }
    .kw-modal .kw-form { padding:18px; border-top:0; }
    .kw-modal-head { display:flex; align-items:center; justify-content:space-between; padding:16px 18px 0; }
    .kw-modal-head h3 { margin:0; font-size:1.05rem; }
    .kw-modal-close { border:0; background:transparent; font-size:1.4rem; line-height:1; color:#94a3b8; cursor:pointer; }
    .kw-modal-close:hover { color:#334155; }
</style>

<div class="kw-page">
    <section class="kw-hero">
        <h2>Palabras clave del bot</h2>
        <p>Configura palabras o sinónimos (sepáralos con comas) que, cuando el cliente los escriba, disparan un texto fijo. Ejemplo: "direcciones, ubicaciones, sucursales, horarios" &rarr; un mensaje con tus locales y horarios. Cada palabra solo puede usarse en una configuración, y cada una puede limitarse a sucursales específicas o quedar para todas.</p>
    </section>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any() && ! $matchesKnownForm)<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="kw-toolbar">
        <button type="button" class="kw-fab" id="kwOpenCreate"><i class="fas fa-plus"></i> Nueva palabra clave</button>
    </div>

    <div class="kw-list">
        @forelse($entries as $entry)
            @php $isFailedEdit = $failedFormId !== null && (string) $failedFormId === (string) $entry->id; @endphp
            <details class="kw-card" @if($isFailedEdit) open @endif>
                <summary class="kw-summary">
                    <div class="kw-summary-main">
                        <div class="kw-badges">
                            @foreach($entry->keywords as $kw)
                                <span class="kw-badge">{{ $kw }}</span>
                            @endforeach
                        </div>
                        <p class="kw-preview">{{ \Illuminate\Support\Str::limit($entry->response_text, 90) }}</p>
                    </div>
                    <div class="kw-summary-meta">
                        <span class="kw-chip {{ $entry->is_active ? 'is-on' : 'is-off' }}">{{ $entry->is_active ? 'Activa' : 'Inactiva' }}</span>
                        <span class="kw-chip">{{ $entry->all_branches ? 'Todas las sucursales' : ($entry->branches->pluck('name')->join(', ') ?: 'Sin sucursal') }}</span>
                        <i class="fas fa-chevron-down kw-chevron"></i>
                    </div>
                </summary>
                <form class="kw-form" method="POST" action="{{ route('admin.chatbot-keywords.update', $entry) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="_form_id" value="{{ $entry->id }}">
                    <label>Palabras clave (sepáralas con comas)
                        <input type="text" name="keywords" required maxlength="1000" value="{{ $isFailedEdit ? old('keywords') : implode(', ', $entry->keywords) }}">
                    </label>
                    @if($isFailedEdit) @error('keywords')<small class="kw-error">{{ $message }}</small>@enderror @endif
                    <label>Texto que responde el bot
                        <textarea name="response_text" rows="4" required maxlength="2000">{{ $isFailedEdit ? old('response_text') : $entry->response_text }}</textarea>
                    </label>
                    @if($isFailedEdit) @error('response_text')<small class="kw-error">{{ $message }}</small>@enderror @endif
                    <div class="kw-row">
                        <label class="kw-check"><input type="checkbox" name="is_active" value="1" @checked($isFailedEdit ? old('is_active') : $entry->is_active)> Activa</label>
                        <label>Orden<input type="number" name="sort_order" min="0" value="{{ $isFailedEdit ? old('sort_order') : $entry->sort_order }}"></label>
                    </div>
                    @if($branches->isNotEmpty())
                        <label class="kw-check">
                            <input type="checkbox" class="kw-all-toggle" name="all_branches" value="1" @checked($isFailedEdit ? old('all_branches') : $entry->all_branches)>
                            Todas las sucursales
                        </label>
                        <div class="kw-branches">
                            @foreach($branches as $branch)
                                @php
                                    $checked = $isFailedEdit
                                        ? in_array($branch->id, old('branch_ids', []))
                                        : $entry->branches->contains('id', $branch->id);
                                @endphp
                                <label class="kw-branch"><input type="checkbox" class="kw-branch-input" name="branch_ids[]" value="{{ $branch->id }}" @checked($checked)> {{ $branch->name }}</label>
                            @endforeach
                        </div>
                        @if($isFailedEdit) @error('branch_ids')<small class="kw-error">{{ $message }}</small>@enderror @endif
                    @endif
                    <div class="kw-actions">
                        <button type="submit" class="kw-save">Guardar cambios</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.chatbot-keywords.destroy', $entry) }}" onsubmit="return confirm('¿Eliminar esta palabra clave?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="kw-delete">Eliminar</button>
                </form>
            </details>
        @empty
            <div class="kw-empty">Todavía no hay palabras clave configuradas.</div>
        @endforelse
    </div>
</div>

<dialog id="kwCreateModal" class="kw-modal">
    <div class="kw-modal-head">
        <h3>Nueva palabra clave</h3>
        <button type="button" class="kw-modal-close" id="kwCloseCreate" aria-label="Cerrar">&times;</button>
    </div>
    <form class="kw-form" method="POST" action="{{ route('admin.chatbot-keywords.store') }}">
        @csrf
        <input type="hidden" name="_form_id" value="new">
        <label>Palabras clave (sepáralas con comas)
            <input type="text" name="keywords" required maxlength="1000" value="{{ $failedFormId === 'new' ? old('keywords') : '' }}" placeholder="direcciones, ubicaciones, sucursales, horarios">
        </label>
        @if($failedFormId === 'new') @error('keywords')<small class="kw-error">{{ $message }}</small>@enderror @endif
        <label>Texto que responde el bot
            <textarea name="response_text" rows="4" required maxlength="2000" placeholder="*Nuestras ubicaciones*&#10;...">{{ $failedFormId === 'new' ? old('response_text') : '' }}</textarea>
        </label>
        @if($failedFormId === 'new') @error('response_text')<small class="kw-error">{{ $message }}</small>@enderror @endif
        <div class="kw-row">
            <label class="kw-check"><input type="checkbox" name="is_active" value="1" @checked($failedFormId === 'new' ? old('is_active') : true)> Activa</label>
            <label>Orden<input type="number" name="sort_order" min="0" value="{{ $failedFormId === 'new' ? old('sort_order', 0) : 0 }}"></label>
        </div>
        @if($branches->isNotEmpty())
            <label class="kw-check">
                <input type="checkbox" class="kw-all-toggle" name="all_branches" value="1" @checked($failedFormId === 'new' ? old('all_branches') : true)>
                Todas las sucursales
            </label>
            <div class="kw-branches">
                @foreach($branches as $branch)
                    <label class="kw-branch"><input type="checkbox" class="kw-branch-input" name="branch_ids[]" value="{{ $branch->id }}" @checked($failedFormId === 'new' && in_array($branch->id, old('branch_ids', [])))> {{ $branch->name }}</label>
                @endforeach
            </div>
            @if($failedFormId === 'new') @error('branch_ids')<small class="kw-error">{{ $message }}</small>@enderror @endif
        @endif
        <div class="kw-actions">
            <button type="submit" class="kw-save">Crear palabra clave</button>
        </div>
    </form>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.kw-form').forEach((form) => {
        const all = form.querySelector('.kw-all-toggle');
        const choices = form.querySelectorAll('.kw-branch-input');
        const sync = () => choices.forEach((input) => { input.disabled = !!all?.checked; });
        all?.addEventListener('change', sync);
        sync();
    });

    const modal = document.getElementById('kwCreateModal');
    document.getElementById('kwOpenCreate')?.addEventListener('click', () => modal?.showModal());
    document.getElementById('kwCloseCreate')?.addEventListener('click', () => modal?.close());
    modal?.addEventListener('click', (event) => { if (event.target === modal) modal.close(); });

    @if($failedFormId === 'new')
        modal?.showModal();
    @endif
});
</script>
@endsection
