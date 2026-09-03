@extends('admin.layouts.app')

@section('header', 'Parámetros de plataforma')

@section('content')

<style>
    .platform-params { max-width: 960px; }
    .platform-section {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        margin-bottom: 1.25rem;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,.04);
    }
    .platform-section-head {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #f1f5f9;
        background: #f8fafc;
    }
    .platform-section-head h2 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #111827;
    }
    .platform-section-head p {
        margin: .35rem 0 0;
        font-size: .82rem;
        color: #6b7280;
    }
    .platform-section-body { padding: 1.25rem; }
    .platform-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    @media (max-width: 768px) {
        .platform-grid { grid-template-columns: 1fr; }
    }
    .platform-field label {
        display: block;
        font-size: .82rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: .35rem;
    }
    .platform-field input,
    .platform-field select,
    .platform-field textarea {
        width: 100%;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: .5rem .65rem;
        font-size: .875rem;
    }
    .platform-field input[type="checkbox"],
    .platform-field input[type="radio"] {
        width: auto;
        padding: 0;
    }
    .platform-field .hint {
        font-size: .72rem;
        color: #9ca3af;
        margin-top: .25rem;
    }
    .platform-save-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.25rem;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
    }
</style>

<div class="platform-params">
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="mb-4">
        <p class="text-sm text-gray-600 mb-0">
            Panel interno de super administrador: define el <strong><a href="#order-pdf" class="text-emerald-700">PDF de orden</a></strong>
            y los <strong>costos Meta WhatsApp</strong> que se reflejan en <a href="{{ route('admin.reports.whatsapp') }}" class="text-emerald-700">Reportes WhatsApp</a>.
        </p>
    </div>

    <section class="platform-section" id="catalogo-empresa">
        <div class="platform-section-head">
            <h2>🛒 Catálogo de la empresa activa</h2>
            <p>Este panel opera el menú de la empresa activa seleccionada. Administra categorías, productos, fotos, precios, variaciones y existencias desde el módulo Productos.</p>
        </div>
        <div class="platform-section-body">
            <a href="{{ route('admin.products.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold rounded-lg text-decoration-none">
                <i class="fas fa-box-open"></i> Administrar menú
            </a>
        </div>
    </section>

    @php $pdf = $orderPdfSettings ?? []; @endphp
    <form action="{{ route('admin.pricing-settings.update') }}" method="POST" id="form-order-pdf">
        @csrf
        @method('PUT')
        <input type="hidden" name="_section" value="order_pdf">

        <section class="platform-section" id="order-pdf">
            <div class="platform-section-head">
                <h2>📄 PDF de orden de pedido</h2>
                <p>Datos de la empresa y textos que aparecen en el PDF enviado al cliente (Ecuador).</p>
            </div>
            <div class="platform-section-body">
                <div class="platform-grid mb-4">
                    <div class="platform-field">
                        <label for="pdf_legal_name">Razón social / nombre legal *</label>
                        <input type="text" id="pdf_legal_name" name="legal_name" required maxlength="255"
                            value="{{ old('legal_name', $pdf['legal_name'] ?? '') }}">
                    </div>
                    <div class="platform-field">
                        <label for="pdf_trade_name">Nombre comercial</label>
                        <input type="text" id="pdf_trade_name" name="trade_name" maxlength="255"
                            value="{{ old('trade_name', $pdf['trade_name'] ?? '') }}">
                    </div>
                    <div class="platform-field">
                        <label for="pdf_ruc">RUC</label>
                        <input type="text" id="pdf_ruc" name="ruc" maxlength="20"
                            value="{{ old('ruc', $pdf['ruc'] ?? '') }}" placeholder="0990000001001">
                    </div>
                    <div class="platform-field">
                        <label for="pdf_phone">Teléfono</label>
                        <input type="text" id="pdf_phone" name="phone" maxlength="30"
                            value="{{ old('phone', $pdf['phone'] ?? '') }}">
                    </div>
                    <div class="platform-field" style="grid-column: 1 / -1;">
                        <label for="pdf_address">Dirección</label>
                        <input type="text" id="pdf_address" name="address" maxlength="500"
                            value="{{ old('address', $pdf['address'] ?? '') }}">
                    </div>
                    <div class="platform-field">
                        <label for="pdf_city">Ciudad / país</label>
                        <input type="text" id="pdf_city" name="city" maxlength="120"
                            value="{{ old('city', $pdf['city'] ?? 'Ecuador') }}">
                    </div>
                    <div class="platform-field">
                        <label for="pdf_email">Correo</label>
                        <input type="email" id="pdf_email" name="email" maxlength="255"
                            value="{{ old('email', $pdf['email'] ?? '') }}">
                    </div>
                    <div class="platform-field">
                        <label for="pdf_website">Sitio web</label>
                        <input type="text" id="pdf_website" name="website" maxlength="255"
                            value="{{ old('website', $pdf['website'] ?? '') }}" placeholder="www.miempresa.com">
                    </div>
                </div>

                <div class="platform-grid mb-4">
                    <div class="platform-field">
                        <label for="pdf_document_title">Título del documento *</label>
                        <input type="text" id="pdf_document_title" name="document_title" required maxlength="120"
                            value="{{ old('document_title', $pdf['document_title'] ?? 'ORDEN DE PEDIDO') }}">
                    </div>
                    <div class="platform-field">
                        <label for="pdf_document_subtitle">Subtítulo</label>
                        <input type="text" id="pdf_document_subtitle" name="document_subtitle" maxlength="255"
                            value="{{ old('document_subtitle', $pdf['document_subtitle'] ?? '') }}">
                    </div>
                    <div class="platform-field">
                        <label for="pdf_iva_rate">IVA (%)</label>
                        <input type="number" id="pdf_iva_rate" name="iva_rate_percent" min="0" max="100" required
                            value="{{ old('iva_rate_percent', $pdf['iva_rate_percent'] ?? 15) }}">
                        <p class="hint">En Ecuador suele ser 15%.</p>
                    </div>
                    <div class="platform-field">
                        <label for="pdf_timezone">Zona horaria</label>
                        <input type="text" id="pdf_timezone" name="timezone" required maxlength="64"
                            value="{{ old('timezone', $pdf['timezone'] ?? 'America/Guayaquil') }}">
                    </div>
                    <div class="platform-field" style="grid-column: 1 / -1;">
                        <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                            <input type="hidden" name="prices_include_iva" value="0">
                            <input type="checkbox" name="prices_include_iva" value="1"
                                @checked(old('prices_include_iva', $pdf['prices_include_iva'] ?? false))>
                            <span>Los precios del catálogo ya incluyen IVA</span>
                        </label>
                        <p class="hint mb-0">Si no está marcado, el PDF calcula subtotal + IVA sobre el total de líneas.</p>
                    </div>
                    <div class="platform-field" style="grid-column: 1 / -1;">
                        <label for="pdf_legal_footer">Nota legal al pie del PDF</label>
                        <textarea id="pdf_legal_footer" name="legal_footer" rows="3" maxlength="2000">{{ old('legal_footer', $pdf['legal_footer'] ?? '') }}</textarea>
                        <p class="hint mb-0">Ej: «Este documento no sustituye factura electrónica del SRI».</p>
                    </div>
                </div>
            </div>
            <div class="platform-save-bar" style="border-top: 1px solid #f1f5f9; border-radius: 0; margin: 0;">
                <p class="text-xs text-gray-500 mb-0">Estos datos se usan al descargar o enviar el PDF de pedidos.</p>
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg border-0">
                    <i class="fas fa-save"></i> Guardar PDF de orden
                </button>
            </div>
        </section>
    </form>

    <form action="{{ route('admin.pricing-settings.update') }}" method="POST" id="form-meta">
        @csrf
        @method('PUT')
        <input type="hidden" name="_section" value="meta">

        <section class="platform-section" id="costos-meta">
            <div class="platform-section-head">
                <h2>💬 Costos Meta WhatsApp</h2>
                <p>Tarifas internas y tipos de conversación visibles en <a href="{{ route('admin.reports.whatsapp') }}">Reportes WhatsApp</a>.</p>
            </div>
            <div class="platform-section-body">
                <div class="mb-5 p-4 rounded-xl border border-emerald-200 bg-emerald-50">
                    <h3 class="font-semibold text-gray-900 mb-2 text-sm">Tipos de conversación activos</h3>
                    <p class="text-sm text-gray-600 mb-3">Los desactivados no aparecen en Reportes WhatsApp.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @php
                            $categoryLabels = [
                                'service' => '💬 Atención al cliente (cuando escriben)',
                                'utility' => '📋 Avisos automáticos del bot',
                                'marketing' => '📢 Promociones / campañas',
                                'authentication' => '🔐 Códigos de verificación (OTP)',
                            ];
                        @endphp
                        @foreach($categoryKeys as $key)
                            <label class="flex items-start gap-2 text-sm cursor-pointer">
                                <input type="checkbox" name="enabled_categories[]" value="{{ $key }}"
                                    @checked(in_array($key, old('enabled_categories', $enabledCategories), true))
                                    class="mt-1 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                <span>{{ $categoryLabels[$key] ?? ucfirst($key) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                    <div class="platform-field">
                        <label>Factor de ajuste interno</label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="meta_markup" step="0.01" min="1" max="3"
                                value="{{ old('meta_markup', $settings->meta_markup) }}" required>
                            <span class="text-sm text-gray-500 whitespace-nowrap">× (ej. 1.30)</span>
                        </div>
                        @error('meta_markup')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="platform-field">
                        <label>Región</label>
                        <input type="text" name="region" value="{{ old('region', $settings->region) }}" required>
                    </div>
                    <div class="platform-field">
                        <label>Moneda</label>
                        <input type="text" name="currency" maxlength="3" value="{{ old('currency', $settings->currency) }}" class="uppercase" required>
                    </div>
                </div>

                <div class="space-y-4">
                    @foreach($categoryKeys as $key)
                        @php
                            $meta = $categories[$key] ?? [];
                            $rate = $settings->rates[$key] ?? ['min' => 0, 'max' => 0];
                            $appliedMin = round(($rate['min'] ?? 0) * $settings->meta_markup, 4);
                            $appliedMax = round(($rate['max'] ?? 0) * $settings->meta_markup, 4);
                            $isEnabled = in_array($key, old('enabled_categories', $enabledCategories), true);
                        @endphp
                        <div class="border rounded-xl p-4 {{ $isEnabled ? 'border-gray-200 bg-gray-50' : 'border-dashed border-gray-300 bg-gray-100 opacity-80' }}">
                            <div class="flex items-start gap-3 mb-3">
                                <span class="text-2xl">{{ $meta['icon'] ?? '💬' }}</span>
                                <div>
                                    <h3 class="font-semibold text-gray-900 text-sm">
                                        {{ $meta['label'] ?? ucfirst($key) }}
                                        @unless($isEnabled)
                                            <span class="text-xs font-normal text-gray-500">(inactivo para el cliente)</span>
                                        @endunless
                                    </h3>
                                    <p class="text-sm text-gray-500">{{ $meta['description'] ?? '' }}</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="platform-field">
                                    <label>Tarifa base mín. (USD)</label>
                                    <input type="number" name="rates[{{ $key }}][min]" step="0.0001" min="0"
                                        value="{{ old("rates.{$key}.min", $rate['min']) }}" required>
                                </div>
                                <div class="platform-field">
                                    <label>Tarifa base máx. (USD)</label>
                                    <input type="number" name="rates[{{ $key }}][max]" step="0.0001" min="0"
                                        value="{{ old("rates.{$key}.max", $rate['max']) }}" required>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">
                                Aplicado a reportes: <strong>${{ number_format($appliedMin, 4) }}</strong> — <strong>${{ number_format($appliedMax, 4) }}</strong> por conversación
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="platform-save-bar" style="border-top: 1px solid #f1f5f9; border-radius: 0; margin: 0;">
                <p class="text-xs text-gray-500 mb-0 max-w-lg">
                    Tarifas Meta y tipos de conversación visibles en Reportes WhatsApp.
                </p>
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg border-0">
                    <i class="fas fa-save"></i> Guardar costos Meta
                </button>
            </div>
        </section>
    </form>
</div>
@endsection

@push('scripts')
<script>
if (window.location.hash) {
    const target = document.querySelector(window.location.hash);
    if (target) {
        setTimeout(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
    }
}
</script>
@endpush
