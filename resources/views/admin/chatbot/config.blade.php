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
                            placeholder="Ej: Banco Mercantil&#10;Cuenta corriente: 0105-1234-56-1234567890&#10;Titular: Mi Empresa C.A. — RIF J-12345678-9&#10;Zelle: pagos@miempresa.com"
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
                            Estos mismos números también reciben por WhatsApp los avisos automáticos de comprobante
                            recibido y preferencia de facturación.
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">🔔 Sonidos de alerta en el panel</label>
                        <p class="mt-1 text-xs text-gray-500">
                            Elige qué tan fuerte suena cada evento en la pantalla de Pedidos. "Urgente" repite el tono
                            3 veces — pensado para lo que necesita atención inmediata.
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-2">
                            @php
                                $alertSoundLabels = [
                                    'new_order' => 'Pedido nuevo',
                                    'payment_proof' => 'Comprobante enviado',
                                    'invoice_confirmed' => 'Factura / consumidor final elegido',
                                    'agent_request' => 'Pidió hablar con un asesor',
                                ];
                                $alertSoundOptions = ['suave' => 'Suave', 'normal' => 'Normal', 'fuerte' => 'Fuerte', 'urgente' => 'Urgente (repite 3x)'];
                            @endphp
                            @foreach($alertSoundLabels as $eventKey => $eventLabel)
                                <div>
                                    <label for="alert_sound_{{ $eventKey }}" class="block text-xs font-medium text-gray-700">{{ $eventLabel }}</label>
                                    <div class="mt-1 flex gap-1">
                                        <select id="alert_sound_{{ $eventKey }}" name="alert_sounds[{{ $eventKey }}]"
                                            class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            @foreach($alertSoundOptions as $value => $label)
                                                <option value="{{ $value }}" {{ old('alert_sounds.'.$eventKey, $config->alert_sounds[$eventKey] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" class="alert-sound-test-btn px-2 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50" title="Probar sonido" data-target="alert_sound_{{ $eventKey }}"><i class="fas fa-play"></i></button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
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
                    Recibe notificaciones por WhatsApp y/o Email solo para los eventos que elijas abajo -- no en cada mensaje que escriba un cliente.
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

                    @php
                        $monitoringEventOptions = [
                            'new_contact' => ['label' => 'Cliente nuevo escribe por primera vez', 'icon' => '👋'],
                            'new_order' => ['label' => 'Llega un pedido nuevo', 'icon' => '📦'],
                            'payment_confirmed' => ['label' => 'El cliente paga / manda su comprobante', 'icon' => '💳'],
                            'agent_request' => ['label' => 'Solicita hablar con un asesor o humano', 'icon' => '💬'],
                        ];
                        $selectedMonitoringEvents = $config->monitoring_events ?? array_keys($monitoringEventOptions);
                    @endphp
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ¿Cuándo quieres que te avise?
                        </label>
                        <div class="space-y-2">
                            @foreach($monitoringEventOptions as $eventKey => $meta)
                                <div class="flex items-center">
                                    <input type="checkbox" id="monitoring_event_{{ $eventKey }}" name="monitoring_events[]" value="{{ $eventKey }}"
                                        {{ in_array($eventKey, $selectedMonitoringEvents, true) ? 'checked' : '' }}
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="monitoring_event_{{ $eventKey }}" class="ml-2 block text-sm text-gray-700">
                                        {{ $meta['icon'] }} {{ $meta['label'] }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            Si no marcas ninguno, no llegará ninguna notificación aunque "Habilitar monitoreo" esté activado.
                        </p>
                    </div>
                </div>
            </div>

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
                                @if($landing['logo_path'])
                                    <img src="{{ asset('storage/'.$landing['logo_path']) }}"
                                        alt="Logo" class="w-12 h-12 rounded-lg object-contain border border-gray-200 bg-white">
                                @else
                                    <div class="w-12 h-12 rounded-lg border border-gray-200 bg-gray-50 flex items-center justify-center text-gray-300">
                                        <i class="fas fa-image"></i>
                                    </div>
                                @endif
                                <div class="flex-1 space-y-1">
                                    <input type="file" id="landing_logo_image" name="landing_logo_image" accept="image/jpeg,image/png,image/jpg,image/webp"
                                        class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                    @if($landing['logo_path'])
                                        <label class="inline-flex items-center text-xs text-gray-600">
                                            <input type="checkbox" name="remove_landing_logo" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-1">
                                            Quitar logo
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

            <section class="mt-8 rounded-xl border border-emerald-200 bg-emerald-50/40 p-4 sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Proceso de pago</p>
                        <h3 class="mt-1 text-lg font-semibold text-gray-900">Plantillas de cobro y comprobantes</h3>
                        <p class="mt-1 max-w-3xl text-sm text-gray-600">
                            Edita lo que recibe el cliente sin cambiar importes, estados ni botones. Las variables se reemplazan
                            automáticamente con los datos reales del pedido y cada empresa conserva sus propios textos.
                        </p>
                    </div>
                    <span class="inline-flex w-fit items-center rounded-full bg-white px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200">
                        {{ count($paymentTemplateDefinitions) }} mensajes editables
                    </span>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 xl:grid-cols-2">
                    @php $currentTemplateGroup = null; @endphp
                    @foreach($paymentTemplateDefinitions as $templateKey => $definition)
                        @if(($definition['group'] ?? null) !== $currentTemplateGroup)
                            @php $currentTemplateGroup = $definition['group'] ?? null; @endphp
                            <h4 class="col-span-full mt-2 text-xs font-semibold uppercase tracking-wider text-emerald-700 first:mt-0">
                                {{ $currentTemplateGroup }}
                            </h4>
                        @endif
                        <details class="group rounded-lg border border-gray-200 bg-white shadow-sm" {{ $loop->first || $errors->has('payment_templates.'.$templateKey) ? 'open' : '' }}>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900">{{ $definition['label'] }}</span>
                                    <span class="mt-0.5 block text-xs font-normal text-gray-500">{{ $definition['description'] }}</span>
                                </span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform group-open:rotate-180"></i>
                            </summary>
                            <div class="border-t border-gray-100 px-4 pb-4 pt-3">
                                @if(!empty($config->metadata['payment_templates'][$templateKey] ?? null))
                                    <p class="mb-2 rounded-md bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 ring-1 ring-amber-200">
                                        ✏️ Este mensaje tiene un texto propio guardado. Aunque el sistema mejore el texto de fábrica más adelante, este seguirá mostrando lo de aquí abajo hasta que lo vacíes y guardes.
                                    </p>
                                @endif
                                <textarea id="payment_template_{{ $templateKey }}" name="payment_templates[{{ $templateKey }}]" rows="7" maxlength="3000"
                                    class="block w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-emerald-500">{{ old('payment_templates.'.$templateKey, $config->metadata['payment_templates'][$templateKey] ?? $definition['body']) }}</textarea>

                                @if($definition['variables'] !== [])
                                    <div class="mt-3">
                                        <p class="text-xs font-medium text-gray-600">Variables disponibles — toca una para insertarla, o el "?" para saber qué es:</p>
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach($definition['variables'] as $variable => $description)
                                                <span class="inline-flex overflow-hidden rounded-md ring-1 ring-gray-200">
                                                    <button type="button" data-template-target="payment_template_{{ $templateKey }}" data-template-variable="&#123;&#123;{{ $variable }}&#125;&#125;"
                                                        title="{{ $description }}"
                                                        class="bg-gray-100 px-2 py-1 font-mono text-xs text-gray-700 hover:bg-emerald-50 hover:text-emerald-800">
                                                        &#123;&#123;{{ $variable }}&#125;&#125;
                                                    </button>
                                                    <button type="button" data-template-info-for="payment_template_info_{{ $templateKey }}" data-template-info-text="{{ $description }}"
                                                        aria-label="Qué es {{ $variable }}"
                                                        class="border-l border-gray-200 bg-gray-50 px-1.5 text-xs font-bold text-gray-500 hover:bg-emerald-50 hover:text-emerald-800">
                                                        ?
                                                    </button>
                                                </span>
                                            @endforeach
                                        </div>
                                        <p id="payment_template_info_{{ $templateKey }}" class="mt-2 hidden rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-800 ring-1 ring-emerald-100"></p>
                                    </div>
                                @endif

                                @error('payment_templates.'.$templateKey)
                                    <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-2 text-xs text-gray-500">Si lo dejas vacío se recuperará el texto predeterminado al guardar.</p>
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>

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
                La conexión del número de WhatsApp (Phone Number ID, WABA ID y token) ahora se administra por
                empresa, no acá.
            </p>
            <a href="{{ route('admin.empresas.index') }}"
                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-building mr-2"></i>Ir a Empresas → WhatsApp
            </a>
        </div>

        <div class="mt-6 bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-medium text-gray-900 mb-1">✉️ Mensajes automáticos al cliente</h3>
            <p class="text-sm text-gray-600 mb-4">
                Textos que el sistema le envía al cliente por WhatsApp cuando pasan cosas en la plataforma (cambio de estado del pedido, costo de envío confirmado, etc.). No es el flujo conversacional del bot — eso se edita desde <a href="{{ route('admin.marketing-flow.edit') }}" class="text-blue-600 underline">Flujo de marketing</a>.
            </p>

            @php
                // Este sistema (App\Models\MessageTemplate) no guarda una
                // descripción por variable como sí hace PaymentMessageTemplates
                // -- se documenta acá, a mano, una sola vez para las 4 plantillas
                // que existen hoy.
                $messageTemplatePlaceholderInfo = [
                    'order_number' => 'Número del pedido, ej. "ORD-010". Se arma solo con el pedido.',
                    'status_label' => 'Nombre del nuevo estado del pedido (ej. "En preparación", "Listo"). Se arma solo según a qué estado cambió.',
                    'address_line' => 'Línea con la dirección de entrega, si el pedido es delivery (vacía si no aplica). Se arma sola con el pedido.',
                    'recipient_line' => 'Línea con el nombre de quién recibe, si el pedido es delivery (vacía si no aplica). Se arma sola con el pedido.',
                    'fee' => 'Costo de envío confirmado, con dos decimales (sin el símbolo $).',
                    'total' => 'Monto total del pedido, con dos decimales (sin el símbolo $).',
                    'pdf_url' => 'Enlace para ver/descargar el PDF de este pedido. Se genera solo, no se edita aparte.',
                    'items_list' => 'Lista de productos del pedido ("*Resumen:*" + una línea "• Nombre xCantidad" por producto). Se arma sola con el pedido.',
                    'shipping_line' => 'Línea "🚚 Envío: $X" (o "Por confirmar" si aún no se calcula) -- vacía si el pedido no es delivery. Se arma sola con el pedido.',
                    'fulfillment' => 'Bloque "🚚 Entrega": sucursal, tipo, y si aplica dirección y quién recibe. Se arma solo con el pedido -- no se edita aparte.',
                    'note_line' => 'La nota que el cliente escribió al pedido, si dejó alguna (vacía si no).',
                    'agent_note_line' => 'Mensaje que un asesor haya agregado al reenviar este ticket manualmente (vacío si nadie escribió nada).',
                ];
            @endphp
            <div class="space-y-4">
                @foreach($messageTemplates as $template)
                    <form action="{{ route('admin.chatbot.message-templates.update', $template) }}" method="POST" class="bg-white border border-gray-200 rounded-lg p-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="is_enabled" value="0">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <label for="template_body_{{ $template->id }}" class="text-sm font-semibold text-gray-800">{{ $template->name }}</label>
                            <label class="inline-flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border px-3 py-2 {{ old('is_enabled', $messageTemplateStates[$template->key] ?? true) ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-gray-200 bg-gray-50 text-gray-600' }}">
                                <input type="checkbox" name="is_enabled" value="1" {{ old('is_enabled', $messageTemplateStates[$template->key] ?? true) ? 'checked' : '' }}
                                    class="h-5 w-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                <span class="text-sm font-semibold">{{ old('is_enabled', $messageTemplateStates[$template->key] ?? true) ? 'Mensaje activo' : 'Mensaje silenciado' }}</span>
                            </label>
                        </div>
                        <textarea id="template_body_{{ $template->id }}" name="body" rows="4" required
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 font-mono text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">{{ old('body', $template->body) }}</textarea>
                        @if(!empty($template->placeholders))
                            <div class="mt-2">
                                <p class="text-xs font-medium text-gray-600">Variables disponibles — toca una para insertarla, o el "?" para saber qué es:</p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach($template->placeholders as $placeholder)
                                        <span class="inline-flex overflow-hidden rounded-md ring-1 ring-gray-200">
                                            <button type="button" data-template-target="template_body_{{ $template->id }}" data-template-variable="&#123;&#123;{{ $placeholder }}&#125;&#125;"
                                                title="{{ $messageTemplatePlaceholderInfo[$placeholder] ?? '' }}"
                                                class="bg-gray-100 px-2 py-1 font-mono text-xs text-gray-700 hover:bg-emerald-50 hover:text-emerald-800">
                                                &#123;&#123;{{ $placeholder }}&#125;&#125;
                                            </button>
                                            <button type="button" data-template-info-for="message_template_info_{{ $template->id }}" data-template-info-text="{{ $messageTemplatePlaceholderInfo[$placeholder] ?? 'Sin descripción todavía.' }}"
                                                aria-label="Qué es {{ $placeholder }}"
                                                class="border-l border-gray-200 bg-gray-50 px-1.5 text-xs font-bold text-gray-500 hover:bg-emerald-50 hover:text-emerald-800">
                                                ?
                                            </button>
                                        </span>
                                    @endforeach
                                </div>
                                <p id="message_template_info_{{ $template->id }}" class="mt-2 hidden rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-800 ring-1 ring-emerald-100"></p>
                            </div>
                        @endif
                        @if($template->key === 'order_confirmation_ticket')
                            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <label class="flex min-h-11 cursor-pointer items-center gap-3">
                                    <input type="checkbox" name="send_order_pdf_document" value="1"
                                        {{ old('send_order_pdf_document', $config->send_order_pdf_document) ? 'checked' : '' }}
                                        class="h-5 w-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                    <span class="text-sm font-semibold text-gray-800">Adjuntar también el PDF del pedido como archivo</span>
                                </label>
                                <p class="mt-1 text-xs text-gray-500">
                                    Este mensaje ya incluye un enlace para ver/descargar el PDF. Si lo desactivas, ya no se manda
                                    además el archivo como documento adjunto de WhatsApp — solo queda el enlace.
                                </p>
                            </div>
                        @endif
                        @if($template->key === 'order_status_changed')
                            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Avisar al cliente cuando pase a:</p>
                                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    @foreach($notificationStatuses as $statusKey => $statusLabel)
                                        <input type="hidden" name="status_notifications[{{ $statusKey }}]" value="0">
                                        <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-lg border bg-white px-3 py-2 text-xs font-medium text-gray-700">
                                            <input type="checkbox" name="status_notifications[{{ $statusKey }}]" value="1" {{ old('status_notifications.'.$statusKey, $statusNotificationStates[$statusKey] ?? true) ? 'checked' : '' }}
                                                class="h-5 w-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                            {{ $statusLabel }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <div class="mt-2 flex justify-end">
                            <button type="submit" class="inline-flex min-h-11 items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
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
    document.querySelectorAll('.alert-sound-test-btn').forEach((button) => {
        button.addEventListener('click', () => {
            const select = document.getElementById(button.dataset.target);
            if (!select || !window.WaOrderAlerts) return;
            window.WaOrderAlerts.playPreset(select.value);
        });
    });

    document.querySelectorAll('[data-template-variable]').forEach((button) => {
        button.addEventListener('click', () => {
            const textarea = document.getElementById(button.dataset.templateTarget);
            if (!textarea) return;

            const start = textarea.selectionStart ?? textarea.value.length;
            const end = textarea.selectionEnd ?? start;
            textarea.setRangeText(button.dataset.templateVariable, start, end, 'end');
            textarea.focus();
        });
    });

    document.querySelectorAll('[data-template-info-for]').forEach((button) => {
        button.addEventListener('click', () => {
            const box = document.getElementById(button.dataset.templateInfoFor);
            if (!box) return;
            box.textContent = button.dataset.templateInfoText;
            box.classList.remove('hidden');
        });
    });

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
