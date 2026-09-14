<?php

namespace App\Http\Controllers;

use App\Mail\StorefrontPasswordResetCode;
use App\Models\Company;
use App\Models\StorefrontCustomerAddress;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Services\StorefrontOrderSelfService;
use App\Services\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * "Mi cuenta" del storefront: login/registro de clientes con teléfono +
 * contraseña. La identidad es el mismo WhatsappContact que ya usa el bot --
 * un contacto puede existir sin password (por haber escrito o pedido antes
 * sin "crear cuenta"); registrarse simplemente le pone contraseña a ese
 * mismo registro en vez de crear una identidad paralela.
 */
class StorefrontAccountController extends Controller
{
    public function __construct(private StorefrontOrderSelfService $orderSelfService) {}

    public function register(Request $request, Company $company): JsonResponse
    {
        $profile = $this->profile($company);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $phone = $this->normalizePhone($validated['phone']);
        if (! $phone) {
            return response()->json(['ok' => false, 'message' => 'Ingresa un teléfono válido de 8 a 15 dígitos.'], 422);
        }

        $contact = WhatsappContact::query()->firstOrNew([
            'business_profile_id' => $profile->id,
            'phone_number' => $phone,
        ]);

        if ($contact->exists && $contact->hasAccountPassword()) {
            return response()->json(['ok' => false, 'message' => 'Ya existe una cuenta con ese teléfono. Inicia sesión.'], 422);
        }

        $isNewContact = ! $contact->exists;
        $contact->fill([
            'name' => trim($validated['name']),
            'password' => Hash::make($validated['password']),
        ]);
        if ($isNewContact) {
            $contact->status = 'active';
            $contact->bot_enabled = true;
        }
        $contact->save();

        Auth::guard('storefront_customer')->login($contact, true);

        return response()->json(['ok' => true, 'customer' => $this->customerPayload($contact)]);
    }

    public function login(Request $request, Company $company): JsonResponse
    {
        $profile = $this->profile($company);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ]);

        $phone = $this->normalizePhone($validated['phone']);
        if (! $phone) {
            return response()->json(['ok' => false, 'message' => 'Ingresa un teléfono válido de 8 a 15 dígitos.'], 422);
        }

        $throttleKey = 'storefront-login:'.$profile->id.':'.$phone;
        if (RateLimiter::tooManyAttempts($throttleKey, 6)) {
            return response()->json(['ok' => false, 'message' => 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.'], 429);
        }

        $attempted = Auth::guard('storefront_customer')->attempt([
            'business_profile_id' => $profile->id,
            'phone_number' => $phone,
            'password' => $validated['password'],
        ], true);

        if (! $attempted) {
            RateLimiter::hit($throttleKey, 60);

            // Es un resultado esperado del formulario, no un error técnico de
            // validación. El frontend lo muestra dentro del modal sin llenar
            // la consola del navegador con respuestas 422.
            return response()->json(['ok' => false, 'message' => 'Teléfono o contraseña incorrectos.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return response()->json(['ok' => true, 'customer' => $this->customerPayload(Auth::guard('storefront_customer')->user())]);
    }

    /**
     * Inicia OAuth sin exponer las credenciales de la empresa. El estado se
     * guarda en sesión para impedir que un tercero fuerce un callback ajeno.
     */
    public function redirectToGoogle(Request $request, Company $company): RedirectResponse
    {
        $this->profile($company);
        $settings = $company->storefrontSetting;
        if (! $settings?->googleLoginEnabled()) {
            return $this->googleReturn($company, 'error', 'El acceso con Google todavía no está configurado para esta tienda.');
        }

        $state = Str::random(64);
        $request->session()->put($this->googleOAuthKey($company), [
            'state_hash' => hash('sha256', $state),
            'started_at' => now()->timestamp,
        ]);

        $query = http_build_query([
            'client_id' => $settings->google_oauth_client_id,
            'redirect_uri' => route('storefront.account.google.callback', $company),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    /** Intercambia el código únicamente en backend y vincula la identidad. */
    public function handleGoogleCallback(Request $request, Company $company): RedirectResponse
    {
        $profile = $this->profile($company);
        $oauth = $request->session()->pull($this->googleOAuthKey($company));
        $state = (string) $request->query('state', '');

        if ($request->filled('error')) {
            return $this->googleReturn($company, 'error', 'Cancelaste el acceso con Google. Puedes intentarlo nuevamente.');
        }
        if (! is_array($oauth) || now()->timestamp - (int) ($oauth['started_at'] ?? 0) > 600
            || ! hash_equals((string) ($oauth['state_hash'] ?? ''), hash('sha256', $state))) {
            return $this->googleReturn($company, 'error', 'La solicitud de Google venció o no es válida. Inténtalo nuevamente.');
        }

        $settings = $company->storefrontSetting;
        if (! $settings?->googleLoginEnabled() || ! $request->filled('code')) {
            return $this->googleReturn($company, 'error', 'No pudimos completar el acceso con Google.');
        }

        try {
            $tokenResponse = Http::asForm()->timeout(12)->post('https://oauth2.googleapis.com/token', [
                'code' => $request->string('code')->toString(),
                'client_id' => $settings->google_oauth_client_id,
                'client_secret' => $settings->google_oauth_client_secret,
                'redirect_uri' => route('storefront.account.google.callback', $company),
                'grant_type' => 'authorization_code',
            ])->throw();

            $accessToken = $tokenResponse->json('access_token');
            abort_unless(is_string($accessToken) && $accessToken !== '', 502);
            $googleUser = Http::withToken($accessToken)->timeout(12)
                ->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();
        } catch (\Throwable $e) {
            Log::warning('storefront.google_oauth.failed', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            return $this->googleReturn($company, 'error', 'Google no pudo validar tu cuenta. Inténtalo nuevamente.');
        }

        $googleId = trim((string) ($googleUser['sub'] ?? ''));
        $email = Str::lower(trim((string) ($googleUser['email'] ?? '')));
        if ($googleId === '' || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || filter_var($googleUser['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN) !== true) {
            return $this->googleReturn($company, 'error', 'Google no entregó un correo verificado para esta cuenta.');
        }

        $contact = WhatsappContact::query()
            ->where('business_profile_id', $profile->id)
            ->where(function ($query) use ($googleId, $email) {
                $query->where('google_id', $googleId)
                    ->orWhere('google_email', $email)
                    ->orWhere('billing_email', $email)
                    ->orWhere('metadata->email', $email);
            })->first();

        if ($contact) {
            $this->attachGoogleIdentity($contact, $googleId, $email, (string) ($googleUser['name'] ?? ''));
            Auth::guard('storefront_customer')->login($contact, true);
            $request->session()->regenerate();

            return $this->googleReturn($company, 'success');
        }

        $request->session()->put($this->googlePendingKey($company), [
            'google_id' => $googleId,
            'email' => $email,
            'name' => trim((string) ($googleUser['name'] ?? 'Cliente')) ?: 'Cliente',
            'created_at' => now()->timestamp,
        ]);

        return $this->googleReturn($company, 'complete');
    }

    /** Google no comparte teléfonos: se solicita una sola vez al crear cuenta. */
    public function completeGoogleRegistration(Request $request, Company $company): JsonResponse
    {
        $profile = $this->profile($company);
        $pending = $request->session()->get($this->googlePendingKey($company));
        if (! is_array($pending) || now()->timestamp - (int) ($pending['created_at'] ?? 0) > 600) {
            return response()->json(['ok' => false, 'message' => 'La validación con Google venció. Inicia nuevamente.'], 422);
        }

        $validated = $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $phone = $this->normalizePhone($validated['phone']);
        if (! $phone) {
            return response()->json(['ok' => false, 'message' => 'Ingresa un teléfono válido de 8 a 15 dígitos.'], 422);
        }

        $contact = WhatsappContact::query()->firstOrNew([
            'business_profile_id' => $profile->id,
            'phone_number' => $phone,
        ]);
        $existingEmail = Str::lower((string) ($contact->google_email ?: $contact->billing_email ?: data_get($contact->metadata, 'email')));
        if ($contact->exists && $contact->hasAccountPassword() && $existingEmail !== $pending['email']) {
            return response()->json([
                'ok' => false,
                'message' => 'Ese teléfono ya tiene una cuenta. Inicia sesión con teléfono; no vincularemos Google sin verificarla.',
            ], 422);
        }
        if ($contact->exists && filled($contact->google_id) && $contact->google_id !== $pending['google_id']) {
            return response()->json(['ok' => false, 'message' => 'Ese teléfono ya está vinculado con otra cuenta de Google.'], 422);
        }

        if (! $contact->exists) {
            $contact->status = 'active';
            $contact->bot_enabled = true;
        }
        $this->attachGoogleIdentity($contact, $pending['google_id'], $pending['email'], $pending['name']);
        $request->session()->forget($this->googlePendingKey($company));
        Auth::guard('storefront_customer')->login($contact, true);
        $request->session()->regenerate();

        return response()->json(['ok' => true, 'customer' => $this->customerPayload($contact)]);
    }

    /**
     * Envía un código breve para recuperar la cuenta, por WhatsApp (default) o
     * por correo cuando el cliente indica que el mensaje de WhatsApp no le
     * llegó. Si pide el código por correo y todavía no tiene uno guardado, el
     * correo que escribe en ese momento se guarda en su perfil.
     */
    public function requestPasswordReset(Request $request, Company $company, WhatsappService $whatsapp): JsonResponse
    {
        $profile = $this->profile($company);
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'channel' => ['nullable', 'string', 'in:whatsapp,email'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
        ]);
        $channel = $validated['channel'] ?? 'whatsapp';
        $phone = $this->normalizePhone($validated['phone']);

        if (! $phone) {
            return response()->json(['ok' => false, 'message' => 'Ingresa un teléfono válido de 8 a 15 dígitos.']);
        }

        $throttleKey = 'storefront-reset-request:'.$profile->id.':'.$phone.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return response()->json(['ok' => false, 'message' => 'Espera unos minutos antes de solicitar otro código.'], 429);
        }

        $contact = WhatsappContact::query()
            ->where('business_profile_id', $profile->id)
            ->where('phone_number', $phone)
            ->first();

        // La respuesta no revela si el teléfono está registrado. Así nadie
        // puede usar este formulario para enumerar clientes de una empresa.
        if (! $contact?->hasAccountPassword()) {
            RateLimiter::hit($throttleKey, 600);

            return response()->json([
                'ok' => true,
                'message' => $channel === 'email'
                    ? 'Si ese número tiene una cuenta, recibirá un código por correo.'
                    : 'Si ese número tiene una cuenta, recibirá un código por WhatsApp.',
            ]);
        }

        $email = trim($validated['email'] ?? '') ?: ($contact->metadata['email'] ?? null);
        if ($channel === 'email' && ! $email) {
            return response()->json([
                'ok' => false,
                'needs_email' => true,
                'message' => 'Escribe tu correo para poder enviarte el código.',
            ]);
        }

        RateLimiter::hit($throttleKey, 600);

        if ($channel === 'email' && blank($contact->metadata['email'] ?? null)) {
            $metadata = $contact->metadata ?? [];
            $metadata['email'] = $email;
            $contact->metadata = $metadata;
            $contact->save();
        }

        $code = (string) random_int(100000, 999999);
        $resetKey = $this->passwordResetKey($profile->id, $phone);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $resetKey],
            ['token' => Hash::make($code), 'created_at' => now()],
        );

        if ($channel === 'email') {
            try {
                Mail::to($email)->send(new StorefrontPasswordResetCode($code, $profile->business_name ?: $company->name));
            } catch (\Throwable $e) {
                Log::error('storefront.password_reset.email_failed', [
                    'company_id' => $company->id,
                    'profile_id' => $profile->id,
                    'error' => $e->getMessage(),
                ]);
                DB::table('password_reset_tokens')->where('email', $resetKey)->delete();

                return response()->json([
                    'ok' => false,
                    'message' => 'No pudimos enviar el código por correo. Inténtalo nuevamente o escríbenos para ayudarte.',
                ], 503);
            }

            return response()->json([
                'ok' => true,
                'message' => "Te enviamos un código a {$email}. Escríbelo para crear tu nueva contraseña.",
            ]);
        }

        $whatsapp->useBusinessProfile($profile);
        $sent = $whatsapp->sendTextMessage(
            $contact,
            "🔐 Código para recuperar tu cuenta: {$code}\n\nVence en 10 minutos. No lo compartas con nadie.",
        );

        if ($sent !== true) {
            DB::table('password_reset_tokens')->where('email', $resetKey)->delete();

            return response()->json([
                'ok' => false,
                'message' => 'No pudimos enviar el código por WhatsApp. Inténtalo nuevamente o escríbenos para ayudarte.',
            ], 503);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Te enviamos un código por WhatsApp. Escríbelo para crear tu nueva contraseña.',
        ]);
    }

    /** Verifica el código y reemplaza la contraseña sin exponer el token. */
    public function resetPassword(Request $request, Company $company): JsonResponse
    {
        $profile = $this->profile($company);
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);
        $phone = $this->normalizePhone($validated['phone']);

        if (! $phone) {
            return response()->json(['ok' => false, 'message' => 'El teléfono o el código no son válidos.']);
        }

        $attemptKey = 'storefront-reset-attempt:'.$profile->id.':'.$phone.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($attemptKey, 6)) {
            return response()->json(['ok' => false, 'message' => 'Demasiados intentos. Solicita un código nuevo en unos minutos.'], 429);
        }

        $contact = WhatsappContact::query()
            ->where('business_profile_id', $profile->id)
            ->where('phone_number', $phone)
            ->first();
        $resetKey = $this->passwordResetKey($profile->id, $phone);
        $reset = DB::table('password_reset_tokens')
            ->where('email', $resetKey)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->first();

        if (! $contact?->hasAccountPassword() || ! $reset || ! Hash::check($validated['code'], $reset->token)) {
            RateLimiter::hit($attemptKey, 600);

            return response()->json(['ok' => false, 'message' => 'El código es incorrecto o ya venció.']);
        }

        $contact->password = Hash::make($validated['password']);
        $contact->save();
        DB::table('password_reset_tokens')->where('email', $resetKey)->delete();
        RateLimiter::clear($attemptKey);

        Auth::guard('storefront_customer')->login($contact, true);
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'message' => 'Contraseña actualizada correctamente.',
            'customer' => $this->customerPayload($contact),
        ]);
    }

    public function logout(Request $request, Company $company): JsonResponse
    {
        Auth::guard('storefront_customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true])
            ->header('X-CSRF-TOKEN', $request->session()->token());
    }

    public function me(Request $request, Company $company): JsonResponse
    {
        $contact = $this->authenticatedContactFor($company);

        return response()->json(['ok' => true, 'customer' => $contact ? $this->customerPayload($contact) : null]);
    }

    public function updateProfile(Request $request, Company $company): JsonResponse
    {
        $contact = $this->authenticatedContactFor($company);
        if (! $contact) {
            return response()->json(['ok' => false, 'message' => 'Debes iniciar sesión.'], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'invoice_preference' => ['required', Rule::in(['consumer', 'invoice'])],
            'billing_type' => ['nullable', 'required_if:invoice_preference,invoice', Rule::in(['cedula', 'ruc', 'pasaporte'])],
            'billing_id' => ['nullable', 'required_if:invoice_preference,invoice', 'string', 'max:20'],
            'billing_legal_name' => ['nullable', 'required_if:invoice_preference,invoice', 'string', 'max:255'],
            'billing_address' => ['nullable', 'required_if:invoice_preference,invoice', 'string', 'max:500'],
            'billing_email' => ['nullable', 'required_if:invoice_preference,invoice', 'email:rfc', 'max:255'],
        ]);

        $metadata = $contact->metadata ?? [];
        $metadata['invoice_preference'] = $validated['invoice_preference'];
        if (filled($validated['email'] ?? null)) {
            $metadata['email'] = Str::lower(trim($validated['email']));
        } else {
            unset($metadata['email']);
        }

        $contact->name = trim($validated['name']);
        $contact->metadata = $metadata;
        if ($validated['invoice_preference'] === 'invoice') {
            $contact->fill([
                'billing_type' => $validated['billing_type'],
                'billing_id' => trim($validated['billing_id']),
                'billing_legal_name' => trim($validated['billing_legal_name']),
                'billing_email' => Str::lower(trim($validated['billing_email'])),
                'address' => trim($validated['billing_address']),
            ]);
        }
        $contact->save();

        return response()->json([
            'ok' => true,
            'message' => 'Tus datos quedaron guardados para tus próximos pedidos.',
            'customer' => $this->customerPayload($contact->fresh()),
        ]);
    }

    public function storeAddress(Request $request, Company $company): JsonResponse
    {
        $contact = $this->authenticatedContactFor($company);
        if (! $contact) {
            return response()->json(['ok' => false, 'message' => 'Debes iniciar sesión.'], 401);
        }

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $addressText = trim($validated['address']);
        $address = $contact->storefrontAddresses()
            ->whereRaw('LOWER(address) = ?', [Str::lower($addressText)])
            ->first();
        $addressCount = $contact->storefrontAddresses()->count();
        if (! $address && $addressCount >= 8) {
            return response()->json(['ok' => false, 'message' => 'Puedes guardar hasta 8 direcciones. Elimina una para agregar otra.'], 422);
        }

        $makeDefault = (bool) ($validated['is_default'] ?? false) || ! $contact->storefrontAddresses()->exists();
        DB::transaction(function () use ($contact, &$address, $validated, $addressText, $makeDefault, $addressCount) {
            if ($makeDefault) {
                $contact->storefrontAddresses()->update(['is_default' => false]);
            }
            $address ??= new StorefrontCustomerAddress(['contact_id' => $contact->id]);
            $address->fill([
                'label' => trim($validated['label'] ?? '') ?: ($address->label ?: ($addressCount === 0 ? 'Casa' : 'Dirección '.($addressCount + 1))),
                'address' => $addressText,
                'reference' => trim($validated['reference'] ?? '') ?: null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'is_default' => $makeDefault || $address->is_default,
            ])->save();
        });

        return response()->json([
            'ok' => true,
            'message' => 'Dirección guardada para futuros pedidos.',
            'addresses' => $this->addressPayloads($contact),
        ]);
    }

    public function deleteAddress(Company $company, StorefrontCustomerAddress $address): JsonResponse
    {
        $contact = $this->authenticatedContactFor($company);
        abort_unless($contact && $address->contact_id === $contact->id, 404);
        $wasDefault = $address->is_default;
        $address->delete();
        if ($wasDefault) {
            $contact->storefrontAddresses()->oldest()->first()?->update(['is_default' => true]);
        }

        return response()->json(['ok' => true, 'addresses' => $this->addressPayloads($contact)]);
    }

    public function orders(Request $request, Company $company): JsonResponse
    {
        $contact = $this->authenticatedContactFor($company);
        if (! $contact) {
            return response()->json(['ok' => false, 'message' => 'Debes iniciar sesión.'], 401);
        }

        $orders = WhatsappCart::query()
            ->reportable()
            ->where('contact_id', $contact->id)
            ->with([
                'branch:id,name,address',
                'items:id,whatsapp_cart_id,name,price,quantity,line_note',
            ])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (WhatsappCart $cart) => $this->customerOrderPayload($cart));

        return response()->json(['ok' => true, 'orders' => $orders])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function updateInvoice(Request $request, Company $company, WhatsappCart $cart): JsonResponse
    {
        $contact = $this->ownedOrderContact($company, $cart);
        $validated = $request->validate([
            'requires_invoice' => ['required', 'boolean'],
            'billing_type' => ['nullable', 'required_if:requires_invoice,true', Rule::in(['cedula', 'ruc', 'pasaporte'])],
            'billing_id' => ['nullable', 'required_if:requires_invoice,true', 'string', 'max:20'],
            'billing_legal_name' => ['nullable', 'required_if:requires_invoice,true', 'string', 'max:255'],
            'billing_address' => ['nullable', 'required_if:requires_invoice,true', 'string', 'max:500'],
            'billing_email' => ['nullable', 'required_if:requires_invoice,true', 'email:rfc', 'max:255'],
        ]);
        abort_if($cart->isCancelled(), 422, 'No se puede modificar un pedido cancelado.');

        $this->orderSelfService->saveInvoicePreference($cart, $contact, $validated);

        return response()->json(['ok' => true, 'message' => 'Datos de facturación actualizados.']);
    }

    public function uploadPaymentProof(Request $request, Company $company, WhatsappCart $cart): JsonResponse
    {
        $contact = $this->ownedOrderContact($company, $cart);
        abort_unless($cart->payment_method === 'transferencia', 422, 'Este pedido no requiere comprobante de transferencia.');
        abort_if($cart->hasPaymentProof(), 422, 'Este pedido ya tiene un comprobante cargado.');
        abort_if($cart->isCancelled(), 422, 'No se puede cargar un comprobante a un pedido cancelado.');
        $validated = $request->validate(['proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:12288']]);

        try {
            $this->orderSelfService->savePaymentProof($cart, $contact, $validated['proof']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Comprobante recibido. Lo verificaremos pronto.']);
    }

    /**
     * Detalle seguro para el cliente. No incluye notas internas, datos de
     * facturación sensibles, comprobantes ni identidad de los operadores.
     */
    private function customerOrderPayload(WhatsappCart $cart): array
    {
        $metadata = is_array($cart->metadata) ? $cart->metadata : [];
        $storedInvoice = is_array($cart->invoice_data) ? $cart->invoice_data : [];
        $invoiceData = $storedInvoice ?: array_filter([
            'billing_type' => $cart->contact?->billing_type,
            'billing_id' => $cart->contact?->billing_id ?? $cart->contact?->national_id,
            'billing_legal_name' => $cart->contact?->billing_legal_name,
            'address' => $cart->contact?->address,
            'email' => $cart->contact?->billing_email,
        ], fn ($value) => filled($value));
        $serviceType = $metadata['service_type'] ?? null;
        $pickupMode = $metadata['pickup_mode'] ?? null;
        $delivery = is_array($metadata['delivery'] ?? null) ? $metadata['delivery'] : [];
        $timeline = collect($metadata['operational_timeline'] ?? [])
            ->filter(fn ($event) => is_array($event) && filled($event['to'] ?? null))
            ->map(fn ($event) => [
                'status' => $event['to'],
                'label' => $this->statusLabelFromValue((string) $event['to']),
                'at' => $event['at'] ?? null,
            ])
            ->values();

        $events = collect([[
            'status' => 'created',
            'label' => 'Pedido creado',
            'at' => $cart->created_at?->toIso8601String(),
        ]])->concat($timeline);

        if ($events->last()['status'] !== $cart->status) {
            $events->push([
                'status' => $cart->status,
                'label' => $this->statusLabel($cart),
                'at' => $metadata['status_changed_at'] ?? $cart->updated_at?->toIso8601String(),
            ]);
        }

        return [
            'id' => $cart->id,
            'order_number' => $cart->getOrderNumber(),
            'status' => $cart->status,
            'status_label' => $this->statusLabel($cart),
            'total' => (float) $cart->total,
            'branch' => $cart->branch?->name,
            'branch_address' => $cart->branch?->address,
            'created_at' => $cart->created_at?->toIso8601String(),
            'created_at_label' => $cart->created_at?->translatedFormat('d M Y, h:i A'),
            'payment_method' => match ($cart->payment_method) {
                'efectivo' => 'Efectivo',
                'transferencia' => 'Transferencia bancaria',
                'tarjeta' => 'Tarjeta',
                default => 'Por confirmar',
            },
            'payment' => [
                'method' => $cart->payment_method,
                'status' => $cart->payment_status,
                'proof_submitted' => $cart->hasPaymentProof(),
                'can_upload_proof' => $cart->payment_method === 'transferencia' && ! $cart->hasPaymentProof() && ! $cart->isCancelled(),
                'bank_instructions' => $cart->payment_method === 'transferencia'
                    ? WhatsappChatbotConfig::query()->where('business_profile_id', $cart->contact->business_profile_id)->first()?->bank_transfer_instructions
                    : null,
            ],
            'invoice' => [
                'requires_invoice' => (bool) $cart->requires_invoice,
                'status' => $cart->invoice_status,
                'data' => $invoiceData,
                // true una vez que el cliente ya contestó esto desde el
                // micrositio -- 'none' por sí solo es ambiguo (es también el
                // valor por defecto de un pedido al que nunca se le preguntó),
                // así que hay que fijarse en la marca que deja
                // StorefrontOrderSelfService::saveInvoicePreference().
                'decided' => filled($metadata['invoice_preference_source'] ?? null),
            ],
            'fulfillment' => [
                'label' => match ($serviceType) {
                    'pickup' => 'Retiro en el local',
                    'delivery' => 'Delivery',
                    'llevar' => $pickupMode === 'delivery' ? 'Delivery' : 'Para llevar',
                    'servir' => 'Para servir',
                    default => 'Por confirmar',
                },
                'address' => $delivery['address'] ?? data_get($metadata, 'delivery_location.manual_address'),
                'reference' => $metadata['delivery_reference'] ?? ($delivery['reference'] ?? null),
            ],
            'items' => $cart->items->map(fn ($item) => [
                'name' => $item->name,
                'price' => (float) $item->price,
                'quantity' => (int) $item->quantity,
                'subtotal' => round((float) $item->price * (int) $item->quantity, 2),
                'selection' => $item->line_note,
            ])->values(),
            'timeline' => $events->values(),
            'next_action' => $this->customerNextAction($cart),
        ];
    }

    private function customerNextAction(WhatsappCart $cart): array
    {
        return match ($cart->status) {
            WhatsappCart::STATUS_PENDING => ['title' => 'Pedido recibido', 'description' => 'Estamos revisando los productos y datos de tu pedido.'],
            WhatsappCart::STATUS_PAYMENT_PENDING => ['title' => 'Pago pendiente', 'description' => $cart->hasPaymentProof() ? 'Recibimos tu comprobante y estamos verificándolo.' : 'Completa o envía el comprobante de pago para continuar.'],
            WhatsappCart::STATUS_PAID => ['title' => 'Pago confirmado', 'description' => 'Tu pago fue validado y el pedido continuará a preparación.'],
            WhatsappCart::STATUS_CONFIRMED => ['title' => 'Pedido confirmado', 'description' => 'Tu pedido está listo para pasar a cocina.'],
            WhatsappCart::STATUS_PREPARING => ['title' => 'Estamos preparando tu pedido', 'description' => 'Te avisaremos cuando esté listo.'],
            WhatsappCart::STATUS_READY => ['title' => 'Tu pedido está listo', 'description' => 'Ya puedes retirarlo o esperar la coordinación de la entrega.'],
            WhatsappCart::STATUS_COMPLETED => ['title' => 'Pedido entregado', 'description' => 'El proceso de este pedido ha finalizado.'],
            WhatsappCart::STATUS_CANCELLED => ['title' => 'Pedido cancelado', 'description' => $cart->cancellationReasonLabel() ?? 'Este pedido ya no continuará.'],
            default => ['title' => 'Pedido en proceso', 'description' => 'Estamos actualizando el estado de tu pedido.'],
        };
    }

    private function statusLabelFromValue(string $status): string
    {
        $cart = new WhatsappCart(['status' => $status]);

        return $this->statusLabel($cart);
    }

    /**
     * El guard es una sesión global del navegador: si el cliente inició
     * sesión en la tienda de otra empresa y luego visita esta, no debe
     * arrastrar esa identidad ajena -- se trata como "no autenticado aquí".
     */
    private function authenticatedContactFor(Company $company): ?WhatsappContact
    {
        $contact = Auth::guard('storefront_customer')->user();
        if (! $contact) {
            return null;
        }

        $profile = $this->profile($company);
        if ($contact->business_profile_id !== $profile->id) {
            return null;
        }

        return $contact;
    }

    private function ownedOrderContact(Company $company, WhatsappCart $cart): WhatsappContact
    {
        $contact = $this->authenticatedContactFor($company);
        abort_unless($contact && $cart->contact_id === $contact->id, 404);

        return $contact;
    }

    private function customerPayload(WhatsappContact $contact): array
    {
        return [
            'name' => $contact->name,
            'phone' => $contact->phone_number,
            'email' => $contact->metadata['email'] ?? ($contact->google_email ?? $contact->billing_email),
            'invoice_preference' => data_get($contact->metadata, 'invoice_preference', filled($contact->billing_id) ? 'invoice' : 'consumer'),
            'billing' => [
                'type' => $contact->billing_type,
                'id' => $contact->billing_id,
                'legal_name' => $contact->billing_legal_name,
                'address' => $contact->address,
                'email' => $contact->billing_email,
            ],
            'addresses' => $this->addressPayloads($contact),
            // Una compra cuenta cuando el pedido ya fue entregado/completado;
            // los carritos activos, abandonados o cancelados no inflan este
            // indicador que ve el cliente en "Mi cuenta".
            'purchases_count' => WhatsappCart::query()
                ->where('contact_id', $contact->id)
                ->where('status', WhatsappCart::STATUS_COMPLETED)
                ->count(),
        ];
    }

    private function addressPayloads(WhatsappContact $contact): array
    {
        return $contact->storefrontAddresses()
            ->orderByDesc('is_default')->latest('updated_at')->get()
            ->map(fn (StorefrontCustomerAddress $address) => [
                'id' => $address->id,
                'label' => $address->label,
                'address' => $address->address,
                'reference' => $address->reference,
                'latitude' => $address->latitude,
                'longitude' => $address->longitude,
                'is_default' => $address->is_default,
            ])->all();
    }

    private function statusLabel(WhatsappCart $cart): string
    {
        return match ($cart->status) {
            WhatsappCart::STATUS_PENDING => 'Pendiente de confirmación',
            WhatsappCart::STATUS_CONFIRMED => 'Confirmado',
            WhatsappCart::STATUS_PAYMENT_PENDING => 'Esperando verificación de pago',
            WhatsappCart::STATUS_PAID => 'Pago verificado',
            WhatsappCart::STATUS_PREPARING => 'En preparación',
            WhatsappCart::STATUS_READY => 'Listo',
            WhatsappCart::STATUS_COMPLETED => 'Entregado',
            WhatsappCart::STATUS_CANCELLED => $cart->cancellationReasonLabel() ?? 'Cancelado',
            default => ucfirst($cart->status),
        };
    }

    private function normalizePhone(string $raw): ?string
    {
        return WhatsappContact::normalizePhone($raw);
    }

    private function passwordResetKey(int $profileId, string $phone): string
    {
        return "storefront:{$profileId}:{$phone}";
    }

    private function attachGoogleIdentity(WhatsappContact $contact, string $googleId, string $email, string $name): void
    {
        $metadata = $contact->metadata ?? [];
        $metadata['email'] = $email;
        $contact->google_id = $googleId;
        $contact->google_email = $email;
        $contact->metadata = $metadata;
        if (blank($contact->name)) {
            $contact->name = trim($name) ?: 'Cliente';
        }
        $contact->save();
    }

    private function googleOAuthKey(Company $company): string
    {
        return 'storefront_google_oauth.'.$company->id;
    }

    private function googlePendingKey(Company $company): string
    {
        return 'storefront_google_pending.'.$company->id;
    }

    private function googleReturn(Company $company, string $status, ?string $message = null): RedirectResponse
    {
        return redirect()->route('storefront.show', array_filter([
            'company' => $company,
            'cuenta' => 'google',
            'google' => $status,
            'google_message' => $message,
        ]));
    }

    private function profile(Company $company)
    {
        abort_unless($company->status === 'active', 404);

        return $company->whatsappAccounts()->orderByDesc('is_primary')->orderBy('id')->firstOrFail();
    }
}
