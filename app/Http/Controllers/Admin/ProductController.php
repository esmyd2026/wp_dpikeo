<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Models\Franchise;
use App\Services\DemoClienteService;
use App\Services\PlanLimitsService;
use App\Services\ProductImageService;
use App\Services\ProductImportExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(
        private readonly PlanLimitsService $planLimits,
        private readonly DemoClienteService $demoCliente,
        private readonly ProductImportExportService $productImportExport,
        private readonly ProductImageService $productImages,
        private readonly \App\Services\OrderLifecycleService $orderLifecycle,
    ) {}

    public function index()
    {
        $products = WhatsappPrice::with(['menuCategory:id,title,description,icon,franchise_id', 'franchise:id,name,slug'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $categories = WhatsappMenuItem::catalogCategories()
            ->where('is_active', true)
            ->select('id', 'title', 'description', 'icon', 'franchise_id')
            ->orderBy('order')
            ->get();

        $stats = WhatsappPrice::summaryStats();
        $planLimits = $this->planLimits->snapshot();

        return view('admin.products.index', compact('products', 'categories', 'stats', 'planLimits') + [
            'demoClienteOptions' => $this->demoCliente->options(),
            'activeDemoCliente' => $this->demoCliente->activeKey(),
            'franchises' => Franchise::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function create()
    {
        $categories = $this->getCategories();

        return view('admin.products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        if (!$this->planLimits->canCreateProduct()) {
            return response()->json([
                'message' => $this->planLimits->productLimitMessage(),
            ], 422);
        }

        $data = $this->validateAndPrepare($request);

        if ($data instanceof \Illuminate\Http\JsonResponse) {
            return $data;
        }

        if ($request->hasFile('image')) {
            $data['image'] = $this->productImages->store($request->file('image'));
        }

        $product = WhatsappPrice::create($data);

        return response()->json([
            'message' => 'Producto creado correctamente',
            'product' => $this->formatProduct($product->load('menuCategory')),
        ]);
    }

    public function show(WhatsappPrice $product)
    {
        return response()->json($this->formatProduct($product->load('menuCategory')));
    }

    public function edit(WhatsappPrice $product)
    {
        $categories = $this->getCategories();

        return view('admin.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, WhatsappPrice $product)
    {
        $previousStock = (int) $product->stock;
        $data = $this->validateAndPrepare($request, $product);

        if ($data instanceof \Illuminate\Http\JsonResponse) {
            return $data;
        }

        if ($request->boolean('remove_image')) {
            $this->productImages->delete($product->image);
            $data['image'] = null;
        } elseif ($request->hasFile('image')) {
            $data['image'] = $this->productImages->store($request->file('image'), $product->image);
        }

        $product->update($data);
        $this->orderLifecycle->recordManualStockChange($product->fresh(), $previousStock, $request->user()?->id);

        return response()->json([
            'message' => 'Producto actualizado correctamente',
            'product' => $this->formatProduct($product->fresh()->load('menuCategory')),
        ]);
    }

    public function destroy(WhatsappPrice $product)
    {
        // Un producto vendido no se borra: se desactiva y se conserva el
        // historial, el PDF y las líneas de pedidos anteriores.
        if ($product->cartItems()->exists()) {
            $product->update(['is_active' => false]);

            return response()->json([
                'message' => 'El producto tiene pedidos históricos y fue desactivado para conservarlos.',
                'deactivated' => true,
            ]);
        }

        $this->productImages->delete($product->image);
        $product->delete();

        return response()->json(['message' => 'Producto eliminado correctamente']);
    }

    /**
     * Duplica un producto hacia una o varias franquicias, creando (o
     * reutilizando) la categoría equivalente en cada una y evitando crear un
     * duplicado si ya existe un producto con el mismo nombre allí.
     */
    public function duplicate(Request $request, WhatsappPrice $product)
    {
        $validated = $request->validate([
            'franchise_ids' => 'required|array|min:1',
            'franchise_ids.*' => 'integer|exists:franchises,id',
        ]);

        $product->loadMissing('menuCategory');
        $sourceCategoryTitle = $product->menuCategory?->title ?? $product->category ?? 'Sin categoría';

        $results = [];

        foreach (array_unique($validated['franchise_ids']) as $franchiseId) {
            $franchise = Franchise::query()->whereKey($franchiseId)->where('is_active', true)->first();
            if (!$franchise) {
                $results[] = ['franchise' => null, 'status' => 'error', 'message' => 'Franquicia no encontrada o inactiva.'];
                continue;
            }

            if ((int) $franchise->id === (int) $product->franchise_id) {
                $results[] = ['franchise' => $franchise->name, 'status' => 'skipped', 'message' => 'Es la misma franquicia del producto original.'];
                continue;
            }

            if (!$this->planLimits->canCreateProduct()) {
                $results[] = ['franchise' => $franchise->name, 'status' => 'error', 'message' => $this->planLimits->productLimitMessage()];
                continue;
            }

            $exists = WhatsappPrice::where('franchise_id', $franchise->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($product->name))])
                ->exists();
            if ($exists) {
                $results[] = ['franchise' => $franchise->name, 'status' => 'skipped', 'message' => 'Ya existe un producto con ese nombre en esta franquicia.'];
                continue;
            }

            $targetCategory = $this->findOrCreateEquivalentCategory($product->menuCategory, $sourceCategoryTitle, $franchise);
            $newSku = $this->generateUniqueSku($product->sku, $franchise);
            $imagePath = $product->image ? $this->productImages->copy($product->image) : null;

            $copy = WhatsappPrice::create([
                'menu_item_id' => $targetCategory->id,
                'franchise_id' => $franchise->id,
                'category' => $targetCategory->title,
                'sku' => $newSku,
                'name' => $product->name,
                'description' => $product->description,
                'benefits' => $product->benefits,
                'characteristics' => $product->characteristics,
                'price' => $product->price,
                'promo_price' => $product->promo_price,
                'is_promo' => $product->is_promo,
                'promo_start_date' => $product->promo_start_date,
                'promo_end_date' => $product->promo_end_date,
                'currency' => $product->currency,
                'is_active' => $product->is_active,
                'demo_cliente' => $franchise->slug,
                // El inventario es propio de cada local: no se copia.
                'stock' => 0,
                'allow_quantity_selection' => $product->allow_quantity_selection,
                'min_quantity' => $product->min_quantity,
                'max_quantity' => $product->max_quantity,
                'image' => $imagePath,
                'metadata' => $product->metadata,
            ]);

            $results[] = [
                'franchise' => $franchise->name,
                'status' => 'created',
                'message' => "Creado como {$newSku}.",
                'product_id' => $copy->id,
            ];
        }

        return response()->json(['results' => $results]);
    }

    private function findOrCreateEquivalentCategory(?WhatsappMenuItem $sourceCategory, string $title, Franchise $franchise): WhatsappMenuItem
    {
        $existing = WhatsappMenuItem::catalogCategories()
            ->where('franchise_id', $franchise->id)
            ->whereRaw('LOWER(title) = ?', [mb_strtolower(trim($title))])
            ->first();

        if ($existing) {
            return $existing;
        }

        $menuId = $sourceCategory?->menu_id ?? $this->getPricesMenu()->id;

        return WhatsappMenuItem::create([
            'menu_id' => $menuId,
            'franchise_id' => $franchise->id,
            'title' => $title,
            'description' => $sourceCategory?->description,
            'action_id' => $this->makeUniqueActionId($menuId, $title),
            'icon' => $sourceCategory?->icon ?? '📦',
            'order' => $sourceCategory?->order ?? 0,
            'is_active' => true,
            'demo_cliente' => $franchise->slug,
        ]);
    }

    private function getPricesMenu(): \App\Models\WhatsappMenu
    {
        $menu = \App\Models\WhatsappMenu::where('action_id', 'prices_menu')->first();

        if (!$menu) {
            abort(500, 'No está configurado el menú de catálogo (prices_menu).');
        }

        return $menu;
    }

    private function makeUniqueActionId(int $menuId, string $title): string
    {
        $base = \Illuminate\Support\Str::slug($title, '_') ?: 'categoria';
        $base = \Illuminate\Support\Str::limit($base, 40, '');
        $actionId = $base;
        $suffix = 1;

        while (WhatsappMenuItem::where('menu_id', $menuId)->where('action_id', $actionId)->exists()) {
            $actionId = $base . '_' . $suffix;
            $suffix++;
        }

        return $actionId;
    }

    private function generateUniqueSku(string $baseSku, Franchise $franchise): string
    {
        $suffix = '-' . mb_strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $franchise->slug) ?: 'X', 0, 3));
        $base = mb_strtoupper(mb_substr($baseSku, 0, 20 - mb_strlen($suffix)) . $suffix);

        $candidate = $base;
        $i = 2;
        while (WhatsappPrice::where('sku', $candidate)->exists()) {
            $extra = (string) $i;
            $candidate = mb_substr($base, 0, 20 - mb_strlen($extra)) . $extra;
            $i++;
        }

        return $candidate;
    }

    public function downloadImportTemplate()
    {
        return $this->productImportExport->templateDownloadResponse();
    }

    public function exportCatalog()
    {
        return $this->productImportExport->exportDownloadResponse();
    }

    public function importCatalog(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'mode' => 'nullable|in:upsert,create,update',
        ]);

        $result = $this->productImportExport->importFromUpload(
            $request->file('file'),
            $request->input('mode', 'upsert')
        );

        $total = $result['created'] + $result['updated'];
        $message = $total > 0
            ? "Importación completada: {$result['created']} creados, {$result['updated']} actualizados."
            : 'No se importó ningún producto.';

        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} fila(s) omitida(s).";
        }

        return response()->json([
            'message' => $message,
            'result' => $result,
        ], $total > 0 ? 200 : 422);
    }

    public function bulkUpdateStatus(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:whatsapp_prices,id',
            'is_active' => 'required',
        ]);

        $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isActive === null) {
            return response()->json(['message' => 'Estado inválido.'], 422);
        }

        $updated = WhatsappPrice::query()
            ->whereIn('id', $validated['ids'])
            ->update(['is_active' => $isActive]);

        return response()->json([
            'message' => $isActive
                ? "{$updated} producto(s) activado(s) correctamente."
                : "{$updated} producto(s) desactivado(s) correctamente.",
            'updated' => $updated,
        ]);
    }

    private function getCategories()
    {
        return WhatsappMenuItem::catalogCategories()
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    private function validateAndPrepare(Request $request, ?WhatsappPrice $product = null)
    {
        $productId = $product?->id;

        $validator = Validator::make($request->all(), [
            'sku' => [
                'required',
                'string',
                'max:20',
                Rule::unique('whatsapp_prices', 'sku')->ignore($productId),
            ],
            'name' => 'required|string|max:255',
            'menu_item_id' => 'required|exists:whatsapp_menu_items,id',
            'price' => 'required|numeric|min:0',
            'promo_price' => 'nullable|numeric|min:0|lt:price',
            'description' => 'nullable|string|max:5000',
            'benefits' => 'nullable|string|max:5000',
            'characteristics' => 'nullable|string|max:5000',
            'variations' => 'nullable|string|max:5000',
            'extras' => 'nullable|string|max:5000',
            'stock' => 'nullable|integer|min:0',
            'allow_quantity_selection' => 'nullable|boolean',
            'min_quantity' => 'nullable|integer|min:1',
            'max_quantity' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'franchise_id' => 'required|integer|exists:franchises,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'remove_image' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $category = WhatsappMenuItem::findOrFail($validated['menu_item_id']);

        $promoPrice = isset($validated['promo_price']) && $validated['promo_price'] !== ''
            ? (float) $validated['promo_price']
            : null;

        $minQty = (int) ($validated['min_quantity'] ?? 1);
        $maxQty = (int) ($validated['max_quantity'] ?? 999);

        if ($maxQty < $minQty) {
            return response()->json([
                'errors' => ['max_quantity' => ['La cantidad máxima debe ser mayor o igual a la mínima.']],
            ], 422);
        }

        $franchise = Franchise::query()->whereKey($validated['franchise_id'])->where('is_active', true)->first();
        if (!$franchise) {
            return response()->json(['errors' => ['franchise_id' => ['Selecciona una franquicia activa.']]], 422);
        }
        if ($category->franchise_id && (int) $category->franchise_id !== (int) $franchise->id) {
            return response()->json(['errors' => ['franchise_id' => ['La franquicia debe coincidir con la categoría elegida.']]], 422);
        }

        $metadata = is_array($product?->metadata) ? $product->metadata : [];
        $metadata['variations'] = $this->parsePricedOptions($validated['variations'] ?? '');
        $metadata['extras'] = $this->parsePricedOptions($validated['extras'] ?? '');

        return [
            'menu_item_id' => $category->id,
            'franchise_id' => $franchise->id,
            'category' => $category->title,
            'sku' => strtoupper(trim($validated['sku'])),
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'benefits' => $validated['benefits'] ?? null,
            'characteristics' => $this->parseCharacteristics($validated['characteristics'] ?? ''),
            'price' => (float) $validated['price'],
            'promo_price' => $promoPrice,
            'is_promo' => $promoPrice !== null && $promoPrice > 0,
            'promo_start_date' => ($promoPrice !== null && $promoPrice > 0) ? now()->toDateString() : null,
            'promo_end_date' => ($promoPrice !== null && $promoPrice > 0) ? now()->addDays(30)->toDateString() : null,
            'currency' => 'USD',
            'is_active' => $request->boolean('is_active'),
            // Columna histórica usada por el flujo existente; el origen real
            // del catálogo es franchise_id y su slug.
            'demo_cliente' => $franchise->slug,
            'stock' => (int) ($validated['stock'] ?? 0),
            'allow_quantity_selection' => $request->boolean('allow_quantity_selection', true),
            'min_quantity' => $minQty,
            'max_quantity' => $maxQty,
            'metadata' => $metadata,
        ];
    }

    /**
     * Convierte las opciones del panel a una estructura reutilizable por los
     * canales conversacionales. Una opción por línea: "Nombre | 4.50".
     * El precio es opcional para extras que se cotizan en el local.
     */
    private function parsePricedOptions(?string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $text);
        $options = [];

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            $title = $parts[0] ?? '';
            if ($title === '') {
                continue;
            }

            $price = isset($parts[1]) && $parts[1] !== ''
                ? (float) str_replace(',', '.', str_replace('$', '', $parts[1]))
                : null;

            $options[] = ['title' => $title, 'price' => $price];
        }

        return $options;
    }

    private function pricedOptionsToText(array|string|null $options): string
    {
        if (is_string($options)) {
            $options = json_decode($options, true) ?: [];
        }

        if (!is_array($options)) {
            return '';
        }

        return collect($options)
            ->filter(fn ($option) => is_array($option) && !empty($option['title']))
            ->map(fn ($option) => $option['title'] . (isset($option['price']) && $option['price'] !== null ? ' | ' . number_format((float) $option['price'], 2, '.', '') : ''))
            ->implode("\n");
    }

    private function parseCharacteristics(?string $text): array
    {
        if (!$text) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);

        return array_values(array_filter(array_map('trim', $lines)));
    }

    private function characteristicsToText($characteristics): string
    {
        if (empty($characteristics)) {
            return '';
        }

        if (is_string($characteristics)) {
            $decoded = json_decode($characteristics, true);
            $characteristics = is_array($decoded) ? $decoded : [$characteristics];
        }

        if (!is_array($characteristics)) {
            return '';
        }

        return implode("\n", $characteristics);
    }

    private function formatProduct(WhatsappPrice $product): array
    {
        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'menu_item_id' => $product->menu_item_id,
            'franchise_id' => $product->franchise_id,
            'category' => $product->category,
            'category_title' => $product->menuCategory?->title ?? $product->category,
            'description' => $product->description,
            'benefits' => $product->benefits,
            'characteristics' => $this->characteristicsToText($product->characteristics),
            'price' => $product->price,
            'promo_price' => $product->promo_price,
            'is_promo' => $product->is_promo,
            'is_active' => (bool) $product->is_active,
            'demo_cliente' => $product->demo_cliente,
            'stock' => $product->stock,
            'allow_quantity_selection' => (bool) $product->allow_quantity_selection,
            'min_quantity' => $product->min_quantity,
            'max_quantity' => $product->max_quantity,
            'icon' => $product->icon,
            'image' => $product->image,
            'image_url' => $product->image_url,
            'variations' => $this->pricedOptionsToText($product->metadata['variations'] ?? []),
            'extras' => $this->pricedOptionsToText($product->metadata['extras'] ?? []),
        ];
    }
}
