@extends('admin.layouts.app')

@section('header', 'Sucursales')

@section('content')
<style>
    .branch-page { max-width: 1060px; margin: 0 auto; }
    .branch-hero { border-radius:18px; padding:24px; margin-bottom:20px; color:#fff; background:linear-gradient(120deg,#8e2500,#e85d04 55%,#ff8a19); }
    .branch-hero h2 { margin:0 0 6px; font-size:1.45rem; font-weight:900; }
    .branch-hero p { margin:0; opacity:.92; max-width:680px; }
    .branch-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px; }
    .branch-card { background:#fff; border:1px solid #e7e9ee; border-radius:16px; padding:18px; box-shadow:0 5px 16px rgba(15,23,42,.05); }
    .branch-card h3 { margin:0; font-size:1.03rem; font-weight:850; color:#162033; }
    .branch-card p { min-height:42px; margin:9px 0; color:#64748b; font-size:.86rem; line-height:1.45; }
    .branch-pill { display:inline-block; border-radius:999px; padding:4px 8px; font-size:.7rem; font-weight:800; background:#fff0df; color:#a53e00; }
    .branch-form { display:grid; gap:11px; }
    .branch-form label { display:grid; gap:5px; font-size:.78rem; font-weight:800; color:#475569; }
    .branch-form input,.branch-form textarea { width:100%; border:1px solid #cbd5e1; border-radius:9px; padding:9px 10px; font:inherit; font-size:.88rem; }
    .branch-form input:focus,.branch-form textarea:focus { outline:0; border-color:#e85d04; box-shadow:0 0 0 3px rgba(232,93,4,.12); }
    .branch-check { display:flex!important; grid-template-columns:auto 1fr; align-items:center; gap:7px; font-weight:700!important; }
    .branch-check input { width:auto!important; }
    .branch-save { border:0; border-radius:9px; padding:10px 13px; color:#fff; background:#e85d04; font:inherit; font-weight:800; cursor:pointer; }
    .branch-add { border:1px dashed #fdba74; background:#fffaf5; }
</style>

<div class="branch-page">
    <section class="branch-hero">
        <h2>Sucursales{{ $activeCompany ? ' — '.$activeCompany->name : '' }}</h2>
        <p>Define los locales que atienden pedidos. En caja podrás elegir la sucursal responsable; la comanda y los reportes conservarán esa referencia.</p>
    </section>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="branch-grid">
        @foreach($branches as $branch)
            <article class="branch-card">
                <div style="display:flex;align-items:start;justify-content:space-between;gap:10px">
                    <div><h3>{{ $branch->name }}</h3><span class="branch-pill">{{ $branch->code }}</span></div>
                    @if($branch->is_default)<span class="branch-pill" style="background:#dcfce7;color:#166534">Predeterminada</span>@endif
                </div>
                <p>{{ $branch->address ?: 'Sin dirección registrada' }}<br>{{ $branch->phone ?: 'Sin teléfono registrado' }}</p>
                <form class="branch-form" method="POST" action="{{ route('admin.branches.update', $branch) }}">
                    @csrf @method('PUT')
                    <label>Nombre<input name="name" required maxlength="120" value="{{ old('name', $branch->name) }}"></label>
                    <label>Código<input name="code" maxlength="24" value="{{ old('code', $branch->code) }}"></label>
                    <label>Teléfono<input name="phone" maxlength="30" value="{{ old('phone', $branch->phone) }}"></label>
                    <label>Dirección<textarea name="address" rows="2" maxlength="500">{{ old('address', $branch->address) }}</textarea></label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <label>Latitud<input name="latitude" type="text" inputmode="decimal" placeholder="-2.170998" value="{{ old('latitude', $branch->latitude) }}"></label>
                        <label>Longitud<input name="longitude" type="text" inputmode="decimal" placeholder="-79.922359" value="{{ old('longitude', $branch->longitude) }}"></label>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
                        <label>$ por km<input name="delivery_fee_per_unit" type="text" inputmode="decimal" placeholder="2.00" value="{{ old('delivery_fee_per_unit', $branch->delivery_fee_per_unit) }}"></label>
                        <label>Cada (km)<input name="delivery_fee_km_unit" type="text" inputmode="decimal" placeholder="5" value="{{ old('delivery_fee_km_unit', $branch->delivery_fee_km_unit) }}"></label>
                        <label>Mínimo $<input name="delivery_fee_minimum" type="text" inputmode="decimal" placeholder="2.00" value="{{ old('delivery_fee_minimum', $branch->delivery_fee_minimum) }}"></label>
                    </div>
                    <label class="branch-check"><input type="checkbox" name="is_default" value="1" @checked($branch->is_default)> Usar como sucursal predeterminada</label>
                    <label class="branch-check"><input type="checkbox" name="is_active" value="1" @checked($branch->is_active)> Sucursal activa</label>
                    <label class="branch-check"><input type="checkbox" name="dine_in_enabled" value="1" @checked($branch->dine_in_enabled)> Permite pedidos para servir en mesa</label>
                    <button class="branch-save">Guardar cambios</button>
                </form>
                @unless($branch->is_default)
                    <form method="POST" action="{{ route('admin.branches.destroy', $branch) }}" onsubmit="return confirm('¿Eliminar la sucursal {{ $branch->name }}?');" style="margin-top:8px">
                        @csrf @method('DELETE')
                        <button class="branch-save" style="background:#b91c1c" @if($branch->orders_count) disabled title="Tiene {{ $branch->orders_count }} pedido(s) históricos" @endif>Eliminar sucursal</button>
                    </form>
                @endunless
            </article>
        @endforeach

        <article class="branch-card branch-add">
            <h3>Nueva sucursal</h3>
            <p>Agrega un local o punto de venta adicional.</p>
            <form class="branch-form" method="POST" action="{{ route('admin.branches.store') }}">
                @csrf
                <label>Nombre<input name="name" required maxlength="120" placeholder="Ej.: Sucursal Centro"></label>
                <label>Código<input name="code" maxlength="24" placeholder="Ej.: KENNEDY"></label>
                <label>Teléfono<input name="phone" maxlength="30" placeholder="WhatsApp o teléfono"></label>
                <label>Dirección<textarea name="address" rows="2" maxlength="500" placeholder="Dirección o referencia"></textarea></label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <label>Latitud<input name="latitude" type="text" inputmode="decimal" placeholder="-2.170998"></label>
                    <label>Longitud<input name="longitude" type="text" inputmode="decimal" placeholder="-79.922359"></label>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
                    <label>$ por km<input name="delivery_fee_per_unit" type="text" inputmode="decimal" placeholder="2.00"></label>
                    <label>Cada (km)<input name="delivery_fee_km_unit" type="text" inputmode="decimal" placeholder="5"></label>
                    <label>Mínimo $<input name="delivery_fee_minimum" type="text" inputmode="decimal" placeholder="2.00"></label>
                </div>
                <label class="branch-check"><input type="checkbox" name="is_default" value="1"> Usar como predeterminada</label>
                <label class="branch-check"><input type="checkbox" name="is_active" value="1" checked> Sucursal activa</label>
                <label class="branch-check"><input type="checkbox" name="dine_in_enabled" value="1" checked> Permite pedidos para servir en mesa</label>
                <button class="branch-save">Crear sucursal</button>
            </form>
        </article>
    </div>
</div>
@endsection
