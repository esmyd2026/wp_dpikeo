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
    .branch-hours { display:grid; gap:6px; border-top:1px solid #eef0f3; padding-top:10px; margin-top:2px; }
    .branch-hours-title { font-size:.78rem; font-weight:800; color:#475569; margin:0 0 2px; }
    .branch-hours-row { display:grid; grid-template-columns:74px auto 1fr 1fr; align-items:center; gap:6px; font-size:.78rem; }
    .branch-hours-row label.branch-check { font-weight:600!important; font-size:.72rem; color:#64748b; }
    .branch-hours-row input[type="time"] { width:100%; border:1px solid #cbd5e1; border-radius:7px; padding:5px 6px; font:inherit; font-size:.8rem; }
    .branch-hours-row input[type="time"]:disabled { background:#f1f5f9; color:#94a3b8; }
    .branch-tiers { display:grid; gap:6px; border-top:1px solid #eef0f3; padding-top:10px; margin-top:2px; }
    .branch-tiers-title { font-size:.78rem; font-weight:800; color:#475569; margin:0; }
    .branch-tiers-hint { font-size:.72rem; color:#94a3b8; margin:0 0 2px; }
    .branch-tier-row { display:grid; grid-template-columns:1fr 1fr 1fr auto; align-items:center; gap:6px; }
    .branch-tier-row input { width:100%; border:1px solid #cbd5e1; border-radius:7px; padding:6px 7px; font:inherit; font-size:.8rem; }
    .branch-tier-remove { border:0; background:#fee2e2; color:#b91c1c; border-radius:7px; width:28px; height:28px; font-weight:800; cursor:pointer; }
    .branch-tier-add { border:1px dashed #fdba74; background:#fffaf5; color:#a53e00; border-radius:7px; padding:6px 8px; font-size:.76rem; font-weight:700; cursor:pointer; width:fit-content; }
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
                    <label>Información de reservas (se muestra en "Información" del bot)<textarea name="reservations_info" rows="2" maxlength="500" placeholder="Ej: Reservas al 099-123-4567, con 1 día de anticipación">{{ old('reservations_info', $branch->reservations_info) }}</textarea></label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <label>Latitud<input name="latitude" type="text" inputmode="decimal" placeholder="-2.170998" value="{{ old('latitude', $branch->latitude) }}"></label>
                        <label>Longitud<input name="longitude" type="text" inputmode="decimal" placeholder="-79.922359" value="{{ old('longitude', $branch->longitude) }}"></label>
                    </div>
                    <label>Costo mínimo de envío $ (si no se puede calcular por km)<input name="delivery_fee_minimum" type="text" inputmode="decimal" placeholder="2.00" value="{{ old('delivery_fee_minimum', $branch->delivery_fee_minimum) }}"></label>
                    <label class="branch-check"><input type="checkbox" name="is_default" value="1" @checked($branch->is_default)> Usar como sucursal predeterminada</label>
                    <label class="branch-check"><input type="checkbox" name="is_active" value="1" @checked($branch->is_active)> Sucursal activa (aparece en "Información" del bot)</label>
                    <label class="branch-check"><input type="checkbox" name="orders_enabled" value="1" @checked($branch->orders_enabled) title="Si la destildas, esta sucursal deja de poder elegirse para pedidos/delivery, pero sigue mostrándose en Información."> Disponible para pedidos/envíos</label>
                    <label class="branch-check"><input type="checkbox" name="dine_in_enabled" value="1" @checked($branch->dine_in_enabled)> Permite pedidos para servir en mesa</label>

                    <div class="branch-tiers js-tiers">
                        <p class="branch-tiers-title">Tarifas de delivery por km</p>
                        <p class="branch-tiers-hint">Desde qué km hasta qué km cuesta cuánto. Dejar "hasta" vacío = "en adelante". Se calcula solo cuando el cliente comparte su ubicación.</p>
                        @foreach($branch->deliveryFeeTiers as $i => $tier)
                            <div class="branch-tier-row">
                                <input type="text" inputmode="decimal" name="delivery_fee_tiers[{{ $i }}][from_km]" placeholder="Desde (km)" value="{{ $tier->from_km }}">
                                <input type="text" inputmode="decimal" name="delivery_fee_tiers[{{ $i }}][to_km]" placeholder="Hasta (km)" value="{{ $tier->to_km }}">
                                <input type="text" inputmode="decimal" name="delivery_fee_tiers[{{ $i }}][price]" placeholder="Precio $" value="{{ $tier->price }}">
                                <button type="button" class="branch-tier-remove js-tier-remove">&times;</button>
                            </div>
                        @endforeach
                        <button type="button" class="branch-tier-add js-tier-add">+ Agregar tramo</button>
                    </div>

                    <div class="branch-hours">
                        <p class="branch-hours-title">Horario de atención</p>
                        @foreach($branch->hoursByDay() as $day => $hour)
                            <div class="branch-hours-row">
                                <span>{{ $hour->dayLabel() }}</span>
                                <label class="branch-check">
                                    <input type="checkbox" class="js-day-closed" name="hours[{{ $day }}][is_closed]" value="1" @checked($hour->is_closed)> Cerrado
                                </label>
                                <input type="time" name="hours[{{ $day }}][opens_at]" value="{{ $hour->opens_at ? \Illuminate\Support\Carbon::parse($hour->opens_at)->format('H:i') : '' }}" @disabled($hour->is_closed)>
                                <input type="time" name="hours[{{ $day }}][closes_at]" value="{{ $hour->closes_at ? \Illuminate\Support\Carbon::parse($hour->closes_at)->format('H:i') : '' }}" @disabled($hour->is_closed)>
                            </div>
                        @endforeach
                    </div>

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
                <label>Información de reservas (se muestra en "Información" del bot)<textarea name="reservations_info" rows="2" maxlength="500" placeholder="Ej: Reservas al 099-123-4567, con 1 día de anticipación"></textarea></label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <label>Latitud<input name="latitude" type="text" inputmode="decimal" placeholder="-2.170998"></label>
                    <label>Longitud<input name="longitude" type="text" inputmode="decimal" placeholder="-79.922359"></label>
                </div>
                <label>Costo mínimo de envío $ (si no se puede calcular por km)<input name="delivery_fee_minimum" type="text" inputmode="decimal" placeholder="2.00"></label>
                <label class="branch-check"><input type="checkbox" name="is_default" value="1"> Usar como predeterminada</label>
                <label class="branch-check"><input type="checkbox" name="is_active" value="1" checked> Sucursal activa (aparece en "Información" del bot)</label>
                <label class="branch-check"><input type="checkbox" name="orders_enabled" value="1" checked title="Si la destildas, esta sucursal deja de poder elegirse para pedidos/delivery, pero sigue mostrándose en Información."> Disponible para pedidos/envíos</label>
                <label class="branch-check"><input type="checkbox" name="dine_in_enabled" value="1" checked> Permite pedidos para servir en mesa</label>

                <div class="branch-tiers js-tiers">
                    <p class="branch-tiers-title">Tarifas de delivery por km</p>
                    <p class="branch-tiers-hint">Desde qué km hasta qué km cuesta cuánto. Dejar "hasta" vacío = "en adelante". Se puede completar después.</p>
                    <button type="button" class="branch-tier-add js-tier-add">+ Agregar tramo</button>
                </div>

                <div class="branch-hours">
                    <p class="branch-hours-title">Horario de atención (opcional, se puede completar después)</p>
                    @foreach(\App\Models\BusinessBranchHour::DAYS as $day => $label)
                        <div class="branch-hours-row">
                            <span>{{ $label }}</span>
                            <label class="branch-check">
                                <input type="checkbox" class="js-day-closed" name="hours[{{ $day }}][is_closed]" value="1"> Cerrado
                            </label>
                            <input type="time" name="hours[{{ $day }}][opens_at]">
                            <input type="time" name="hours[{{ $day }}][closes_at]">
                        </div>
                    @endforeach
                </div>

                <button class="branch-save">Crear sucursal</button>
            </form>
        </article>
    </div>
</div>

<script>
    document.querySelectorAll('.js-day-closed').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const row = checkbox.closest('.branch-hours-row');
            row.querySelectorAll('input[type="time"]').forEach(function (input) {
                input.disabled = checkbox.checked;
                if (checkbox.checked) input.value = '';
            });
        });
    });

    function branchTierRow() {
        const key = 'new' + Date.now() + Math.floor(Math.random() * 1000);
        const row = document.createElement('div');
        row.className = 'branch-tier-row';
        row.innerHTML = `
            <input type="text" inputmode="decimal" name="delivery_fee_tiers[${key}][from_km]" placeholder="Desde (km)">
            <input type="text" inputmode="decimal" name="delivery_fee_tiers[${key}][to_km]" placeholder="Hasta (km)">
            <input type="text" inputmode="decimal" name="delivery_fee_tiers[${key}][price]" placeholder="Precio $">
            <button type="button" class="branch-tier-remove js-tier-remove">&times;</button>`;
        return row;
    }

    document.querySelectorAll('.js-tiers').forEach(function (container) {
        container.querySelector('.js-tier-add').addEventListener('click', function () {
            container.insertBefore(branchTierRow(), this);
        });
    });

    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('js-tier-remove')) {
            e.target.closest('.branch-tier-row').remove();
        }
    });
</script>
@endsection
