@extends('admin.layouts.app')

@section('header', 'Preguntas frecuentes')

@section('content')
<style>
    .faq-page { max-width: 900px; margin: 0 auto; }
    .faq-hero { border-radius:18px; padding:24px; margin-bottom:20px; color:#fff; background:linear-gradient(120deg,#8e2500,#e85d04 55%,#ff8a19); }
    .faq-hero h2 { margin:0 0 6px; font-size:1.45rem; font-weight:900; }
    .faq-hero p { margin:0; opacity:.92; max-width:680px; }
    .faq-list { display:flex; flex-direction:column; gap:14px; }
    .faq-card { background:#fff; border:1px solid #e7e9ee; border-radius:16px; padding:18px; box-shadow:0 5px 16px rgba(15,23,42,.05); }
    .faq-form { display:grid; gap:11px; }
    .faq-form label { display:grid; gap:5px; font-size:.78rem; font-weight:800; color:#475569; }
    .faq-form input, .faq-form textarea { width:100%; border:1px solid #cbd5e1; border-radius:9px; padding:9px 10px; font:inherit; font-size:.88rem; }
    .faq-form input:focus, .faq-form textarea:focus { outline:0; border-color:#e85d04; box-shadow:0 0 0 3px rgba(232,93,4,.12); }
    .faq-row { display:grid; grid-template-columns:1fr 140px; gap:8px; }
    .faq-check { display:flex!important; align-items:center; gap:7px; font-weight:700!important; }
    .faq-check input { width:auto!important; }
    .faq-actions { display:flex; justify-content:space-between; align-items:center; gap:8px; margin-top:4px; }
    .faq-save { border:0; border-radius:9px; padding:10px 13px; color:#fff; background:#e85d04; font:inherit; font-weight:800; cursor:pointer; }
    .faq-delete { border:0; border-radius:9px; padding:10px 13px; color:#fff; background:#b91c1c; font:inherit; font-weight:800; cursor:pointer; }
    .faq-add { border:1px dashed #fdba74; background:#fffaf5; }
    .faq-empty { text-align:center; padding:2rem; background:#fff; border:1px dashed #e2e8f0; border-radius:14px; color:#64748b; }
</style>

<div class="faq-page">
    <section class="faq-hero">
        <h2>Preguntas frecuentes</h2>
        <p>Lo que el bot muestra en "Información" cuando el cliente pregunta por cancelaciones, devoluciones, contacto de vendedoras, etc. El cliente toca una pregunta y el bot responde con la respuesta configurada acá.</p>
    </section>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="faq-list">
        @forelse($faqs as $faq)
            <article class="faq-card">
                <form class="faq-form" method="POST" action="{{ route('admin.faqs.update', $faq) }}">
                    @csrf @method('PUT')
                    <label>Pregunta<input name="question" required maxlength="200" value="{{ old('question', $faq->question) }}"></label>
                    <label>Respuesta<textarea name="answer" rows="3" required maxlength="2000">{{ old('answer', $faq->answer) }}</textarea></label>
                    <div class="faq-row">
                        <label class="faq-check"><input type="checkbox" name="is_active" value="1" @checked($faq->is_active)> Activa (visible para los clientes)</label>
                        <label>Orden<input name="sort_order" type="number" min="0" value="{{ old('sort_order', $faq->sort_order) }}"></label>
                    </div>
                    <div class="faq-actions">
                        <button type="submit" class="faq-save">Guardar cambios</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" onsubmit="return confirm('¿Eliminar esta pregunta frecuente?');" style="margin-top:8px">
                    @csrf @method('DELETE')
                    <button type="submit" class="faq-delete">Eliminar</button>
                </form>
            </article>
        @empty
            <div class="faq-empty">Todavía no hay preguntas frecuentes configuradas.</div>
        @endforelse

        <article class="faq-card faq-add">
            <h3 style="margin:0 0 6px;">Nueva pregunta frecuente</h3>
            <form class="faq-form" method="POST" action="{{ route('admin.faqs.store') }}">
                @csrf
                <label>Pregunta<input name="question" required maxlength="200" placeholder="Ej.: ¿Qué pasa si ya no quiero mi pedido?"></label>
                <label>Respuesta<textarea name="answer" rows="3" required maxlength="2000" placeholder="Ej.: Puedes cancelarlo sin costo mientras no esté confirmado..."></textarea></label>
                <div class="faq-row">
                    <label class="faq-check"><input type="checkbox" name="is_active" value="1" checked> Activa (visible para los clientes)</label>
                    <label>Orden<input name="sort_order" type="number" min="0" value="0"></label>
                </div>
                <button type="submit" class="faq-save">Crear pregunta</button>
            </form>
        </article>
    </div>
</div>
@endsection
