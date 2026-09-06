@extends('admin.layouts.app')

@section('header', 'WhatsApp — ' . $company->name)

@section('content')
<div class="bg-white shadow-sm rounded-lg overflow-hidden">
    <div class="p-6">
        <a href="{{ route('admin.empresas.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-4">
            <i class="fas fa-arrow-left mr-1"></i> Empresas
        </a>

        <div class="flex items-center gap-2 mb-1">
            <h2 class="text-xl font-semibold text-gray-900">{{ $company->name }}</h2>
            <button type="button" onclick="var f=document.getElementById('form-editar-nombre-empresa'); f.style.display = f.style.display === 'flex' ? 'none' : 'flex';"
                class="text-gray-400 hover:text-gray-600" title="Editar nombre de la empresa">
                <i class="fas fa-pen text-sm"></i>
            </button>
        </div>
        <form id="form-editar-nombre-empresa" action="{{ route('admin.empresas.update', $company) }}" method="POST"
            style="display:{{ $errors->has('name') ? 'flex' : 'none' }}" class="items-center gap-2 mb-3">
            @csrf
            @method('PUT')
            <input type="text" name="name" value="{{ old('name', $company->name) }}" maxlength="120" required
                class="border border-gray-300 rounded-md px-3 py-1.5 text-sm w-64 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            <button type="submit" class="text-xs px-3 py-1.5 rounded-md bg-green-600 text-white hover:bg-green-700">Guardar</button>
        </form>
        @error('name')<p class="text-xs text-red-600 mb-2">{{ $message }}</p>@enderror
        <p class="text-sm text-gray-600 mb-6">Números de WhatsApp conectados a esta empresa.</p>

        @php
            $usableAccounts = $accounts->where('status', 'connected');
            $hasPrimary = $usableAccounts->contains('is_primary', true);
        @endphp

        @if($accounts->isEmpty())
            <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm mb-6">
                <i class="fas fa-triangle-exclamation mr-1"></i> Todavía no hay ningún número conectado.
            </div>
        @else
            @if(!$hasPrimary && $usableAccounts->count() >= 2)
                <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm mb-4">
                    <i class="fas fa-star-half-stroke mr-1"></i>
                    <strong>Requiere selección de número principal.</strong> Esta empresa tiene
                    {{ $usableAccounts->count() }} números conectados y ninguno marcado como principal —
                    el panel (dashboard, catálogo, campañas) no puede elegir uno solo. Marcá "Establecer como
                    principal" en el número que corresponda.
                </div>
            @endif
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
                            'whatsapp_business_app_coexistence' => 'WhatsApp Business + Cloud API',
                            default => 'Desconocido',
                        };
                        $isConnected = $account->status === 'connected';
                    @endphp
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    <span class="text-xs px-2 py-1 rounded-full {{ $badge[1] }}">{{ $badge[0] }}</span>
                                    <span class="text-xs px-2 py-1 rounded-full bg-blue-50 text-blue-700">{{ $connectionTypeLabel }}</span>
                                    @if($account->is_primary)
                                        <span class="text-xs px-2 py-1 rounded-full bg-amber-100 text-amber-800 font-medium">
                                            ⭐ Número principal
                                        </span>
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
                                @if($account->status === 'disconnected' && $account->disconnected_at)
                                    <p class="text-xs text-gray-400 mt-1">Desconectado el {{ $account->disconnected_at->format('d/m/Y H:i') }}</p>
                                @endif
                                @if($account->last_verified_at)
                                    <p class="text-xs text-gray-400 mt-1">
                                        Última verificación: {{ $account->last_verified_at->format('d/m/Y H:i') }}
                                        ({{ $account->last_verification_status === 'ok' ? 'operativa' : 'con problemas' }})
                                    </p>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <button type="button"
                                    class="js-ver-detalles text-xs px-3 py-1.5 border border-gray-300 rounded-md hover:bg-gray-50 whitespace-nowrap"
                                    data-details-url="{{ route('admin.empresas.whatsapp.profile.details', [$company, $account]) }}"
                                    data-test-url="{{ route('admin.empresas.whatsapp.profile.test', [$company, $account]) }}">
                                    Ver detalles
                                </button>
                                <button type="button"
                                    class="js-probar-conexion text-xs px-3 py-1.5 border border-gray-300 rounded-md hover:bg-gray-50 whitespace-nowrap"
                                    data-test-url="{{ route('admin.empresas.whatsapp.profile.test', [$company, $account]) }}">
                                    Probar conexión
                                </button>
                                <div class="relative js-menu-wrap">
                                    <button type="button" class="js-menu-toggle w-8 h-8 inline-flex items-center justify-center rounded-md hover:bg-gray-100 text-gray-500">
                                        <i class="fas fa-ellipsis-vertical"></i>
                                    </button>
                                    <div class="js-menu-dropdown hidden absolute right-0 mt-1 w-48 bg-white border border-gray-200 rounded-md shadow-lg z-20 py-1 text-sm">
                                        @if($account->connection_type === 'manual')
                                            <a href="#form-conexion-manual" class="block px-3 py-2 hover:bg-gray-50 text-gray-700">
                                                <i class="fas fa-sliders mr-1 text-gray-400"></i> Configurar
                                            </a>
                                        @endif
                                        @if($isConnected && !$account->is_primary)
                                            <form action="{{ route('admin.empresas.whatsapp.profile.set-primary', [$company, $account]) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="block w-full text-left px-3 py-2 hover:bg-amber-50 text-amber-800">
                                                    <i class="fas fa-star mr-1"></i> Establecer como principal
                                                </button>
                                            </form>
                                        @endif
                                        @if($isConnected)
                                            <button type="button"
                                                class="js-desconectar block w-full text-left px-3 py-2 hover:bg-red-50 text-red-600"
                                                data-disconnect-url="{{ route('admin.empresas.whatsapp.profile.disconnect', [$company, $account]) }}"
                                                data-profile-name="{{ $account->display_name ?: $account->business_name }}"
                                                data-profile-phone="{{ $account->phone_number ?: 'sin número' }}"
                                                data-profile-type="{{ $connectionTypeLabel }}">
                                                <i class="fas fa-plug-circle-xmark mr-1"></i> Desconectar
                                            </button>
                                        @else
                                            <button type="button"
                                                class="js-eliminar block w-full text-left px-3 py-2 hover:bg-red-50 text-red-600"
                                                data-delete-url="{{ route('admin.empresas.whatsapp.profile.destroy', [$company, $account]) }}"
                                                data-profile-name="{{ $account->display_name ?: $account->business_name }}"
                                                data-profile-phone="{{ $account->phone_number ?: 'sin número' }}">
                                                <i class="fas fa-trash mr-1"></i> Eliminar
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
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

        <details id="form-conexion-manual" class="mt-6 bg-gray-50 rounded-lg group">
            <summary class="cursor-pointer select-none p-4 text-sm font-medium text-gray-600 hover:text-gray-800 list-none flex items-center gap-2">
                <i class="fas fa-chevron-right text-xs transition-transform group-open:rotate-90"></i>
                Conexión manual (avanzado)
            </summary>
            <div class="p-6 pt-0">
            <p class="text-sm text-gray-600 mb-4">
                Para cuando Embedded Signup no aplica (por ejemplo, mientras se resuelve la coexistencia con la
                app de WhatsApp Business): cargá las credenciales de WhatsApp Cloud API a mano. Las obtenés en
                <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline">developers.facebook.com/apps</a>
                → tu app → <strong>WhatsApp → Configuración de la API</strong>.
            </p>

            @php($account = $accounts->firstWhere('connection_type', 'manual') ?? $accounts->first())

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
        </details>
    </div>
</div>

{{-- Modal compartido: "Ver detalles" / resultado de "Probar conexión". Se
     popula por JS con fetch() a las rutas de cada tarjeta -- nunca incluye
     access_token en ningún campo. --}}
<div id="modal-detalle-conexion" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">Detalle de la conexión</h3>
            <button type="button" class="js-cerrar-modal-detalle text-gray-400 hover:text-gray-600">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <div class="px-5 py-4 text-sm text-gray-700 space-y-2 max-h-[70vh] overflow-y-auto" id="modal-detalle-body">
            <p class="text-gray-400">Cargando...</p>
        </div>
        <div class="px-5 py-3 border-t border-gray-200 flex justify-end gap-2">
            <button type="button" class="js-probar-desde-modal text-xs px-3 py-1.5 border border-gray-300 rounded-md hover:bg-gray-50">
                Probar conexión
            </button>
            <button type="button" class="js-cerrar-modal-detalle text-xs px-3 py-1.5 rounded-md bg-gray-100 hover:bg-gray-200">
                Cerrar
            </button>
        </div>
    </div>
</div>

{{-- Modal compartido: confirmación de desconexión LOCAL. No llama a Meta, no
     borra la fila -- solo cambia status/disconnected_at (ver
     CompanyWhatsappController::disconnect). --}}
<div id="modal-desconectar" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">¿Qué deseas hacer con esta conexión?</h3>
            <button type="button" class="js-cerrar-modal-desconectar text-gray-400 hover:text-gray-600">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <div class="px-5 py-4 text-sm text-gray-700 space-y-3">
            <p class="text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2 text-xs">
                Esta acción dejará de utilizar este número dentro de esta empresa. <strong>No elimina
                automáticamente el número de Meta ni de la app de WhatsApp Business.</strong> El registro se
                conserva (no se borra) por si necesitás volver a activarlo o consultarlo más adelante.
            </p>
            <dl class="text-xs text-gray-600 grid grid-cols-3 gap-x-2 gap-y-1">
                <dt class="font-medium text-gray-500">Empresa</dt>
                <dd class="col-span-2">{{ $company->name }}</dd>
                <dt class="font-medium text-gray-500">Número</dt>
                <dd class="col-span-2" id="modal-desconectar-numero">—</dd>
                <dt class="font-medium text-gray-500">Tipo</dt>
                <dd class="col-span-2" id="modal-desconectar-tipo">—</dd>
            </dl>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                    Escribí <span class="font-mono font-semibold">DESCONECTAR</span> para confirmar
                </label>
                <input type="text" id="modal-desconectar-confirm-input" autocomplete="off"
                    class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:outline-none focus:ring-red-500 focus:border-red-500">
            </div>
            <p id="modal-desconectar-status" class="text-xs text-gray-500"></p>
        </div>
        <div class="px-5 py-3 border-t border-gray-200 flex justify-end gap-2">
            <button type="button" class="js-cerrar-modal-desconectar text-xs px-3 py-1.5 rounded-md bg-gray-100 hover:bg-gray-200">
                Cancelar
            </button>
            <button type="button" id="modal-desconectar-submit" disabled
                class="text-xs px-3 py-1.5 rounded-md bg-red-300 text-white cursor-not-allowed">
                Desconectar
            </button>
        </div>
    </div>
</div>

{{-- Modal compartido: borrado real de una conexión ya desconectada (ver
     CompanyWhatsappController::destroy). Bloqueado en el backend si sigue
     conectada o si tiene contactos/pedidos asociados. --}}
<div id="modal-eliminar" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">Eliminar esta conexión</h3>
            <button type="button" class="js-cerrar-modal-eliminar text-gray-400 hover:text-gray-600">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <div class="px-5 py-4 text-sm text-gray-700 space-y-3">
            <p class="text-red-700 bg-red-50 border border-red-200 rounded-md px-3 py-2 text-xs">
                Esto borra el registro para siempre (catálogo y configuración propios incluidos). No se puede
                deshacer. Se bloquea si el número tiene contactos o pedidos asociados.
            </p>
            <dl class="text-xs text-gray-600 grid grid-cols-3 gap-x-2 gap-y-1">
                <dt class="font-medium text-gray-500">Empresa</dt>
                <dd class="col-span-2">{{ $company->name }}</dd>
                <dt class="font-medium text-gray-500">Número</dt>
                <dd class="col-span-2" id="modal-eliminar-numero">—</dd>
            </dl>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                    Escribí <span class="font-mono font-semibold">ELIMINAR</span> para confirmar
                </label>
                <input type="text" id="modal-eliminar-confirm-input" autocomplete="off"
                    class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:outline-none focus:ring-red-500 focus:border-red-500">
            </div>
            <p id="modal-eliminar-status" class="text-xs text-gray-500"></p>
        </div>
        <div class="px-5 py-3 border-t border-gray-200 flex justify-end gap-2">
            <button type="button" class="js-cerrar-modal-eliminar text-xs px-3 py-1.5 rounded-md bg-gray-100 hover:bg-gray-200">
                Cancelar
            </button>
            <button type="button" id="modal-eliminar-submit" disabled
                class="text-xs px-3 py-1.5 rounded-md bg-red-300 text-white cursor-not-allowed">
                Eliminar
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        function postJson(url) {
            return fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
            }).then((r) => r.json());
        }

        // El enlace "Configurar" de una conexión manual apunta a
        // #form-conexion-manual, que vive dentro de un <details> colapsado
        // por defecto -- sin esto, el ancla no abre el bloque en todos los
        // navegadores.
        document.querySelectorAll('a[href="#form-conexion-manual"]').forEach((link) => {
            link.addEventListener('click', () => {
                document.getElementById('form-conexion-manual')?.setAttribute('open', '');
            });
        });

        // ---- Menús "⋮" por tarjeta ----
        document.querySelectorAll('.js-menu-toggle').forEach((btn) => {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                const dropdown = btn.parentElement.querySelector('.js-menu-dropdown');
                document.querySelectorAll('.js-menu-dropdown').forEach((d) => {
                    if (d !== dropdown) d.classList.add('hidden');
                });
                dropdown.classList.toggle('hidden');
            });
        });
        document.addEventListener('click', function () {
            document.querySelectorAll('.js-menu-dropdown').forEach((d) => d.classList.add('hidden'));
        });

        // ---- Modal "Ver detalles" / "Probar conexión" ----
        const detalleModal = document.getElementById('modal-detalle-conexion');
        const detalleBody = document.getElementById('modal-detalle-body');
        let detalleTestUrl = null;
        let detalleDetailsUrl = null;

        function fieldLabel(status) {
            return {
                connected: 'Conectado', pending: 'Pendiente', disconnected: 'Desconectado',
                error: 'Error', requires_action: 'Requiere acción',
            }[status] || (status || '—');
        }

        function renderDetalle(data) {
            const rows = [
                ['Nombre visible', data.display_name || data.business_name || '—'],
                ['Número', data.phone_number || '—'],
                ['Phone Number ID', data.phone_number_id || '—'],
                ['WABA ID', data.whatsapp_business_id || '—'],
                ['Empresa', data.company?.name || '—'],
                ['Estado local', fieldLabel(data.status)],
                ['Número principal', data.is_primary ? '⭐ Sí' : 'No'],
                ['Conectado el', data.connected_at ? new Date(data.connected_at).toLocaleString() : '—'],
            ];
            if (data.status === 'disconnected' && data.disconnected_at) {
                rows.push(['Desconectado el', new Date(data.disconnected_at).toLocaleString()]);
            }
            rows.push(['Última verificación', data.last_verified_at
                ? `${new Date(data.last_verified_at).toLocaleString()} (${data.last_verification_status === 'ok' ? 'operativa' : 'con problemas'})`
                : 'Todavía no se probó']);

            if (data.graph) {
                rows.push(['— Datos en vivo de Meta —', '']);
                rows.push(['Número verificado por Meta', data.graph.display_phone_number || '—']);
                rows.push(['Nombre verificado', data.graph.verified_name || '—']);
                rows.push(['Calidad', data.graph.quality_rating || '—']);
                rows.push(['Estado de verificación del código', data.graph.code_verification_status || '—']);
            }

            detalleBody.innerHTML = rows.map(([label, value]) => label.startsWith('—')
                ? `<p class="pt-2 mt-2 border-t border-gray-100 text-xs font-semibold text-gray-500 uppercase">${label.replace(/—/g, '').trim()}</p>`
                : `<div class="flex justify-between gap-3"><dt class="text-gray-500">${label}</dt><dd class="font-medium text-gray-900 text-right break-all">${value}</dd></div>`
            ).join('');
        }

        function openDetalleModal(detailsUrl, testUrl) {
            detalleTestUrl = testUrl;
            detalleDetailsUrl = detailsUrl;
            detalleBody.innerHTML = '<p class="text-gray-400">Cargando...</p>';
            detalleModal.classList.remove('hidden');
            detalleModal.classList.add('flex');

            fetch(detailsUrl, { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then(renderDetalle)
                .catch(() => { detalleBody.innerHTML = '<p class="text-red-600">No se pudo cargar el detalle.</p>'; });
        }

        document.querySelectorAll('.js-ver-detalles').forEach((btn) => {
            btn.addEventListener('click', () => openDetalleModal(btn.dataset.detailsUrl, btn.dataset.testUrl));
        });

        document.querySelectorAll('.js-probar-conexion').forEach((btn) => {
            btn.addEventListener('click', function () {
                const original = btn.textContent;
                btn.textContent = 'Probando...';
                btn.disabled = true;
                postJson(btn.dataset.testUrl)
                    .then((data) => {
                        alert(data.ok ? `✅ ${data.message}` : `⚠️ ${data.message}`);
                        window.location.reload();
                    })
                    .catch(() => { alert('Error de red al probar la conexión.'); })
                    .finally(() => { btn.textContent = original; btn.disabled = false; });
            });
        });

        document.querySelectorAll('.js-cerrar-modal-detalle').forEach((btn) => {
            btn.addEventListener('click', () => {
                detalleModal.classList.add('hidden');
                detalleModal.classList.remove('flex');
            });
        });

        document.querySelector('.js-probar-desde-modal')?.addEventListener('click', function () {
            if (!detalleTestUrl || !detalleDetailsUrl) return;
            const el = this;
            const original = el.textContent;
            el.textContent = 'Probando...';
            el.disabled = true;
            postJson(detalleTestUrl)
                .then((testResult) => fetch(detalleDetailsUrl, { headers: { Accept: 'application/json' } })
                    .then((r) => r.json())
                    .then((full) => renderDetalle({ ...full, graph: testResult.graph, _testMessage: testResult.message })))
                .catch(() => { detalleBody.innerHTML = '<p class="text-red-600">No se pudo probar la conexión.</p>'; })
                .finally(() => { el.textContent = original; el.disabled = false; });
        });

        // ---- Modal "Desconectar" ----
        const desconectarModal = document.getElementById('modal-desconectar');
        const desconectarInput = document.getElementById('modal-desconectar-confirm-input');
        const desconectarSubmit = document.getElementById('modal-desconectar-submit');
        const desconectarStatus = document.getElementById('modal-desconectar-status');
        let desconectarUrl = null;

        document.querySelectorAll('.js-desconectar').forEach((btn) => {
            btn.addEventListener('click', function () {
                desconectarUrl = btn.dataset.disconnectUrl;
                document.getElementById('modal-desconectar-numero').textContent = btn.dataset.profilePhone || '—';
                document.getElementById('modal-desconectar-tipo').textContent = btn.dataset.profileType || '—';
                desconectarInput.value = '';
                desconectarStatus.textContent = '';
                desconectarSubmit.disabled = true;
                desconectarSubmit.className = 'text-xs px-3 py-1.5 rounded-md bg-red-300 text-white cursor-not-allowed';
                desconectarModal.classList.remove('hidden');
                desconectarModal.classList.add('flex');
            });
        });

        desconectarInput?.addEventListener('input', function () {
            const enabled = this.value.trim() === 'DESCONECTAR';
            desconectarSubmit.disabled = !enabled;
            desconectarSubmit.className = enabled
                ? 'text-xs px-3 py-1.5 rounded-md bg-red-600 text-white hover:bg-red-700'
                : 'text-xs px-3 py-1.5 rounded-md bg-red-300 text-white cursor-not-allowed';
        });

        desconectarSubmit?.addEventListener('click', function () {
            if (!desconectarUrl || desconectarInput.value.trim() !== 'DESCONECTAR') return;
            desconectarStatus.textContent = 'Desconectando...';
            fetch(desconectarUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
            }).then(() => { window.location.reload(); })
              .catch(() => { desconectarStatus.textContent = 'Error de red al desconectar.'; });
        });

        document.querySelectorAll('.js-cerrar-modal-desconectar').forEach((btn) => {
            btn.addEventListener('click', () => {
                desconectarModal.classList.add('hidden');
                desconectarModal.classList.remove('flex');
            });
        });

        // ---- Modal "Eliminar" ----
        const eliminarModal = document.getElementById('modal-eliminar');
        const eliminarInput = document.getElementById('modal-eliminar-confirm-input');
        const eliminarSubmit = document.getElementById('modal-eliminar-submit');
        const eliminarStatus = document.getElementById('modal-eliminar-status');
        let eliminarUrl = null;

        document.querySelectorAll('.js-eliminar').forEach((btn) => {
            btn.addEventListener('click', function () {
                eliminarUrl = btn.dataset.deleteUrl;
                document.getElementById('modal-eliminar-numero').textContent = btn.dataset.profilePhone || '—';
                eliminarInput.value = '';
                eliminarStatus.textContent = '';
                eliminarSubmit.disabled = true;
                eliminarSubmit.className = 'text-xs px-3 py-1.5 rounded-md bg-red-300 text-white cursor-not-allowed';
                eliminarModal.classList.remove('hidden');
                eliminarModal.classList.add('flex');
            });
        });

        eliminarInput?.addEventListener('input', function () {
            const enabled = this.value.trim() === 'ELIMINAR';
            eliminarSubmit.disabled = !enabled;
            eliminarSubmit.className = enabled
                ? 'text-xs px-3 py-1.5 rounded-md bg-red-600 text-white hover:bg-red-700'
                : 'text-xs px-3 py-1.5 rounded-md bg-red-300 text-white cursor-not-allowed';
        });

        eliminarSubmit?.addEventListener('click', function () {
            if (!eliminarUrl || eliminarInput.value.trim() !== 'ELIMINAR') return;
            eliminarStatus.textContent = 'Eliminando...';
            fetch(eliminarUrl, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken },
            }).then((r) => {
                if (r.redirected) { window.location.href = r.url; return; }
                window.location.reload();
            }).catch(() => { eliminarStatus.textContent = 'Error de red al eliminar.'; });
        });

        document.querySelectorAll('.js-cerrar-modal-eliminar').forEach((btn) => {
            btn.addEventListener('click', () => {
                eliminarModal.classList.add('hidden');
                eliminarModal.classList.remove('flex');
            });
        });
    })();
</script>
@endpush

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
