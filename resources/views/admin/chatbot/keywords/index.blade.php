@extends('admin.layouts.app')

@section('header', 'Palabras clave del bot')

@section('content')
<style>
    .kw-page { max-width: 960px; margin: 0 auto; }
    .kw-hero { border-radius:18px; padding:24px; margin-bottom:20px; color:#fff; background:linear-gradient(120deg,#075e54,#128c7e 60%,#25d366); }
    .kw-hero h2 { margin:0 0 6px; font-size:1.45rem; font-weight:900; }
    .kw-hero p { margin:0; opacity:.92; max-width:700px; }
    .kw-list { display:flex; flex-direction:column; gap:14px; }
    .kw-card { background:#fff; border:1px solid #e7e9ee; border-radius:16px; padding:18px; box-shadow:0 5px 16px rgba(15,23,42,.05); }
    .kw-form { display:grid; gap:11px; }
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
    .kw-delete { border:0; border-radius:9px; padding:10px 13px; color:#fff; background:#b91c1c; font:inherit; font-weight:800; cursor:pointer; }
    .kw-add { border:1px dashed #5eead4; background:#f0fdfa; }
    .kw-empty { text-align:center; padding:2rem; background:#fff; border:1px dashed #e2e8f0; border-radius:14px; color:#64748b; }
</style>

<div class="kw-page">
    <section class="kw-hero">
        <h2>Palabras clave del bot</h2>
        <p>Configura palabras o sinónimos (sepáralos con comas) que, cuando el cliente los escriba, disparan un texto fijo. Ejemplo: "direcciones, ubicaciones, sucursales, horarios" &rarr; un mensaje con tus locales y horarios. Cada palabra solo puede usarse en una configuración, y cada una puede limitarse a sucursales específicas o quedar para todas.</p>
    </section>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="kw-list">
        @forelse($entries as $entry)
            <article class="kw-card">
                <div class="kw-badges">
                    @foreach($entry->keywords as $kw)
                        <span class="kw-badge">{{ $kw }}</span>
                    @endforeach
                </div>
                <form class="kw-form" method="POST" action="{{ route('admin.chatbot-keywords.update', $entry) }}">
                    @csrf @method('PUT')
                    <label>Palabras clave (sepáralas con comas)
                        <input type="text" name="keywords" required maxlength="1000" value="{{ old('keywords', implode(', ', $entry->keywords)) }}">
                    </label>
                    <label>Texto que responde el bot
                        <textarea name="response_text" rows="4" required maxlength="2000">{{ old('response_text', $entry->response_text) }}</textarea>
                    </label>
                    <div class="kw-row">
                        <label class="kw-check"><input type="checkbox" name="is_active" value="1" @checked($entry->is_active)> Activa</label>
                        <label>Orden<input type="number" name="sort_order" min="0" value="{{ old('sort_order', $entry->sort_order) }}"></label>
                    </div>
                    @if($branches->isNotEmpty())
                        <label class="kw-check">
                            <input type="checkbox" class="kw-all-toggle" name="all_branches" value="1" @checked($entry->all_branches)>
                            Todas las sucursales
                        </label>
                        <div class="kw-branches">
                            @foreach($branches as $branch)
                                <label class="kw-branch"><input type="checkbox" class="kw-branch-input" name="branch_ids[]" value="{{ $branch->id }}" @checked($entry->branches->contains('id', $branch->id))> {{ $branch->name }}</label>
                            @endforeach
                        </div>
                    @endif
                    <div class="kw-actions">
                        <button type="submit" class="kw-save">Guardar cambios</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.chatbot-keywords.destroy', $entry) }}" onsubmit="return confirm('¿Eliminar esta palabra clave?');" style="margin-top:8px">
                    @csrf @method('DELETE')
                    <button type="submit" class="kw-delete">Eliminar</button>
                </form>
            </article>
        @empty
            <div class="kw-empty">Todavía no hay palabras clave configuradas.</div>
        @endforelse

        <article class="kw-card kw-add">
            <h3 style="margin:0 0 6px;">Nueva palabra clave</h3>
            <form class="kw-form" method="POST" action="{{ route('admin.chatbot-keywords.store') }}">
                @csrf
                <label>Palabras clave (sepáralas con comas)
                    <input type="text" name="keywords" required maxlength="1000" placeholder="direcciones, ubicaciones, sucursales, horarios">
                </label>
                <label>Texto que responde el bot
                    <textarea name="response_text" rows="4" required maxlength="2000" placeholder="*Nuestras ubicaciones*&#10;..."></textarea>
                </label>
                <div class="kw-row">
                    <label class="kw-check"><input type="checkbox" name="is_active" value="1" checked> Activa</label>
                    <label>Orden<input type="number" name="sort_order" min="0" value="0"></label>
                </div>
                @if($branches->isNotEmpty())
                    <label class="kw-check">
                        <input type="checkbox" class="kw-all-toggle" name="all_branches" value="1" checked>
                        Todas las sucursales
                    </label>
                    <div class="kw-branches">
                        @foreach($branches as $branch)
                            <label class="kw-branch"><input type="checkbox" class="kw-branch-input" name="branch_ids[]" value="{{ $branch->id }}"> {{ $branch->name }}</label>
                        @endforeach
                    </div>
                @endif
                <button type="submit" class="kw-save">Crear palabra clave</button>
            </form>
        </article>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.kw-form').forEach((form) => {
        const all = form.querySelector('.kw-all-toggle');
        const choices = form.querySelectorAll('.kw-branch-input');
        const sync = () => choices.forEach((input) => { input.disabled = !!all?.checked; });
        all?.addEventListener('change', sync);
        sync();
    });
});
</script>
@endsection
