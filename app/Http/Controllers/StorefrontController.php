<?php

namespace App\Http\Controllers;

use App\Models\BusinessBranch;
use App\Models\Company;
use App\Models\CompanyStorefrontSetting;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Services\BulkOrderService;
use App\Services\BusinessHoursService;
use App\Services\OrderLifecycleService;
use App\Services\OrderPdfService;
use App\Services\StorefrontDeliveryQuoteService;
use App\Services\StorefrontOrderSelfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(
        private BulkOrderService $bulkOrders,
        private StorefrontOrderSelfService $orderSelfService,
        private StorefrontDeliveryQuoteService $deliveryQuotes,
        private BusinessHoursService $businessHours,
    ) {}

    public function show(Request $request, ?Company $company = null): View
    {
        $company = $this->resolveCompany($request, $company);
        $settings = $this->settings($company);
        abort_unless($settings->storefront_enabled, 404);
        $profile = $company->whatsappAccounts()->orderByDesc('is_primary')->orderBy('id')->first();
        if (! $profile) {
            return view('storefront.unavailable', compact('company', 'settings'));
        }

        $branches = $this->businessHours->openOrderBranches($profile->id);
        $closedMessage = $this->businessHours->closedMessage($profile->id);
        // Aparte de $branches (solo las que reciben pedidos, para el selector
        // de retiro/delivery): la sección "Sucursales" es informativa y debe
        // mostrar cualquier local activo, aunque hoy no esté recibiendo
        // pedidos por acá -- sigue siendo un local real con teléfono/horario.
        $infoBranches = BusinessBranch::query()
            ->where('business_profile_id', $profile->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')->orderBy('name')
            ->with('hours')
            ->get();
        $categories = collect($this->bulkOrders->catalogPayload(null, null, $profile->id)['categories'] ?? []);
        $paymentConfig = WhatsappChatbotConfig::query()
            ->where('business_profile_id', $profile->id)
            ->first();
        $bankTransferInstructions = $paymentConfig?->bank_transfer_instructions;
        $cardPaymentUrl = trim((string) data_get($paymentConfig?->metadata, 'card_payment_url')) ?: null;

        return view('storefront.show', compact('company', 'profile', 'settings', 'branches', 'closedMessage', 'infoBranches', 'categories', 'bankTransferInstructions', 'cardPaymentUrl'));
    }

    public function catalog(Request $request, Company $company): JsonResponse
    {
        $profile = $this->profile($company);

        return response()->json($this->bulkOrders->catalogPayload(
            $request->integer('category') ?: null,
            $request->string('q')->toString() ?: null,
            $profile->id,
            $request->boolean('promo'),
        ));
    }

    public function csrfToken(Request $request, Company $company): JsonResponse
    {
        abort_unless($this->settings($company)->storefront_enabled, 404);

        return response()->json([
            'ok' => true,
            'csrf_token' => $request->session()->token(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    public function deliveryQuote(Request $request, Company $company): JsonResponse
    {
        $profile = $this->profile($company);
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $quote = $this->deliveryQuotes->nearestForProfile($profile->id, $latitude, $longitude);

        if (! $quote) {
            return response()->json([
                'ok' => false,
                'message' => 'No hay una sucursal habilitada con ubicación para calcular el envío.',
            ]);
        }

        return response()->json([
            'ok' => true,
            'location' => [
                // Las coordenadas son datos técnicos para el cálculo. Nunca se
                // presentan como si fueran una dirección postal al cliente.
                'label' => 'Ubicación detectada automáticamente',
                'maps_url' => "https://maps.google.com/?q={$latitude},{$longitude}",
            ],
            'branch' => [
                'id' => $quote['branch']->id,
                'name' => $quote['branch']->name,
                'address' => $quote['branch']->address,
            ],
            'distance_km' => $quote['distance_km'],
            'delivery_fee' => $quote['fee'],
            'pending_review' => $quote['pending_review'],
            'message' => $quote['pending_review']
                ? 'La distancia está fuera de los rangos configurados; el costo mostrado es referencial.'
                : 'Costo calculado con el rango de delivery configurado para esta sucursal.',
        ]);
    }

    public function submit(Request $request, Company $company, OrderPdfService $pdf): JsonResponse
    {
        $profile = $this->profile($company);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'service_type' => ['required', Rule::in(['pickup', 'delivery'])],
            'branch_id' => ['required', 'integer', Rule::exists('business_branches', 'id')
                ->where('business_profile_id', $profile->id)->where('is_active', true)->where('orders_enabled', true)],
            'address' => ['required_if:service_type,delivery', 'nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            // "Pide y retira" sin pago adelantado deja pedidos sin retirar
            // -- para ese caso solo se acepta transferencia (mismo criterio
            // aplicado en el checkout, ver selectMode() en storefront/show.
            // blade.php). Delivery conserva las 3 formas de pago de siempre.
            'payment_method' => ['required', Rule::in(
                $request->input('service_type') === 'pickup' ? ['transferencia'] : ['efectivo', 'transferencia', 'tarjeta']
            )],
            'requires_invoice' => ['required', 'boolean'],
            'billing_type' => ['nullable', 'required_if:requires_invoice,true', Rule::in(['cedula', 'ruc', 'pasaporte'])],
            'billing_id' => ['nullable', 'required_if:requires_invoice,true', 'string', 'max:20'],
            'billing_legal_name' => ['nullable', 'required_if:requires_invoice,true', 'string', 'max:255'],
            'billing_address' => ['nullable', 'required_if:requires_invoice,true', 'string', 'max:500'],
            'billing_email' => ['nullable', 'required_if:requires_invoice,true', 'email:rfc', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.variation' => ['nullable', 'string', 'max:120'],
            'items.*.extras' => ['nullable', 'array', 'max:20'],
            'items.*.extras.*' => ['string', 'max:120'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
            'order_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'payment_method.in' => 'Para "Pide y retira" solo aceptamos pago por transferencia.',
        ]);

        $branch = BusinessBranch::query()
            ->where('business_profile_id', $profile->id)
            ->availableForOrders()
            ->with('hours')
            ->find((int) $validated['branch_id']);
        if (! $branch || ! $this->businessHours->branchIsOpen($branch)) {
            return response()->json([
                'ok' => false,
                'message' => $this->businessHours->closedMessage($profile->id)
                    ?? 'La sucursal seleccionada ya cerró. Elige otra disponible para continuar.',
            ], 422);
        }

        // El micrositio no procesa pagos con tarjeta -- igual que el bot,
        // esto se resuelve fuera del chat/web, en la página del negocio.
        // Se valida ANTES de crear el pedido (y reservar stock) para no
        // dejar un pedido huérfano si el negocio todavía no configuró su
        // link de cobro.
        $cardPaymentUrl = null;
        if ($validated['payment_method'] === 'tarjeta') {
            $cardPaymentUrl = trim((string) (WhatsappChatbotConfig::query()
                ->where('business_profile_id', $profile->id)
                ->first()?->metadata['card_payment_url'] ?? ''));
            if ($cardPaymentUrl === '') {
                return response()->json([
                    'ok' => false,
                    'message' => 'El pago con tarjeta no está disponible en este momento. Elige otro método de pago para continuar.',
                ], 422);
            }
        }

        $deliveryQuote = null;
        if ($validated['service_type'] === 'delivery'
            && isset($validated['latitude'], $validated['longitude'])) {
            $deliveryQuote = $this->deliveryQuotes->nearestForProfile(
                $profile->id,
                (float) $validated['latitude'],
                (float) $validated['longitude'],
            );
            if ($deliveryQuote) {
                // La sucursal se decide otra vez en el servidor para que el
                // navegador no pueda alterar ni el local ni la tarifa.
                $validated['branch_id'] = $deliveryQuote['branch']->id;
            }
        }

        // Pedido explícito: todo pedido del micrositio debe quedar ligado a
        // una cuenta real (teléfono+contraseña o Google) -- ya no se permite
        // "pedir como invitado" acá (el bot es distinto: el número de
        // WhatsApp del cliente YA es su identidad real ahí). El frontend ya
        // bloquea el botón de confirmar sin sesión; esto es la validación
        // real del lado servidor.
        $contact = Auth::guard('storefront_customer')->user();
        if (! $contact || $contact->business_profile_id !== $profile->id) {
            return response()->json([
                'ok' => false,
                'needs_account' => true,
                'message' => 'Inicia sesión o crea una cuenta (con tu teléfono o con Google) para confirmar tu pedido.',
            ], 401);
        }
        $metadata = $contact->metadata ?? [];
        if (filled($validated['email'] ?? null)) {
            $metadata['email'] = strtolower(trim($validated['email']));
        }
        $contact->fill([
            'name' => trim($validated['name']),
            'address' => $validated['address'] ?? $contact->address,
            'status' => 'active',
            'bot_enabled' => true,
            'metadata' => $metadata,
        ])->save();

        try {
            $cart = $this->bulkOrders->submitFromStorefront(
                $contact,
                $validated['items'],
                $validated['order_note'] ?? null,
                (int) $validated['branch_id'],
                $validated['service_type'],
                $validated['payment_method'],
                $validated,
            );

            if ($deliveryQuote) {
                $this->deliveryQuotes->applyToCart($cart, $deliveryQuote);
            }

            $accessToken = Str::random(64);
            $cartMetadata = $cart->metadata ?? [];
            $cartMetadata['storefront_access_token_hash'] = hash('sha256', $accessToken);
            $cart->metadata = $cartMetadata;
            if ($validated['payment_method'] === 'transferencia') {
                $cart->markAwaitingPaymentProof();
            } else {
                $cart->save();
            }
            $this->orderSelfService->saveInvoicePreference($cart, $contact, $validated);

            if ($cardPaymentUrl !== null) {
                // El resto de la compra ocurre fuera de este micrositio; no
                // hay forma de saber si el cliente llegó a pagar ahí, así
                // que se cierra el pedido ya mismo (libera stock) en vez de
                // dejarlo pendiente indefinidamente -- mismo criterio que
                // WhatsappService::procesarPagoTarjeta().
                try {
                    app(OrderLifecycleService::class)->transition($cart, WhatsappCart::STATUS_CANCELLED, null, WhatsappCart::CANCEL_REASON_CARD_PAYMENT);
                } catch (\Throwable $e) {
                    report($e);
                }

                return response()->json([
                    'ok' => true,
                    'card_payment_redirect' => true,
                    'payment_url' => $cardPaymentUrl,
                    'order_number' => $cart->getOrderNumber(),
                    'total' => (float) $cart->total,
                    'message' => 'Por el momento no procesamos pagos con tarjeta en esta página. Continúa tu compra de forma segura en nuestro sitio web.',
                ]);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Tu pedido fue registrado correctamente.',
                'order_number' => $cart->getOrderNumber(),
                'order_id' => $cart->id,
                'total' => (float) $cart->total,
                'pdf_url' => $pdf->signedDownloadUrl($cart),
                'proof_upload_url' => route('storefront.order.payment-proof', [$company, $cart], false),
                'order_access_token' => $accessToken,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function uploadPaymentProof(Request $request, Company $company, WhatsappCart $cart): JsonResponse
    {
        $profile = $this->profile($company);
        abort_unless($cart->contact?->business_profile_id === $profile->id, 404);
        $tokenHash = (string) data_get($cart->metadata, 'storefront_access_token_hash');
        abort_unless($tokenHash !== '' && hash_equals($tokenHash, hash('sha256', (string) $request->input('token'))), 403);
        abort_if($cart->hasPaymentProof(), 422, 'Este pedido ya tiene un comprobante cargado.');

        $validated = $request->validate(['proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:12288']]);
        try {
            $this->orderSelfService->savePaymentProof($cart, $cart->contact, $validated['proof']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Comprobante recibido. Lo verificaremos pronto.']);
    }

    private function resolveCompany(Request $request, ?Company $company): Company
    {
        if ($company) {
            return $company;
        }

        $host = strtolower(preg_replace('/:\d+$/', '', $request->getHost()));
        $byDomain = CompanyStorefrontSetting::query()->where('custom_domain', $host)->first()?->company;

        return $byDomain ?: Company::query()
            ->where('slug', config('storefront.default_company_slug'))
            ->where('status', 'active')->firstOrFail();
    }

    private function profile(Company $company)
    {
        abort_unless($company->status === 'active', 404);

        return $company->whatsappAccounts()->orderByDesc('is_primary')->orderBy('id')->firstOrFail();
    }

    private function settings(Company $company): CompanyStorefrontSetting
    {
        return $company->storefrontSetting()->firstOrCreate([], [
            'primary_color' => '#E85D04', 'secondary_color' => '#7C2D12',
            'accent_color' => '#FFD166', 'storefront_enabled' => true,
        ]);
    }
}
