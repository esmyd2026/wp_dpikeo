@extends('admin.layouts.app')

@section('header', 'WhatsApp — ' . $company->name)

@section('content')
<div class="bg-white shadow-sm rounded-lg overflow-hidden">
    <div class="p-6">
        <a href="{{ route('admin.empresas.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-4">
            <i class="fas fa-arrow-left mr-1"></i> Empresas
        </a>

        <h2 class="text-xl font-semibold text-gray-900 mb-1">{{ $company->name }}</h2>
        <p class="text-sm text-gray-600 mb-6">Números de WhatsApp conectados a esta empresa.</p>

        @if($accounts->isEmpty())
            <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm mb-6">
                <i class="fas fa-triangle-exclamation mr-1"></i> Todavía no hay ningún número conectado.
            </div>
        @else
            <div class="space-y-3 mb-6">
                @foreach($accounts as $account)
                    @php
                        $badge = match($account->status) {
                            'connected' => ['Conectado', 'bg-green-100 text-green-700'],
                            'pending' => ['Pendiente', 'bg-gray-100 text-gray-600'],
                            'disconnected' => ['Desconectado', 'bg-gray-100 text-gray-600'],
                            'requires_action' => ['Requiere acción', 'bg-amber-100 text-amber-700'],
                            'error' => ['Error', 'bg-red-100 text-red-700'],
                            default => [ucfirst($account->status ?: 'Desconocido'), 'bg-gray-100 text-gray-600'],
                        };
                        $connectionTypeLabel = match($account->connection_type) {
                            'demo' => 'Demo',
                            'manual' => 'Manual',
                            'embedded_signup' => 'Embedded Signup',
                            'whatsapp_business_app_coexistence' => 'Coexistencia (WhatsApp Business App)',
                            default => null,
                        };
                    @endphp
                    <div class="border border-gray-200 rounded-lg p-4 flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs px-2 py-1 rounded-full {{ $badge[1] }}">{{ $badge[0] }}</span>
                                @if($connectionTypeLabel)
                                    <span class="text-xs px-2 py-1 rounded-full bg-blue-50 text-blue-700">{{ $connectionTypeLabel }}</span>
                                @endif
                                <span class="font-medium text-gray-900">{{ $account->display_name ?: $account->business_name }}</span>
                            </div>
                            <p class="text-sm text-gray-600">{{ $account->phone_number ?: 'Sin número' }}</p>
                            <p class="text-xs text-gray-400 font-mono mt-1">
                                Phone Number ID: {{ $account->phone_number_id ?: '—' }} ·
                                WABA ID: {{ $account->whatsapp_business_id ?: '—' }}
                            </p>
                            @if($account->connected_at)
                                <p class="text-xs text-gray-400 mt-1">Conectado el {{ $account->connected_at->format('d/m/Y H:i') }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if($embeddedSignupReady)
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" id="btn-conectar-whatsapp"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                    <i class="fab fa-whatsapp mr-2"></i>Conectar número nuevo
                </button>
                <button type="button" id="btn-conectar-whatsapp-coexistencia"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fab fa-whatsapp mr-2"></i>Ya uso WhatsApp Business
                </button>
            </div>
            <p class="mt-2 text-xs text-gray-500 max-w-2xl">
                "Ya uso WhatsApp Business" es para un número que hoy está activo en la app de WhatsApp Business
                (celular). Es una prueba: si Meta no tiene habilitada la coexistencia para esta configuración,
                puede devolver el mismo error de "migra o desconecta" que el botón estándar — en ningún caso se
                desconecta ni se migra el número automáticamente.
            </p>
            <span id="conectar-whatsapp-status" class="mt-2 block text-sm text-gray-500"></span>
        @else
            <button type="button" disabled
                title="Falta configurar META_APP_ID y META_EMBEDDED_SIGNUP_CONFIG_ID en el servidor"
                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed">
                <i class="fab fa-whatsapp mr-2"></i>Conectar WhatsApp (falta configurar Meta)
            </button>
        @endif

        <div class="mt-6 bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-medium text-gray-900 mb-1">📱 Conexión manual</h3>
            <p class="text-sm text-gray-600 mb-4">
                Mientras se activa la conexión automática, podés cargar acá las credenciales de WhatsApp Cloud API
                a mano. Las obtenés en
                <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline">developers.facebook.com/apps</a>
                → tu app → <strong>WhatsApp → Configuración de la API</strong>.
            </p>

            @php($account = $accounts->first())

            <form action="{{ route('admin.empresas.whatsapp.update', $company) }}" method="POST" class="bg-white border border-gray-200 rounded-lg p-4 space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="phone_number" class="block text-sm font-medium text-gray-700">Número de WhatsApp</label>
                    <input type="text" id="phone_number" name="phone_number"
                        value="{{ old('phone_number', $account->phone_number ?? '') }}"
                        placeholder="Ej: 593994281769" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <p class="mt-1 text-xs text-gray-500">
                        El número real que los clientes usan para escribirle al bot, con código de país y sin
                        signos ni espacios (ej. 593994281769).
                    </p>
                    @error('phone_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="phone_number_id" class="block text-sm font-medium text-gray-700">Phone Number ID</label>
                    <input type="text" id="phone_number_id" name="phone_number_id"
                        value="{{ old('phone_number_id', $account->phone_number_id ?? '') }}"
                        placeholder="Ej: 123456789012345" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm font-mono">
                    <p class="mt-1 text-xs text-gray-500">
                        Identificador numérico que Meta le asigna al número (no es el número de teléfono). Aparece
                        en <strong>WhatsApp → Configuración de la API</strong> como «Identificador de número de
                        teléfono» / «Phone number ID».
                    </p>
                    @error('phone_number_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="whatsapp_business_id" class="block text-sm font-medium text-gray-700">
                        WhatsApp Business Account ID <span class="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <input type="text" id="whatsapp_business_id" name="whatsapp_business_id"
                        value="{{ old('whatsapp_business_id', $account->whatsapp_business_id ?? '') }}"
                        placeholder="Ej: 987654321098765"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm font-mono">
                    <p class="mt-1 text-xs text-gray-500">
                        Identificador de la cuenta de WhatsApp Business (WABA) en Meta Business Manager.
                    </p>
                </div>

                <div>
                    <label for="access_token" class="block text-sm font-medium text-gray-700">Token de acceso permanente</label>
                    <input type="password" id="access_token" name="access_token" autocomplete="new-password"
                        placeholder="{{ $account && $account->access_token ? '•••••••••••••••••••• (ya configurado, déjalo vacío para no cambiarlo)' : 'Aún no configurado' }}"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm font-mono">
                    <p class="mt-1 text-xs text-gray-500">
                        Por seguridad, el token guardado nunca se muestra acá. Pegá uno nuevo solo si querés
                        reemplazarlo; si lo dejás en blanco, se conserva el que ya está guardado. Se guarda cifrado.
                        Generá un token <strong>permanente</strong> (no el temporal de 24 h) desde
                        <strong>Meta Business Manager → Usuarios del sistema</strong>, con permiso
                        <code>whatsapp_business_messaging</code>.
                    </p>
                    @error('access_token')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="rounded-md bg-blue-50 border border-blue-100 px-3 py-2 text-xs text-blue-800">
                    <i class="fas fa-circle-info mr-1"></i>
                    El <strong>token de verificación del webhook</strong> y el <strong>App Secret</strong> son
                    de la app de Meta de Siglo Tecnológico (no de esta empresa) y se configuran en el
                    <code>.env</code> del servidor.
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Guardar credenciales de WhatsApp
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($embeddedSignupReady)
    @push('scripts')
    <script>
        window.fbAsyncInit = function () {
            FB.init({
                appId: @json($metaAppId),
                autoLogAppEvents: true,
                xfbml: true,
                version: @json($metaGraphApiVersion),
            });
        };
    </script>
    <script async defer crossorigin="anonymous" src="https://connect.facebook.net/es_LA/sdk.js"></script>
    <script>
        (function () {
            const btnStandard = document.getElementById('btn-conectar-whatsapp');
            const btnCoexistence = document.getElementById('btn-conectar-whatsapp-coexistencia');
            const statusEl = document.getElementById('conectar-whatsapp-status');
            if (!btnStandard && !btnCoexistence) return;

            const embeddedSignupUrl = @json(route('admin.empresas.whatsapp.embedded-signup', $company));
            const configId = @json($metaConfigId);
            let sessionInfo = null;
            // 'standard' | 'coexistence' -- qué botón disparó el FB.login en curso.
            let activeMode = 'standard';

            // Meta manda waba_id/phone_number_id por postMessage durante el
            // popup; FB.login() por su parte devuelve el "code" al terminar.
            window.addEventListener('message', function (event) {
                if (!event.origin || !event.origin.endsWith('facebook.com')) return;
                try {
                    const data = JSON.parse(event.data);
                    if (data.type !== 'WA_EMBEDDED_SIGNUP') return;

                    if (data.event === 'FINISH' || data.event === 'FINISH_ONLY_WABA') {
                        sessionInfo = data.data; // { phone_number_id, waba_id, business_id }
                    } else if (data.event === 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING') {
                        // Prueba de coexistencia: no asumimos que este payload
                        // tiene la misma forma que el de FINISH estándar. Se
                        // loguean solo los nombres de campo (nunca valores
                        // completos ni tokens) para poder confirmarlo.
                        const fields = data.data ? Object.keys(data.data) : [];
                        console.info('[EmbeddedSignup][coexistence] FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING recibido. Campos:', fields);
                        sessionInfo = {
                            waba_id: data.data?.waba_id ?? null,
                            phone_number_id: data.data?.phone_number_id ?? null,
                        };
                        if (!sessionInfo.waba_id || !sessionInfo.phone_number_id) {
                            statusEl.textContent = `Coexistencia: el payload no trajo waba_id/phone_number_id con esos nombres. Campos recibidos: ${fields.join(', ') || 'ninguno'} (ver consola).`;
                        }
                    } else if (data.event === 'CANCEL') {
                        statusEl.textContent = `Cancelado en el paso: ${data.data?.current_step || 'desconocido'}.`;
                    } else if (data.event === 'ERROR') {
                        statusEl.textContent = `Meta reportó un error: ${data.data?.error_message || 'desconocido'}.`;
                    }
                } catch (e) {
                    // Mensajes que no son JSON (otros widgets de Facebook) se ignoran.
                }
            });

            function fbLoginCallback(response) {
                if (!response.authResponse || !response.authResponse.code) {
                    statusEl.textContent = 'Se canceló la conexión con Meta.';
                    return;
                }

                if (!sessionInfo || !sessionInfo.waba_id || !sessionInfo.phone_number_id) {
                    if (activeMode !== 'coexistence') {
                        statusEl.textContent = 'Meta no envió los datos del número. Intentá de nuevo.';
                    }
                    return;
                }

                statusEl.textContent = 'Conectando con el servidor...';

                fetch(embeddedSignupUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        code: response.authResponse.code,
                        waba_id: sessionInfo.waba_id,
                        phone_number_id: sessionInfo.phone_number_id,
                        connection_mode: activeMode,
                    }),
                })
                    .then((r) => r.json())
                    .then((data) => {
                        statusEl.textContent = data.message || '';
                        if (data.ok) {
                            window.location.reload();
                        }
                    })
                    .catch(() => {
                        statusEl.textContent = 'Error de red al guardar la conexión.';
                    });
            }

            // El flujo estándar queda exactamente igual que antes: mismo
            // config_id, mismo response_type/override, extras = { version: 'v4' }.
            function startEmbeddedSignup(mode) {
                if (typeof FB === 'undefined') {
                    statusEl.textContent = 'El SDK de Facebook todavía no cargó, esperá un segundo e intentá de nuevo.';
                    return;
                }

                activeMode = mode;
                sessionInfo = null;
                statusEl.textContent = mode === 'coexistence' ? 'Abriendo Meta (WhatsApp Business App)...' : 'Abriendo Meta...';

                const extras = mode === 'coexistence'
                    ? { version: 'v4', featureType: 'whatsapp_business_app_onboarding' }
                    : { version: 'v4' };

                FB.login(fbLoginCallback, {
                    config_id: configId,
                    response_type: 'code',
                    override_default_response_type: true,
                    extras: extras,
                });
            }

            if (btnStandard) {
                btnStandard.addEventListener('click', function () { startEmbeddedSignup('standard'); });
            }
            if (btnCoexistence) {
                btnCoexistence.addEventListener('click', function () { startEmbeddedSignup('coexistence'); });
            }
        })();
    </script>
    @endpush
@endif
@endsection
