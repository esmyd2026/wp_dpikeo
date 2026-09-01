@extends('admin.layouts.app')

@section('header', 'Configuración del Chatbot')

@section('content')
<div class="bg-white shadow-sm rounded-lg overflow-hidden">
    <div class="p-6">
        <form action="{{ route('admin.chatbot.config.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Configuración General</h3>

                    <div>
                        <label for="bot_name" class="block text-sm font-medium text-gray-700">Nombre del Bot</label>
                        <input type="text" id="bot_name" name="bot_name" value="{{ old('bot_name', $config->bot_name ?? '') }}" required
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">Usa <code>@{{nombre_bot}}</code> en mensajes del flujo de marketing para mostrar este nombre.</p>
                    </div>

                    <div>
                        <label for="welcome_message" class="block text-sm font-medium text-gray-700">Mensaje de Bienvenida</label>
                        <textarea id="welcome_message" name="welcome_message" rows="3" required
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">{{ old('welcome_message', $config->welcome_message ?? '') }}</textarea>
                    </div>

                    <div>
                        <label for="fallback_message" class="block text-sm font-medium text-gray-700">Mensaje de Fallback</label>
                        <textarea id="fallback_message" name="fallback_message" rows="3" required
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">{{ old('fallback_message', $config->fallback_message ?? '') }}</textarea>
                    </div>

                    <div>
                        <label for="response_delay" class="block text-sm font-medium text-gray-700">Retraso de Respuesta (ms)</label>
                        <input type="number" id="response_delay" name="response_delay" value="{{ $config->response_delay ?? 1000 }}" min="0" max="5000" step="100"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="abandoned_cart_timeout_minutes" class="block text-sm font-medium text-gray-700">Cancelar pedidos abandonados después de (minutos)</label>
                        <input type="number" id="abandoned_cart_timeout_minutes" name="abandoned_cart_timeout_minutes"
                            value="{{ old('abandoned_cart_timeout_minutes', $config->metadata['abandoned_cart_timeout_minutes'] ?? '') }}"
                            min="5" max="10080" step="5" placeholder="Vacío = desactivado"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">
                            Si un cliente deja un pedido a medias (por ejemplo, sin mandar el comprobante) y pasa este
                            tiempo sin actividad, el bot cancela ese pedido automáticamente, le avisa por WhatsApp y
                            reinicia su conversación para que pueda empezar un pedido nuevo limpio. Déjalo vacío para
                            desactivar esta función.
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="iva_enabled" name="iva_enabled" value="1"
                                {{ old('iva_enabled', $config->metadata['iva_enabled'] ?? false) ? 'checked' : '' }}
                                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <label for="iva_enabled" class="text-sm font-medium text-gray-700">Mostrarle al cliente el desglose de IVA en sus pedidos</label>
                        </div>
                        <div class="mt-2 max-w-xs">
                            <label for="iva_percentage" class="block text-sm font-medium text-gray-700">Porcentaje de IVA (%)</label>
                            <input type="number" id="iva_percentage" name="iva_percentage" step="0.01" min="0" max="100"
                                value="{{ old('iva_percentage', $config->metadata['iva_percentage'] ?? '') }}"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Tus precios de catálogo ya incluyen el IVA — esto no le suma nada al total ni lo cambia,
                            solo le muestra al cliente cuánto de lo que pagó corresponde a IVA. Si lo dejas
                            desactivado, el pedido no menciona el IVA por separado (como hasta ahora).
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="bank_transfer_instructions" class="block text-sm font-medium text-gray-700">
                            Datos para transferencias o depósitos
                        </label>
                        <textarea id="bank_transfer_instructions" name="bank_transfer_instructions" rows="4" maxlength="1500"
                            placeholder="Ej: Banco Mercantil&#10;Cuenta corriente: 0105-1234-56-1234567890&#10;Titular: DPIKEOS C.A. — RIF J-12345678-9&#10;Zelle: pagos@dpikeos.com"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">{{ old('bank_transfer_instructions', $config->metadata['bank_transfer_instructions'] ?? '') }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">
                            Se le envía al cliente junto con el mensaje de costo confirmado cuando su
                            pedido se paga por transferencia o depósito (con envío, para llevar o retiro en local).
                            Déjalo vacío si no aplica.
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="card_payment_url" class="block text-sm font-medium text-gray-700">
                            Link de pago con tarjeta (página externa)
                        </label>
                        <input type="url" id="card_payment_url" name="card_payment_url" maxlength="500"
                            placeholder="https://tu-pagina-de-pago.com"
                            value="{{ old('card_payment_url', $config->metadata['card_payment_url'] ?? '') }}"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">
                            URL del sitio externo donde el cliente paga con tarjeta por su cuenta (no procesamos
                            pagos con tarjeta aquí). Debe empezar con <code>https://</code> — WhatsApp no permite
                            enlaces sin cifrar. Si la dejas vacía, la opción "Pago con tarjeta" no aparecerá en el
                            chat.
                        </p>
                        @error('card_payment_url')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="card_payment_message" class="block text-sm font-medium text-gray-700">
                            Mensaje al mandar el link de pago con tarjeta
                        </label>
                        <textarea id="card_payment_message" name="card_payment_message" rows="3" maxlength="1000"
                            placeholder="💳 Puedes pagar con tarjeta directamente aquí:"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">{{ old('card_payment_message', $config->metadata['card_payment_message'] ?? '') }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">
                            Texto que acompaña el botón con el link de arriba. Se manda apenas el cliente elige
                            "Pago con tarjeta" — el bot no le pregunta nada más después de esto.
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">
                            Consulta de datos de delivery por WhatsApp
                        </label>
                        <p class="mt-1 text-xs text-gray-500">
                            Le permite a tu equipo (no a los clientes) escribirle al número del bot desde SU propio
                            WhatsApp con una palabra clave, y que el bot le responda con los datos del pedido listos
                            para reenviar al repartidor: número de pedido, a quién entregar, dirección y forma de pago.
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
                            <div>
                                <label for="delivery_dispatch_keyword" class="block text-xs font-medium text-gray-700">Palabra clave</label>
                                <input type="text" id="delivery_dispatch_keyword" name="delivery_dispatch_keyword" maxlength="30"
                                    value="{{ old('delivery_dispatch_keyword', $config->metadata['delivery_dispatch_keyword'] ?? '2501') }}"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="delivery_dispatch_numbers" class="block text-xs font-medium text-gray-700">Números autorizados (separados por coma)</label>
                                <input type="text" id="delivery_dispatch_numbers" name="delivery_dispatch_numbers" maxlength="500"
                                    placeholder="593999111222, 593999333444"
                                    value="{{ old('delivery_dispatch_numbers', $config->metadata['delivery_dispatch_numbers'] ?? '') }}"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Solo estos números pueden usar la palabra clave — para cualquier otro contacto (incluidos
                            los clientes), el bot la ignora por completo. Déjalo vacío para desactivar esta función.
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="privacy_notice_enabled" name="privacy_notice_enabled" value="1"
                                {{ old('privacy_notice_enabled', $config->metadata['privacy_notice_enabled'] ?? false) ? 'checked' : '' }}
                                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <label for="privacy_notice_enabled" class="text-sm font-medium text-gray-700">Enviar aviso de protección de datos a clientes nuevos</label>
                        </div>
                        <div class="mt-2">
                            <label for="privacy_notice_text" class="block text-sm font-medium text-gray-700">Texto del aviso</label>
                            <textarea id="privacy_notice_text" name="privacy_notice_text" rows="3" maxlength="1024"
                                placeholder="Tus datos serán utilizados para procesar tus pedidos y ofrecerte promociones. Consulta nuestra política de privacidad:"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">{{ old('privacy_notice_text', $config->metadata['privacy_notice_text'] ?? '') }}</textarea>
                        </div>
                        <div class="mt-2 max-w-md">
                            <label for="privacy_notice_link" class="block text-sm font-medium text-gray-700">Link a la política de privacidad</label>
                            <input type="url" id="privacy_notice_link" name="privacy_notice_link"
                                value="{{ old('privacy_notice_link', $config->metadata['privacy_notice_link'] ?? url('/privacidad')) }}"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Se envía como mensaje aparte, antes del saludo, solo la primera vez que un cliente le
                            escribe al bot. Si desde el panel de chat reinicias la conversación de un cliente, se le
                            vuelve a enviar la próxima vez que escriba.
                        </p>
                    </div>
                </div>

                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Apariencia</h3>
                    <p class="text-sm text-gray-500">El color primario pinta las burbujas del bot en el chat. El secundario se usa en encabezados y acentos del flujo de marketing.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="primary_color" class="block text-sm font-medium text-gray-700">Color primario (burbujas bot)</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="color" id="primary_color" name="primary_color" value="{{ old('primary_color', $config->primary_color ?? '#005c4b') }}"
                                    class="h-10 w-14 border border-gray-300 rounded-md cursor-pointer p-1">
                                <input type="text" id="primary_color_hex" maxlength="7" pattern="#?[0-9a-fA-F]{6}"
                                    value="{{ old('primary_color', $config->primary_color ?? '#005c4b') }}"
                                    class="flex-1 border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm font-mono uppercase">
                            </div>
                        </div>
                        <div>
                            <label for="secondary_color" class="block text-sm font-medium text-gray-700">Color secundario (acentos)</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="color" id="secondary_color" name="secondary_color" value="{{ old('secondary_color', $config->secondary_color ?? '#075e54') }}"
                                    class="h-10 w-14 border border-gray-300 rounded-md cursor-pointer p-1">
                                <input type="text" id="secondary_color_hex" maxlength="7" pattern="#?[0-9a-fA-F]{6}"
                                    value="{{ old('secondary_color', $config->secondary_color ?? '#075e54') }}"
                                    class="flex-1 border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm font-mono uppercase">
                            </div>
                        </div>
                    </div>

                    <div id="color-preview" class="rounded-lg border border-gray-200 p-4 bg-gray-50">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Vista previa</p>
                        <div class="flex flex-wrap items-end gap-4">
                            <div>
                                <div id="preview-bubble" class="inline-block px-3 py-2 rounded-lg text-white text-sm shadow-sm" style="background: {{ $config->primary_color ?? '#005c4b' }}; border-top-right-radius: 0;">
                                    Mensaje del bot
                                </div>
                            </div>
                            <div id="preview-header" class="px-3 py-2 rounded-md text-white text-sm font-medium" style="background: linear-gradient(135deg, {{ $config->secondary_color ?? '#075e54' }}, {{ $config->primary_color ?? '#005c4b' }});">
                                Encabezado / flujo
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="bot_avatar" class="block text-sm font-medium text-gray-700">Avatar del Bot</label>
                        <div class="mt-2 flex items-start gap-4">
                            <div id="bot-avatar-preview-wrap" class="flex-shrink-0 w-16 h-16 rounded-full overflow-hidden border border-gray-200 bg-gray-100 flex items-center justify-center {{ ($config->bot_avatar_url ?? null) ? '' : 'hidden' }}">
                                <img id="bot-avatar-preview" src="{{ $config->bot_avatar_url ?? '' }}" alt="Avatar del bot" class="w-full h-full object-cover">
                            </div>
                            <div id="bot-avatar-placeholder" class="flex-shrink-0 w-16 h-16 rounded-full border border-dashed border-gray-300 bg-gray-50 flex items-center justify-center text-gray-400 {{ ($config->bot_avatar_url ?? null) ? 'hidden' : '' }}">
                                <i class="fas fa-robot text-xl"></i>
                            </div>
                            <div class="flex-1 space-y-2">
                                <input type="file" id="bot_avatar_image" name="bot_avatar_image" accept="image/jpeg,image/png,image/jpg,image/webp"
                                    class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                <input type="url" id="bot_avatar" name="bot_avatar" value="{{ old('bot_avatar', $config->bot_avatar ?? '') }}" placeholder="O pega la URL de la imagen"
                                    class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                @if($config->bot_avatar_url ?? null)
                                <label class="inline-flex items-center text-sm text-gray-600">
                                    <input type="checkbox" name="remove_bot_avatar" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                                    Eliminar avatar actual
                                </label>
                                @endif
                                <p class="text-xs text-gray-500">Se muestra en el chat del panel y en la vista previa del flujo.</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="font_family" class="block text-sm font-medium text-gray-700">Fuente</label>
                        <select id="font_family" name="font_family"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="Arial" {{ ($config->font_family ?? '') === 'Arial' ? 'selected' : '' }}>Arial</option>
                            <option value="Helvetica" {{ ($config->font_family ?? '') === 'Helvetica' ? 'selected' : '' }}>Helvetica</option>
                            <option value="Roboto" {{ ($config->font_family ?? '') === 'Roboto' ? 'selected' : '' }}>Roboto</option>
                            <option value="Open Sans" {{ ($config->font_family ?? '') === 'Open Sans' ? 'selected' : '' }}>Open Sans</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-6 bg-gray-50 p-6 rounded-lg">
                <h3 class="text-lg font-medium text-gray-900 mb-4">🔔 Configuración de Monitoreo</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Recibe notificaciones cada vez que alguien escriba al bot. Las notificaciones se enviarán por WhatsApp y/o Email.
                </p>

                <div class="space-y-4">
                    <div class="flex items-center">
                        <input type="checkbox" id="monitoring_enabled" name="monitoring_enabled" value="1"
                            {{ ($config->monitoring_enabled ?? false) ? 'checked' : '' }}
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="monitoring_enabled" class="ml-2 block text-sm font-medium text-gray-700">
                            Habilitar monitoreo
                        </label>
                    </div>

                    <div>
                        <label for="monitoring_phone_number" class="block text-sm font-medium text-gray-700">
                            Número de WhatsApp para monitoreo
                        </label>
                        <input type="text" id="monitoring_phone_number" name="monitoring_phone_number" 
                            value="{{ $config->monitoring_phone_number ?? '' }}" 
                            placeholder="Ej: 521234567890 (con código de país)"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">
                            Número donde recibirás las notificaciones por WhatsApp. Debe incluir el código de país sin el signo +.
                        </p>
                    </div>

                    <div>
                        <label for="monitoring_email" class="block text-sm font-medium text-gray-700">
                            Email para monitoreo
                        </label>
                        <input type="email" id="monitoring_email" name="monitoring_email" 
                            value="{{ $config->monitoring_email ?? '' }}" 
                            placeholder="ejemplo@correo.com"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">
                            Email donde recibirás las notificaciones por correo electrónico.
                        </p>
                    </div>
                </div>
            </div>

            @php($landing = array_merge(\App\Http\Controllers\LandingController::DEFAULTS, $config->metadata['landing'] ?? []))
            <div class="mt-6 bg-gray-50 p-6 rounded-lg">
                <h3 class="text-lg font-medium text-gray-900 mb-1">🌐 Página de inicio pública (/)</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Contenido de la landing que ve cualquier visitante en la raíz del sitio, antes de iniciar sesión.
                </p>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="landing_accent_color" class="block text-sm font-medium text-gray-700">Color de acento</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="color" id="landing_accent_color" name="landing_accent_color" value="{{ old('landing_accent_color', $landing['accent_color']) }}"
                                    class="h-10 w-14 border border-gray-300 rounded-md cursor-pointer p-1">
                                <input type="text" id="landing_accent_color_hex" maxlength="7" pattern="#?[0-9a-fA-F]{6}"
                                    value="{{ old('landing_accent_color', $landing['accent_color']) }}"
                                    class="flex-1 border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm font-mono uppercase">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Botones, resaltados y el degradado de las bandas de llamado a la acción.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Logo</label>
                            <div class="mt-1 flex items-center gap-3">
                                <img src="{{ $landing['logo_path'] ? asset('storage/'.$landing['logo_path']) : asset('storage/img/dpikeologo.jpg') }}"
                                    alt="Logo" class="w-12 h-12 rounded-lg object-contain border border-gray-200 bg-white">
                                <div class="flex-1 space-y-1">
                                    <input type="file" id="landing_logo_image" name="landing_logo_image" accept="image/jpeg,image/png,image/jpg,image/webp"
                                        class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                    @if($landing['logo_path'])
                                        <label class="inline-flex items-center text-xs text-gray-600">
                                            <input type="checkbox" name="remove_landing_logo" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-1">
                                            Quitar logo (vuelve al logo por defecto)
                                        </label>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Sección principal (hero)</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="landing_hero_eyebrow" class="block text-sm font-medium text-gray-700">Etiqueta pequeña</label>
                                <input type="text" id="landing_hero_eyebrow" name="landing_hero_eyebrow" value="{{ old('landing_hero_eyebrow', $landing['hero_eyebrow']) }}" maxlength="80"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                            </div>
                            <div>
                                <label for="landing_order_cta_label" class="block text-sm font-medium text-gray-700">Texto del botón "Pedir por WhatsApp"</label>
                                <input type="text" id="landing_order_cta_label" name="landing_order_cta_label" value="{{ old('landing_order_cta_label', $landing['order_cta_label']) }}" maxlength="60"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                            </div>
                        </div>
                        <div class="mt-4">
                            <label for="landing_hero_title" class="block text-sm font-medium text-gray-700">Título grande</label>
                            <input type="text" id="landing_hero_title" name="landing_hero_title" value="{{ old('landing_hero_title', $landing['hero_title']) }}" maxlength="160"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                        </div>
                        <div class="mt-4">
                            <label for="landing_hero_title_highlight" class="block text-sm font-medium text-gray-700">Parte del título a resaltar en verde</label>
                            <input type="text" id="landing_hero_title_highlight" name="landing_hero_title_highlight" value="{{ old('landing_hero_title_highlight', $landing['hero_title_highlight']) }}" maxlength="80"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                            <p class="mt-1 text-xs text-gray-500">Debe ser una frase que aparezca tal cual dentro del título de arriba.</p>
                        </div>
                        <div class="mt-4">
                            <label for="landing_hero_subtitle" class="block text-sm font-medium text-gray-700">Subtítulo</label>
                            <textarea id="landing_hero_subtitle" name="landing_hero_subtitle" rows="2" maxlength="400"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">{{ old('landing_hero_subtitle', $landing['hero_subtitle']) }}</textarea>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Chat de ejemplo (teléfono)</p>
                        <p class="text-xs text-gray-500 mb-2">Usa @{{negocio}} para que se reemplace por el nombre del negocio.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <input type="text" name="landing_phone_msg_1" placeholder="Mensaje 1 (bot)" value="{{ old('landing_phone_msg_1', $landing['phone_msg_1']) }}" maxlength="200" class="border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                            <input type="text" name="landing_phone_msg_2" placeholder="Mensaje 2 (cliente)" value="{{ old('landing_phone_msg_2', $landing['phone_msg_2']) }}" maxlength="200" class="border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                            <input type="text" name="landing_phone_msg_3" placeholder="Mensaje 3 (bot)" value="{{ old('landing_phone_msg_3', $landing['phone_msg_3']) }}" maxlength="200" class="border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                            <input type="text" name="landing_phone_msg_4" placeholder="Mensaje 4 (bot, cierre)" value="{{ old('landing_phone_msg_4', $landing['phone_msg_4']) }}" maxlength="200" class="border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                            <input type="text" name="landing_phone_btn_1" placeholder="Botón 1" value="{{ old('landing_phone_btn_1', $landing['phone_btn_1']) }}" maxlength="60" class="border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                            <input type="text" name="landing_phone_btn_2" placeholder="Botón 2" value="{{ old('landing_phone_btn_2', $landing['phone_btn_2']) }}" maxlength="60" class="border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">"Ordena en línea" (sin WhatsApp)</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="landing_order_online_title" class="block text-sm font-medium text-gray-700">Título</label>
                                <input type="text" id="landing_order_online_title" name="landing_order_online_title" value="{{ old('landing_order_online_title', $landing['order_online_title']) }}" maxlength="120"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                            </div>
                            <div>
                                <label for="landing_order_online_cta_label" class="block text-sm font-medium text-gray-700">Texto del botón</label>
                                <input type="text" id="landing_order_online_cta_label" name="landing_order_online_cta_label" value="{{ old('landing_order_online_cta_label', $landing['order_online_cta_label']) }}" maxlength="60"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                            </div>
                        </div>
                        <div class="mt-4">
                            <label for="landing_order_online_text" class="block text-sm font-medium text-gray-700">Texto</label>
                            <textarea id="landing_order_online_text" name="landing_order_online_text" rows="2" maxlength="400"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">{{ old('landing_order_online_text', $landing['order_online_text']) }}</textarea>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Los 3 pasos</p>
                        @foreach ([1, 2, 3] as $n)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                                <input type="text" name="landing_step_{{ $n }}_title" placeholder="Título del paso {{ $n }}" value="{{ old('landing_step_'.$n.'_title', $landing['step_'.$n.'_title']) }}" maxlength="80" class="border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                                <input type="text" name="landing_step_{{ $n }}_text" placeholder="Texto del paso {{ $n }}" value="{{ old('landing_step_'.$n.'_text', $landing['step_'.$n.'_text']) }}" maxlength="300" class="border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Banda final de llamado a la acción</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="landing_cta_band_title" class="block text-sm font-medium text-gray-700">Título</label>
                                <input type="text" id="landing_cta_band_title" name="landing_cta_band_title" value="{{ old('landing_cta_band_title', $landing['cta_band_title']) }}" maxlength="120"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                            </div>
                            <div>
                                <label for="landing_cta_band_subtitle" class="block text-sm font-medium text-gray-700">Subtítulo</label>
                                <input type="text" id="landing_cta_band_subtitle" name="landing_cta_band_subtitle" value="{{ old('landing_cta_band_subtitle', $landing['cta_band_subtitle']) }}" maxlength="300"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                            </div>
                        </div>
                    </div>

                    <a href="{{ url('/') }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800">
                        <i class="fas fa-arrow-up-right-from-square mr-1"></i> Ver la página de inicio
                    </a>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-save mr-2"></i>Guardar Configuración
                </button>
            </div>
        </form>

        <div class="mt-6 bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-medium text-gray-900 mb-1">📱 Credenciales de WhatsApp Cloud API</h3>
            <p class="text-sm text-gray-600 mb-4">
                Estos son los datos que conectan el bot con tu número real de WhatsApp en Meta. Sin esto configurado
                correctamente, el bot no puede enviar ni recibir mensajes. Los obtienes en
                <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline">developers.facebook.com/apps</a>
                → tu app → <strong>WhatsApp → Configuración de la API</strong>.
            </p>

            @if($businessProfile && ($businessProfile->phone_number_id === 'PENDIENTE_CONFIGURAR' || $businessProfile->access_token === 'PENDIENTE_CONFIGURAR'))
                <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
                    <i class="fas fa-triangle-exclamation mr-1"></i>
                    Todavía tienes valores de ejemplo sin configurar (<code>PENDIENTE_CONFIGURAR</code>). El bot no podrá
                    enviar ni recibir mensajes reales hasta que completes los datos de abajo.
                </div>
            @endif

            <form action="{{ route('admin.chatbot.whatsapp.update') }}" method="POST" class="bg-white border border-gray-200 rounded-lg p-4 space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="phone_number" class="block text-sm font-medium text-gray-700">Número de WhatsApp</label>
                    <input type="text" id="phone_number" name="phone_number"
                        value="{{ old('phone_number', $businessProfile->phone_number ?? '') }}"
                        placeholder="Ej: 593994281769" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <p class="mt-1 text-xs text-gray-500">
                        El número real que tus clientes usan para escribirle al bot, con código de país y sin
                        signos ni espacios (ej. 593994281769). Es solo referencia interna, no cambia el número dado
                        de alta en Meta.
                    </p>
                    @error('phone_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="phone_number_id" class="block text-sm font-medium text-gray-700">Phone Number ID</label>
                    <input type="text" id="phone_number_id" name="phone_number_id"
                        value="{{ old('phone_number_id', $businessProfile->phone_number_id ?? '') }}"
                        placeholder="Ej: 123456789012345" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm font-mono">
                    <p class="mt-1 text-xs text-gray-500">
                        Identificador numérico que Meta le asigna a tu número de WhatsApp (no es el número de
                        teléfono). Aparece en <strong>WhatsApp → Configuración de la API</strong> como
                        «Identificador de número de teléfono» / «Phone number ID». Es el dato que más comúnmente
                        se necesita para que el bot pueda enviar mensajes.
                    </p>
                    @error('phone_number_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="whatsapp_business_id" class="block text-sm font-medium text-gray-700">
                        WhatsApp Business Account ID <span class="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <input type="text" id="whatsapp_business_id" name="whatsapp_business_id"
                        value="{{ old('whatsapp_business_id', $businessProfile->whatsapp_business_id ?? '') }}"
                        placeholder="Ej: 987654321098765"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm font-mono">
                    <p class="mt-1 text-xs text-gray-500">
                        Identificador de la cuenta de WhatsApp Business (WABA) en Meta Business Manager. No es
                        obligatorio para que el bot funcione, pero lo usan algunos reportes y plantillas de Meta.
                    </p>
                </div>

                <div>
                    <label for="access_token" class="block text-sm font-medium text-gray-700">Token de acceso permanente</label>
                    <input type="password" id="access_token" name="access_token" autocomplete="new-password"
                        placeholder="{{ $businessProfile && $businessProfile->access_token && $businessProfile->access_token !== 'PENDIENTE_CONFIGURAR' ? '•••••••••••••••••••• (ya configurado, déjalo vacío para no cambiarlo)' : 'Aún no configurado' }}"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm font-mono">
                    <p class="mt-1 text-xs text-gray-500">
                        Por seguridad, el token guardado nunca se muestra aquí. Pega uno nuevo solo si quieres
                        reemplazarlo; si lo dejas en blanco, se conserva el que ya está guardado. Genera un token
                        <strong>permanente</strong> (no el temporal de 24 h) desde
                        <strong>Meta Business Manager → Usuarios del sistema</strong>, con permiso
                        <code>whatsapp_business_messaging</code>.
                    </p>
                    @error('access_token')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="rounded-md bg-blue-50 border border-blue-100 px-3 py-2 text-xs text-blue-800">
                    <i class="fas fa-circle-info mr-1"></i>
                    El <strong>token de verificación del webhook</strong> (<code>WEBHOOK_VERIFY_TOKEN</code>) y el
                    <strong>App Secret</strong> (<code>WHATSAPP_APP_SECRET</code>) no se configuran aquí — esos van
                    directo en el archivo <code>.env</code> del servidor.
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Guardar credenciales de WhatsApp
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-6 bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-medium text-gray-900 mb-1">✉️ Mensajes automáticos al cliente</h3>
            <p class="text-sm text-gray-600 mb-4">
                Textos que el sistema le envía al cliente por WhatsApp cuando pasan cosas en la plataforma (cambio de estado del pedido, costo de envío confirmado, etc.). No es el flujo conversacional del bot — eso se edita desde <a href="{{ route('admin.marketing-flow.edit') }}" class="text-blue-600 underline">Flujo de marketing</a>.
            </p>

            <div class="space-y-4">
                @foreach($messageTemplates as $template)
                    <form action="{{ route('admin.chatbot.message-templates.update', $template) }}" method="POST" class="bg-white border border-gray-200 rounded-lg p-4">
                        @csrf
                        @method('PUT')
                        <label for="template_body_{{ $template->id }}" class="block text-sm font-medium text-gray-700">{{ $template->name }}</label>
                        <textarea id="template_body_{{ $template->id }}" name="body" rows="4" required
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 font-mono text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">{{ old('body', $template->body) }}</textarea>
                        @if(!empty($template->placeholders))
                            <p class="mt-1 text-xs text-gray-500">
                                Variables disponibles:
                                @foreach($template->placeholders as $placeholder)
                                    @php($placeholderTag = '{{' . $placeholder . '}}')
                                    <code class="bg-gray-100 px-1 rounded">{{ $placeholderTag }}</code>@if(!$loop->last), @endif
                                @endforeach
                            </p>
                        @endif
                        <div class="mt-2 flex justify-end">
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-transparent rounded-md shadow-sm text-xs font-medium text-white bg-blue-600 hover:bg-blue-700">
                                <i class="fas fa-save mr-1"></i>Guardar mensaje
                            </button>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    const fileInput = document.getElementById('bot_avatar_image');
    const preview = document.getElementById('bot-avatar-preview');
    const previewWrap = document.getElementById('bot-avatar-preview-wrap');
    const placeholder = document.getElementById('bot-avatar-placeholder');

    function normalizeHex(value, fallback) {
        let v = (value || '').trim();
        if (!v.startsWith('#')) v = '#' + v;
        if (/^#[0-9a-fA-F]{6}$/.test(v)) return v.toLowerCase();
        if (/^#[0-9a-fA-F]{3}$/.test(v)) {
            const c = v.slice(1);
            return ('#' + c[0] + c[0] + c[1] + c[1] + c[2] + c[2]).toLowerCase();
        }
        return fallback;
    }

    function bindColorPair(pickerId, hexId) {
        const picker = document.getElementById(pickerId);
        const hex = document.getElementById(hexId);
        if (!picker || !hex) return;

        const syncFromPicker = () => {
            hex.value = picker.value;
            updateColorPreview();
        };
        const syncFromHex = () => {
            const normalized = normalizeHex(hex.value, picker.value);
            hex.value = normalized;
            picker.value = normalized;
            updateColorPreview();
        };

        picker.addEventListener('input', syncFromPicker);
        hex.addEventListener('change', syncFromHex);
        hex.addEventListener('blur', syncFromHex);
    }

    function updateColorPreview() {
        const primary = document.getElementById('primary_color')?.value || '#005c4b';
        const secondary = document.getElementById('secondary_color')?.value || '#075e54';
        const bubble = document.getElementById('preview-bubble');
        const header = document.getElementById('preview-header');
        if (bubble) bubble.style.background = primary;
        if (header) header.style.background = `linear-gradient(135deg, ${secondary}, ${primary})`;
    }

    bindColorPair('primary_color', 'primary_color_hex');
    bindColorPair('secondary_color', 'secondary_color_hex');
    bindColorPair('landing_accent_color', 'landing_accent_color_hex');

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            const file = this.files?.[0];
            if (!file) {
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                preview.src = event.target.result;
                previewWrap.classList.remove('hidden');
                placeholder.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        });
    }
});
</script>
@endpush
