@extends('admin.layouts.app')

@section('header', 'Tienda en línea — '.$company->name)

@section('content')
<div class="max-w-5xl mx-auto px-4 pb-10">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <a href="{{ route('admin.empresas.whatsapp', $company) }}" class="text-sm text-gray-500 hover:text-gray-800">← Volver a la empresa</a>
            <h2 class="text-2xl font-bold text-gray-900 mt-2">Identidad de la tienda</h2>
            <p class="text-sm text-gray-600 mt-1">Estos valores se aplican al ecommerce, al punto de venta y a la toma manual de pedidos.</p>
        </div>
        <a href="{{ route('storefront.show', $company) }}" target="_blank" class="px-4 py-2 rounded-lg bg-gray-900 text-white text-sm font-semibold">Ver tienda ↗</a>
    </div>

    <form method="POST" action="{{ route('admin.empresas.storefront.update', $company) }}" enctype="multipart/form-data" class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        @csrf @method('PUT')
        <div class="p-6 grid md:grid-cols-2 gap-6">
            <section class="space-y-4">
                <div><h3 class="font-bold text-gray-900">Marca y colores</h3><p class="text-xs text-gray-500 mt-1">Para DPIKEOS se cargó la línea naranja, vino y amarillo como punto de partida.</p></div>
                @foreach(['primary_color' => 'Color principal', 'secondary_color' => 'Color oscuro', 'accent_color' => 'Color de acento'] as $field => $label)
                    <label class="block"><span class="text-sm font-medium text-gray-700">{{ $label }}</span><div class="flex gap-2 mt-1"><input type="color" name="{{ $field }}" value="{{ old($field, $settings->$field) }}" class="h-11 w-16 rounded border"><input value="{{ old($field, $settings->$field) }}" readonly class="flex-1 rounded-lg border-gray-300 bg-gray-50"></div></label>
                @endforeach
                <label class="block"><span class="text-sm font-medium text-gray-700">Logo</span><input type="file" name="logo" accept="image/*,.svg" class="mt-1 block w-full text-sm"></label>
                @if($settings->logoUrl())<img src="{{ $settings->logoUrl() }}" alt="Logo actual" class="h-24 w-24 object-contain rounded-xl border bg-white p-2">@endif
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Favicon de la tienda</span>
                        <input type="file" name="favicon" accept=".ico,image/png,image/jpeg,image/webp" class="mt-2 block w-full text-sm">
                        <small class="mt-2 block text-gray-500">Es el ícono que aparece en la pestaña del navegador. Usa una imagen cuadrada; recomendado: PNG de 512 × 512 px. Máximo 1 MB.</small>
                    </label>
                    @if($settings->faviconUrl())
                        <div class="mt-3 flex items-center gap-3">
                            <span class="grid h-14 w-14 place-items-center rounded-xl border bg-white">
                                <img src="{{ $settings->faviconUrl() }}" alt="Favicon actual" class="h-9 w-9 object-contain">
                            </span>
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" name="remove_favicon" value="1">
                                Quitar favicon actual
                            </label>
                        </div>
                    @endif
                </div>
                <label class="block"><span class="text-sm font-medium text-gray-700">Imagen principal</span><input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm"></label>
                @if($settings->heroImageUrl())<img src="{{ $settings->heroImageUrl() }}" alt="Portada actual" class="h-36 w-full object-cover rounded-xl border">@endif
            </section>

            <section class="space-y-4">
                <div><h3 class="font-bold text-gray-900">Dominio y mapas</h3><p class="text-xs text-gray-500 mt-1">La clave se cifra en la base de datos. En Google Cloud restringe su uso al dominio de esta tienda.</p></div>
                <label class="block"><span class="text-sm font-medium text-gray-700">Dominio propio</span><input name="custom_domain" value="{{ old('custom_domain', $settings->custom_domain) }}" placeholder="pedidos.miempresa.com" class="mt-1 w-full rounded-lg border-gray-300"></label>
                <label class="block"><span class="text-sm font-medium text-gray-700">Google Maps API key</span><input type="password" name="google_maps_api_key" autocomplete="new-password" placeholder="{{ filled($settings->google_maps_api_key) ? '•••••••••••••••••••• (ya configurada, déjalo vacío para no cambiarla)' : 'Aún no configurada' }}" class="mt-1 w-full rounded-lg border-gray-300"><small class="text-gray-500">Se usa para autocompletar direcciones y precisar la ubicación. Por seguridad, la clave guardada nunca se muestra acá -- este campo siempre aparece vacío al recargar, tengas o no una guardada; el texto gris de arriba es lo único que te dice si ya hay una.</small></label>
                <label class="block"><span class="text-sm font-medium text-gray-700">Google Maps Map ID</span><input name="google_maps_map_id" value="{{ old('google_maps_map_id', $settings->google_maps_map_id) }}" class="mt-1 w-full rounded-lg border-gray-300"></label>
                <div class="pt-3 border-t"><h3 class="font-bold text-gray-900">Acceso de clientes con Google</h3><p class="text-xs text-gray-500 mt-1">Crea un cliente OAuth de tipo Aplicación web y registra como URI de redirección exactamente la dirección mostrada abajo.</p></div>
                <label class="block"><span class="text-sm font-medium text-gray-700">Google OAuth Client ID</span><input type="password" name="google_oauth_client_id" autocomplete="new-password" placeholder="{{ filled($settings->google_oauth_client_id) ? '•••••••••••••• (ya configurado; vacío conserva el actual)' : 'Aún no configurado' }}" class="mt-1 w-full rounded-lg border-gray-300"></label>
                <label class="block"><span class="text-sm font-medium text-gray-700">Google OAuth Client Secret</span><input type="password" name="google_oauth_client_secret" autocomplete="new-password" placeholder="{{ filled($settings->google_oauth_client_secret) ? '•••••••••••••• (ya configurado; vacío conserva el actual)' : 'Aún no configurado' }}" class="mt-1 w-full rounded-lg border-gray-300"></label>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3"><span class="block text-xs font-bold text-amber-900">URI de redirección autorizada</span><code class="mt-1 block break-all text-xs text-amber-800 select-all">{{ route('storefront.account.google.callback', $company) }}</code></div>
                <label class="flex items-center gap-3 rounded-xl border p-4 bg-gray-50"><input type="checkbox" name="storefront_enabled" value="1" @checked(old('storefront_enabled', $settings->storefront_enabled))><span><strong class="block text-sm">Tienda pública activa</strong><small class="text-gray-500">Permite recibir pedidos desde la raíz o el enlace de esta empresa.</small></span></label>
            </section>
        </div>
        @if($errors->any())<div class="mx-6 mb-4 rounded-lg bg-red-50 text-red-700 p-3 text-sm">{{ $errors->first() }}</div>@endif
        <div class="px-6 py-4 bg-gray-50 border-t flex justify-end"><button class="px-5 py-2.5 rounded-lg text-white font-semibold" style="background:{{ $settings->primary_color }}">Guardar configuración</button></div>
    </form>
</div>
@endsection
