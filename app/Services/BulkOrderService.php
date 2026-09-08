<?php

namespace App\Services;

use App\Models\BulkOrderToken;
use App\Models\BusinessBranch;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class BulkOrderService
{
    public function __construct(
        private DemoClienteService $demoCliente,
        private ProductImageService $productImages,
    ) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function minCartLines(): int
    {
        return max(1, (int) config('bulk_order.min_cart_lines', 3));
    }

    public function issueToken(WhatsappContact $contact): ?BulkOrderToken
    {
        if (! $this->isAvailable()) {
            return null;
        }

        BulkOrderToken::query()
            ->where('contact_id', $contact->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->delete();

        $hours = max(1, (int) config('bulk_order.token_ttl_hours', 24));

        return BulkOrderToken::create([
            'contact_id' => $contact->id,
            'token' => BulkOrderToken::generateToken(),
            'expires_at' => now()->addHours($hours),
        ]);
    }

    public function formUrl(BulkOrderToken $token): string
    {
        $baseUrl = rtrim((string) config('bulk_order.public_url', config('app.url')), '/');

        return $baseUrl.'/pedido/'.$token->token;
    }

    public function findValidToken(string $token): ?BulkOrderToken
    {
        $record = BulkOrderToken::query()
            ->where('token', $token)
            ->with('contact')
            ->first();

        return $record && $record->isValid() ? $record : null;
    }

    /**
     * Líneas del carrito activo del contacto (si tiene uno del chat del bot),
     * solo para avisarle en el micrositio que ya se van a incluir en su
     * pedido — no se pueden editar aquí, se listan tal cual quedaron.
     *
     * @return array<int, array{name: string, quantity: int, price: float}>
     */
    public function existingCartItems(WhatsappContact $contact): array
    {
        $cart = WhatsappCart::query()
            ->where('contact_id', $contact->id)
            ->where('status', 'active')
            ->with('items')
            ->first();

        if (! $cart) {
            return [];
        }

        return $cart->items->map(fn ($item) => [
            'name' => $item->name,
            'quantity' => (int) $item->quantity,
            'price' => (float) $item->price,
        ])->all();
    }

    /**
     * @return array{categories: array<int, array<string, mixed>>, products: array<int, array<string, mixed>>}
     */
    public function catalogPayload(?int $categoryId = null, ?string $search = null, ?int $businessProfileId = null): array
    {
        $categories = $this->demoCliente->scopeCategoriesWithVisibleProducts(
            $this->demoCliente->applyCategoryScope(
                WhatsappMenuItem::catalogCategories($businessProfileId)
            )
        )
            ->where('is_active', true)
            ->orderBy('order')
            ->get(['id', 'title', 'icon'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'icon' => $c->icon ?: '📦',
            ])
            ->values()
            ->all();

        $query = $this->demoCliente->applyProductScope(
            WhatsappPrice::query()
                ->where('business_profile_id', $businessProfileId)
                ->where('is_active', true)->where('stock', '>', 0)
        )
            ->with('menuCategory:id,title,icon');

        if ($categoryId) {
            $query->where('menu_item_id', $categoryId);
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        $products = $query
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(function (WhatsappPrice $p) {
                $unit = $p->is_promo && $p->promo_price ? (float) $p->promo_price : (float) $p->price;

                return [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'name' => $p->name,
                    'description' => $this->cleanText($p->description),
                    'measurements' => $this->productMeasurements($p),
                    'characteristics' => $this->productCharacteristics($p),
                    'category_id' => $p->menu_item_id,
                    'category' => $p->menuCategory?->title,
                    'price' => $unit,
                    'is_promo' => (bool) $p->is_promo,
                    'allow_quantity' => (bool) $p->allow_quantity_selection,
                    'min_qty' => max(1, (int) ($p->min_quantity ?? 1)),
                    'max_qty' => max(1, (int) ($p->max_quantity ?? 99)),
                    // La tienda puede abrirse desde un dominio o puerto distinto
                    // al APP_URL; las imágenes locales deben seguir ese origen.
                    'image' => $this->productImages->resolveWebUrl($p->image),
                    'variations' => $this->pricedOptions($p->metadata['variations'] ?? []),
                    'extras' => $this->pricedOptions($p->metadata['extras'] ?? []),
                ];
            })
            ->values()
            ->all();

        return [
            'categories' => $categories,
            'products' => $products,
        ];
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int, variation?: string|null, extras?: array<int, string>, note?: string|null}>  $items
     */
    public function submitFromForm(BulkOrderToken $token, array $items, ?string $orderNote = null, ?int $branchId = null): WhatsappCart
    {
        if (! $token->isValid()) {
            throw new InvalidArgumentException('El enlace expiró o ya fue utilizado.');
        }

        $contact = $token->contact;
        if (! $contact) {
            throw new InvalidArgumentException('Cliente no encontrado.');
        }

        $cart = $this->submitForContact(
            $contact,
            $items,
            $orderNote,
            [
                'source' => 'bulk_web_form',
                'bulk_order_token_id' => $token->id,
                'branch_id' => $branchId,
                'branch_confirmed' => true,
                'submitted_at' => now()->toIso8601String(),
            ]
        );

        $token->markUsed($cart->id);

        return $cart;
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int, note?: string|null}>  $items
     */
    /**
     * @param  ?array{service_type?: string|null, pickup_mode?: string|null, table_reference?: string|null}  $fulfillment
     */
    public function submitFromAdmin(
        WhatsappContact $contact,
        array $items,
        ?string $orderNote,
        int $userId,
        bool $notifyWhatsapp = true,
        ?int $branchId = null,
        ?array $fulfillment = null,
        ?string $paymentMethod = null,
    ): WhatsappCart {
        $metadata = [
            'source' => 'admin_web_form',
            'created_by_user_id' => $userId,
            'branch_id' => $branchId,
            'submitted_at' => now()->toIso8601String(),
        ];

        if ($fulfillment !== null) {
            // El operador de caja/POS ya resolvió sucursal y tipo de pedido;
            // si este carrito llegara a pasar por el checkout de WhatsApp
            // (no debería), no debe volver a preguntar la sucursal.
            $metadata['branch_confirmed'] = true;
            $metadata['service_type'] = $fulfillment['service_type'] ?? null;
            $metadata['pickup_mode'] = $fulfillment['pickup_mode'] ?? null;
            if (! empty($fulfillment['table_reference'])) {
                $metadata['table_reference'] = $fulfillment['table_reference'];
            }
        }

        $cart = $this->submitForContact($contact, $items, $orderNote, $metadata);

        if ($paymentMethod !== null) {
            // submitForContact() deja "order_number" como atributo sintético
            // (no es una columna real) para que el llamador lo lea sin otra
            // consulta; hay que quitarlo antes de guardar o el UPDATE falla.
            $orderNumber = $cart->getAttribute('order_number');
            unset($cart->order_number);

            $cart->payment_method = $paymentMethod;
            $cart->payment_status = $paymentMethod === 'efectivo' ? 'cash_on_delivery' : 'pending';
            $cart->save();

            if ($orderNumber !== null) {
                $cart->setAttribute('order_number', $orderNumber);
            }
        }

        if ($notifyWhatsapp) {
            $cartId = $cart->id;
            dispatch(function () use ($cartId) {
                $loaded = WhatsappCart::with('contact')->find($cartId);
                if ($loaded) {
                    app(self::class)->notifyContactViaWhatsapp($loaded);
                }
            })->afterResponse();
        }

        return $cart;
    }

    /**
     * @return array<int, array{id: int, name: string, phone: string|null, identity: string|null}>
     */
    public function searchContacts(string $query, int $limit = 20, ?int $businessProfileId = null): array
    {
        $term = trim($query);
        $like = '%'.$term.'%';

        return WhatsappContact::query()
            ->where('status', 'active')
            ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
            ->when($term !== '', function ($q) use ($like) {
                $q->where(function ($contactQuery) use ($like) {
                    $contactQuery->where('name', 'like', $like)
                        ->orWhere('phone_number', 'like', $like)
                        ->orWhere('national_id', 'like', $like)
                        ->orWhere('billing_id', 'like', $like);
                });
            })
            ->orderBy('name')
            ->limit(max(1, min(30, $limit)))
            ->get(['id', 'name', 'phone_number', 'national_id', 'billing_id'])
            ->map(fn (WhatsappContact $c) => [
                'id' => $c->id,
                'name' => $c->name ?: 'Cliente',
                'phone' => str_starts_with((string) $c->phone_number, 'POS-') ? null : $c->phone_number,
                'identity' => $c->national_id ?: $c->billing_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int, note?: string|null}>  $items
     */
    public function submitForContact(
        WhatsappContact $contact,
        array $items,
        ?string $orderNote = null,
        array $metadata = []
    ): WhatsappCart {
        if ($items === []) {
            throw new InvalidArgumentException('Agrega al menos un producto.');
        }

        return DB::transaction(function () use ($contact, $items, $orderNote, $metadata) {
            // Si el cliente ya tenía un carrito activo (por ejemplo, agregó
            // productos por el chat del bot y luego entró al micrositio a
            // armar una lista), no lo descartamos: sus líneas se copian tal
            // cual (mismo precio y nota ya calculados) al carrito nuevo, para
            // que no "desaparezcan" productos que el cliente ya había elegido.
            $previousCart = WhatsappCart::query()
                ->where('contact_id', $contact->id)
                ->where('status', 'active')
                ->with('items')
                ->first();

            $carryOverLines = $previousCart
                ? $previousCart->items->map(fn ($item) => [
                    'whatsapp_price_id' => $item->whatsapp_price_id,
                    'name' => $item->name,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'line_note' => $item->line_note,
                ])->all()
                : [];

            WhatsappCart::query()
                ->where('contact_id', $contact->id)
                ->where('status', 'active')
                ->update(['status' => 'abandoned']);

            $branchId = $this->resolveBranchId($contact, $metadata['branch_id'] ?? null);

            $cart = WhatsappCart::create([
                'contact_id' => $contact->id,
                'branch_id' => $branchId,
                'total' => 0,
                'status' => 'active',
                'note' => $orderNote,
                'metadata' => $metadata,
                // El cliente puede haber elegido el método de pago por chat
                // (ver WhatsappService::interceptForPaymentMethod) antes de
                // entrar al micrositio a armar la lista -- sin esto, el
                // carrito nuevo lo perdía y confirmarPedido() se lo volvía a
                // preguntar, aunque ya lo hubiera elegido.
                'payment_method' => $previousCart?->payment_method,
                'payment_status' => $previousCart?->payment_status,
            ]);

            $total = 0;

            foreach ($carryOverLines as $line) {
                $cart->items()->create($line);
                $total += (float) $line['price'] * (int) $line['quantity'];
            }

            foreach ($items as $row) {
                $productId = (int) ($row['product_id'] ?? 0);
                $quantity = max(1, (int) ($row['quantity'] ?? 1));
                $customerNote = isset($row['note']) ? trim((string) $row['note']) : null;

                $price = WhatsappPrice::query()
                    ->where('id', $productId)
                    ->where('business_profile_id', $contact->business_profile_id)
                    ->where('is_active', true)
                    ->where('stock', '>', 0)
                    ->first();

                if (! $price) {
                    throw new InvalidArgumentException('Uno de los productos ya no está disponible.');
                }

                if ($price->allow_quantity_selection) {
                    $min = max(1, (int) ($price->min_quantity ?? 1));
                    $max = max($min, (int) ($price->max_quantity ?? 99));
                    $quantity = min($max, max($min, $quantity));
                } else {
                    $quantity = 1;
                }

                $unitPrice = $price->is_promo && $price->promo_price
                    ? (float) $price->promo_price
                    : (float) $price->price;

                $variationName = trim((string) ($row['variation'] ?? ''));
                $variation = collect($this->pricedOptions($price->metadata['variations'] ?? []))
                    ->firstWhere('title', $variationName);
                if ($variation) {
                    $unitPrice = (float) $variation['price'];
                }

                $extrasRequested = is_array($row['extras'] ?? null) ? $row['extras'] : [];
                $availableExtras = collect($this->pricedOptions($price->metadata['extras'] ?? []))->keyBy('title');
                $extraTitles = [];
                foreach ($extrasRequested as $extraTitle) {
                    $extra = $availableExtras->get(trim((string) $extraTitle));
                    if ($extra) {
                        $unitPrice += (float) $extra['price'];
                        $extraTitles[] = $extra['title'];
                    }
                }

                $noteParts = array_filter([
                    $variation ? 'Opción: '.$variation['title'] : null,
                    $extraTitles !== [] ? 'Extras: '.implode(', ', $extraTitles) : null,
                    $customerNote ?: null,
                ]);
                $lineNote = $noteParts !== [] ? implode(' · ', $noteParts) : null;

                $cart->items()->create([
                    'whatsapp_price_id' => $price->id,
                    'name' => $price->name,
                    'price' => $unitPrice,
                    'quantity' => $quantity,
                    'line_note' => $lineNote,
                ]);

                $total += $unitPrice * $quantity;
            }

            $cart->total = round($total, 2);
            $cart->save();

            $lifecycle = app(OrderLifecycleService::class);
            WhatsappCart::query()
                ->where('contact_id', $contact->id)
                ->where('status', WhatsappCart::STATUS_PENDING)
                ->where('id', '!=', $cart->id)
                ->get()
                ->each(fn (WhatsappCart $stale) => $lifecycle->transition(
                    $stale, WhatsappCart::STATUS_CANCELLED, null, WhatsappCart::CANCEL_REASON_SUPERSEDED
                ));

            $whatsapp = app(WhatsappService::class);

            try {
                $whatsapp->useBusinessProfile($contact->businessProfile);
            } catch (\Throwable $e) {
                Log::error('[BulkOrderService] No se pudo resolver el perfil de WhatsApp del contacto', [
                    'contact_id' => $contact->id,
                    'error' => $e->getMessage(),
                ]);

                throw new InvalidArgumentException('No se pudo confirmar el pedido por WhatsApp: revisá la conexión de WhatsApp de esta empresa.');
            }

            $whatsapp->finalizeBulkWebOrder($cart);
            $cart->refresh();
            // Sin atributo sintético "order_number" acá (a diferencia de
            // submitFromAdmin() más arriba, que sí lo necesita y lo quita
            // antes de guardar): finalizeBulkWebOrder() ya deja el número
            // persistido en metadata->order_details, así que
            // $cart->getOrderNumber() lo lee bien después del refresh() sin
            // necesidad de un atributo que no es una columna real -- dejarlo
            // puesto acá hacía crashear el guardado posterior en
            // OrderConfirmationService::sendToClient() ("Unknown column
            // 'order_number'"), y el pedido terminaba sin la confirmación
            // completa (solo el mensaje de respaldo).

            return $cart->load('items');
        });
    }

    private function resolveBranchId(WhatsappContact $contact, mixed $requestedBranchId): int
    {
        $branches = BusinessBranch::query()
            ->where('business_profile_id', $contact->business_profile_id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get(['id']);

        if ($branches->isEmpty()) {
            $profile = $contact->businessProfile;
            if (! $profile) {
                throw new InvalidArgumentException('No se pudo determinar la empresa que recibirá el pedido.');
            }

            // Resguardo para conexiones antiguas o importadas que todavía no
            // tengan local: se crea su propia Matriz, nunca se toma una global.
            return (int) BusinessBranch::ensureDefaultForProfile($profile)->id;
        }

        if ($requestedBranchId !== null && $requestedBranchId !== '') {
            $requested = (int) $requestedBranchId;
            if ($branches->contains(fn (BusinessBranch $branch) => (int) $branch->id === $requested)) {
                return $requested;
            }

            throw new InvalidArgumentException('La sucursal seleccionada no pertenece a esta empresa o está inactiva.');
        }

        if ($branches->count() === 1) {
            return (int) $branches->first()->id;
        }

        throw new InvalidArgumentException('Selecciona la sucursal que atenderá el pedido.');
    }

    /**
     * Pedido explícito: el formulario web ("Armar lista") nunca pregunta
     * "para llevar/servir" ni retiro/delivery -- eso lo debe preguntar el
     * BOT por WhatsApp antes de mandar la confirmación. Si al carrito le
     * falta esa info, se le manda la pregunta en vez de la confirmación
     * (PDF + botones); esta se manda recién cuando responda todo, ver
     * WhatsappService::continueAfterFulfillmentStep(). Pedidos que ya
     * traen esa info resuelta (ej. armados desde el panel/POS) van
     * directo a la confirmación, sin preguntar nada de más.
     */
    public function notifyContactViaWhatsapp(WhatsappCart $cart): void
    {
        try {
            $cart->loadMissing('contact');
            $contact = $cart->contact;
            if (! $contact) {
                return;
            }

            // Solo el formulario público ("Armar lista") nunca pregunta tipo
            // de servicio ni retiro/delivery -- pedidos armados desde el
            // panel/POS (submitFromAdmin) ya resuelven o descartan esa info
            // explícitamente, y no deben empezar a preguntarle al cliente
            // algo que antes no se preguntaba ahí.
            if (($cart->metadata['source'] ?? null) === 'bulk_web_form') {
                $whatsapp = app(WhatsappService::class);
                $whatsapp->useBusinessProfile($contact->businessProfile);

                if ($whatsapp->askNextBulkOrderFulfillmentStep($contact, $cart)) {
                    return;
                }
            }

            app(OrderConfirmationService::class)->notifyBulkOrderSubmitted($contact, $cart);
        } catch (\Throwable $e) {
            Log::error('[BulkOrder] No se pudo notificar por WhatsApp', [
                'cart_id' => $cart->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function cleanText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $text !== '' ? $text : null;
    }

    private function productMeasurements(WhatsappPrice $product): ?string
    {
        $parts = array_values(array_filter([
            $this->cleanText($product->quantity ?? null),
            $this->cleanText($product->format ?? null),
            $this->cleanText($product->flavor ?? null),
        ]));

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    /**
     * @return array<int, string>
     */
    private function productCharacteristics(WhatsappPrice $product): array
    {
        $raw = $product->characteristics;
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            $items = $raw;
        } elseif (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $items = is_array($decoded) ? $decoded : preg_split('/\r\n|\r|\n/', $raw);
        } else {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => $this->cleanText(is_string($item) ? $item : (string) $item),
            $items
        )));
    }

    /** @return array<int, array{title: string, price: float}> */
    private function pricedOptions(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        return collect($options)
            ->filter(fn ($item) => is_array($item) && ! empty($item['title']))
            ->map(fn ($item) => [
                'title' => trim((string) $item['title']),
                'price' => (float) ($item['price'] ?? 0),
            ])
            ->values()
            ->all();
    }
}
