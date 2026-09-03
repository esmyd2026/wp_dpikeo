<?php

namespace App\Services;

use App\Enums\MarketingStepKey;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowStep;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Services\Whatsapp\WhatsappMessagePayload;
use Illuminate\Support\Str;

class MarketingCatalogBuilder
{
    protected ?WhatsappMenu $catalogMenuCache = null;

    public function __construct(
        protected MarketingFlowPayloadBuilder $payloadBuilder,
        protected DemoClienteService $demoCliente,
        protected ?WhatsappBusinessProfile $businessProfile = null,
    ) {
        if (!$this->businessProfile?->id) {
            $this->businessProfile = WhatsappBusinessProfile::first();
        }
    }

    public function buildCatalog(?WhatsappContact $contact = null, ?int $categoryId = null): array
    {
        $step = $this->getProductsStep();

        if (!$step || !$step->is_enabled) {
            return $this->disabledPayload($step, $contact);
        }

        $vars = $this->variables($contact);
        $type = $step->getInteractiveType();

        if ($type === 'text') {
            return WhatsappMessagePayload::text($step->renderMessage($vars));
        }

        if (in_array($type, ['button', 'cta_url'], true)) {
            return $this->payloadBuilder->build($step, $vars);
        }

        // El catálogo DPIKEOS se navega con listas (categorías y productos).
        // Aunque el administrador haya seleccionado "WhatsApp Flow" en este
        // paso, el Flow publicado se reserva para el botón "Pedido rápido" de
        // cada producto. Así se evita solicitar un flow_id general y se
        // mantiene la compra corta y ordenada.
        if ($type === 'flow') {
            $type = 'list';
        }

        $source = $step->config['catalog_source'] ?? 'products';

        if ($source === 'manual') {
            return $this->payloadBuilder->build($step, $vars);
        }

        if ($source === 'categories' && $categoryId === null) {
            return $this->buildCategoryListPayload($step, $vars);
        }

        return $this->buildProductListPayload($step, $vars, $categoryId);
    }

    /**
     * Abre el explorador por categorías sin depender de la configuración
     * temporal del paso (por ejemplo, cuando el panel está en modo "productos").
     * Es la salida limpia para "Ver categorías" y evita mostrar SKUs al cliente.
     */
    public function buildCategoryBrowser(?WhatsappContact $contact = null): array
    {
        $step = $this->getProductsStep();

        if (!$step || !$step->is_enabled) {
            return $this->disabledPayload($step, $contact);
        }

        return $this->buildCategoryListPayload($step, $this->variables($contact));
    }

    protected function getProductsStep(): ?MarketingFlowStep
    {
        if (!$this->businessProfile) {
            return null;
        }

        $flow = MarketingFlow::query()
            ->where('business_profile_id', $this->businessProfile->id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->with('steps')
            ->first();

        return $flow?->steps->firstWhere('step_key', MarketingStepKey::PRODUCTS_MENU);
    }

    protected function variables(?WhatsappContact $contact): array
    {
        $menu = $this->getCatalogMenu();
        $categories = $menu
            ? $this->countCategoriesWithProducts($menu)
            : 0;
        $products = $menu
            ? $this->countVisibleProducts($menu)
            : 0;

        $meta = is_array($this->businessProfile?->metadata) ? $this->businessProfile->metadata : [];

        return [
            'nombre' => $contact?->name ?? 'Cliente',
            'nombre_bot' => WhatsappChatbotConfig::where('business_profile_id', $this->businessProfile?->id)->first()?->bot_name
                ?: 'Asistente virtual',
            'nombre_empresa' => $this->businessProfile?->business_name ?? 'Tienda',
            'telefono_soporte' => $meta['whatsapp']
                ?? $this->businessProfile?->phone_number
                ?? config('whatsapp.demo_whatsapp_number', ''),
            'horario_atencion' => $meta['business_hours'] ?? 'Lunes a viernes 9:00 - 18:00',
            'total_productos' => (string) $products,
            'total_categorias' => (string) $categories,
            'total' => '0.00',
            'moneda' => 'USD',
            'cantidad_items' => '0',
            'numero_pedido' => '-',
            'estado_pedido' => '-',
        ];
    }

    protected function disabledPayload(?MarketingFlowStep $step, ?WhatsappContact $contact): array
    {
        $message = $step?->message_template
            ? MarketingFlowStep::interpolate($step->message_template, $this->variables($contact))
            : 'Lo siento, el catálogo de productos no está disponible en este momento.';

        return WhatsappMessagePayload::text($message);
    }

    protected function buildCategoryListPayload(MarketingFlowStep $step, array $vars): array
    {
        $menu = $this->getCatalogMenu();
        if (!$menu) {
            return WhatsappMessagePayload::text('Lo siento, el catálogo no está configurado.');
        }

        $menuItems = $this->visibleCatalogCategoriesQuery($menu)->get();
        if ($menuItems->isEmpty()) {
            return WhatsappMessagePayload::text('No hay categorías de productos disponibles.');
        }

        $rows = [];
        foreach ($menuItems as $menuItem) {
            $activeCount = $this->demoCliente->applyProductScope(
                $menuItem->prices()->where('is_active', true)->where('stock', '>', 0)
            )->count();

            $rows[] = [
                'id' => 'cat_' . $menuItem->id,
                'title' => Str::limit($menuItem->title, 24, ''),
                'description' => Str::limit($menuItem->description ?: "{$activeCount} producto(s)", 72, ''),
            ];
        }

        if ($rows === []) {
            return WhatsappMessagePayload::text('No hay productos activos en el catálogo.');
        }

        $sections = [['title' => 'Categorías', 'rows' => array_slice($rows, 0, 10)]];
        $sections = $this->appendConfiguredSections($step, $sections, $vars);

        return $this->composeListPayload($step, $vars, $sections);
    }

    protected function buildProductListPayload(MarketingFlowStep $step, array $vars, ?int $categoryId = null): array
    {
        $menu = $this->getCatalogMenu();
        if (!$menu) {
            return WhatsappMessagePayload::text('Lo siento, el catálogo no está configurado.');
        }

        $maxRows = max(1, min(8, (int) ($step->config['max_product_rows'] ?? 8)));
        $includeNavigation = $step->config['include_navigation'] ?? true;

        $productCountInScope = null;
        if ($categoryId) {
            $categoryItem = $this->visibleCatalogCategoriesQuery($menu)
                ->where('id', $categoryId)
                ->first();
            if ($categoryItem) {
                $productCountInScope = $this->demoCliente->applyProductScope(
                    $categoryItem->prices()->where('is_active', true)->where('stock', '>', 0)
                )->count();
            }
        }

        $needsVerMas = $includeNavigation && $categoryId && ($productCountInScope ?? 0) > $maxRows;
        $navigationSlots = $includeNavigation
            ? (($categoryId ? 2 : 2) + ($needsVerMas ? 1 : 0))
            : 0;
        $effectiveMaxRows = min($maxRows, max(1, 10 - $navigationSlots));

        $menuItemsQuery = $this->visibleCatalogCategoriesQuery($menu)->orderBy('order');

        if ($categoryId) {
            $menuItemsQuery->where('id', $categoryId);
        }

        $menuItems = $menuItemsQuery->get();

        if ($menuItems->isEmpty()) {
            if ($categoryId) {
                return WhatsappMessagePayload::text(
                    "No encontramos productos activos en esta categoría.\n\nUse *Catálogo* en el menú principal para ver otras líneas."
                );
            }

            return WhatsappMessagePayload::text('No hay productos disponibles en esta categoría.');
        }

        $sections = [];
        $totalRows = 0;

        foreach ($menuItems as $menuItem) {
            $prices = $this->demoCliente->applyProductScope(
                $menuItem->prices()->where('is_active', true)->where('stock', '>', 0)
            )
                ->orderBy('name')
                ->get();

            if ($prices->isEmpty()) {
                continue;
            }

            $rows = [];
            foreach ($prices as $price) {
                $rows[] = $this->formatProductRow($price);
                $totalRows++;
                if ($totalRows >= $effectiveMaxRows) {
                    break;
                }
            }

            if ($rows !== []) {
                $sections[] = [
                    'title' => Str::limit($menuItem->title, 24, ''),
                    'rows' => $rows,
                ];
            }

            if ($totalRows >= $effectiveMaxRows) {
                break;
            }
        }

        if ($sections === []) {
            if ($categoryId) {
                $categoryTitle = $menuItems->first()?->title ?? 'esta categoría';

                return WhatsappMessagePayload::text(
                    "No hay productos activos en *{$categoryTitle}* en este momento."
                );
            }

            return WhatsappMessagePayload::text('No hay productos activos en el catálogo.');
        }

        $sections = $this->appendConfiguredSections(
            $step,
            $sections,
            $vars,
            $categoryId,
            $productCountInScope,
            $totalRows
        );

        if ($categoryId) {
            $category = $menuItems->first();
            $productCount = $productCountInScope ?? $this->demoCliente->applyProductScope(
                $category->prices()->where('is_active', true)->where('stock', '>', 0)
            )->count();

            return $this->composeListPayload(
                $step,
                $vars,
                $sections,
                $this->buildCategoryProductsBody($category, $productCount, $totalRows, $effectiveMaxRows),
                'Ver productos',
                [
                    // Las listas interactivas de la Cloud API solo admiten
                    // encabezado de texto. Una imagen aquí hace que Meta
                    // rechace todo el menú de productos (error 131009).
                    'type' => 'text',
                    'text' => Str::limit($category->title, 60, ''),
                ]
            );
        }

        return $this->composeListPayload($step, $vars, $sections);
    }

    protected function buildCategoryProductsBody(
        WhatsappMenuItem $category,
        int $productCount,
        int $shownCount,
        int $maxRows
    ): string {
        // El título ya aparece como encabezado nativo de WhatsApp. Repetirlo
        // en el cuerpo hace que la ficha se vea amateur y desperdicia espacio.
        $description = trim((string) $category->description);
        $body = $description !== '' ? $description . "\n\n" : '';

        if ($productCount > $shownCount && $shownCount >= $maxRows) {
            $body .= "Mostrando {$shownCount} de {$productCount} opciones.\n\n";
        }

        $body .= 'Elige una opción para personalizar tu pedido.';

        return $body;
    }

    protected function getCatalogMenu(): ?WhatsappMenu
    {
        if ($this->catalogMenuCache === null) {
            $this->catalogMenuCache = WhatsappMenu::where('action_id', 'prices_menu')
                ->where('business_profile_id', $this->businessProfile?->id)
                ->first();
        }

        return $this->catalogMenuCache;
    }

    protected function exampleSkuForCategory(WhatsappMenuItem $category): ?string
    {
        $price = $this->demoCliente->applyProductScope(
            $category->prices()->where('is_active', true)->where('stock', '>', 0)->orderBy('sku')
        )->first();

        return $price?->sku;
    }

    protected function visibleCatalogCategoriesQuery(WhatsappMenu $menu)
    {
        return $this->demoCliente->scopeCategoriesWithVisibleProducts(
            $this->demoCliente->applyCategoryScope(
                $menu->items()->where('is_active', true)
            )
        )->orderBy('order');
    }

    protected function countCategoriesWithProducts(WhatsappMenu $menu): int
    {
        return $this->visibleCatalogCategoriesQuery($menu)->count();
    }

    protected function countVisibleProducts(WhatsappMenu $menu): int
    {
        return $this->visibleCatalogCategoriesQuery($menu)
            ->get()
            ->sum(fn (WhatsappMenuItem $item) => $this->demoCliente->applyProductScope(
                $item->prices()->where('is_active', true)->where('stock', '>', 0)
            )->count());
    }

    protected function formatProductRow($price): array
    {
        $title = Str::limit($price->name, 24, '');
        $priceText = $price->is_promo
            ? '💰 $' . number_format($price->promo_price, 2) . ' (Oferta)'
            : '💰 $' . number_format($price->price, 2);

        $maxDescLength = max(10, 72 - mb_strlen($priceText) - 3);
        $description = Str::limit($price->description ?? '', $maxDescLength, '...');
        $fullDescription = $description !== ''
            ? $priceText . ' - ' . $description
            : $priceText;

        if (mb_strlen($fullDescription) > 72) {
            $fullDescription = Str::limit($fullDescription, 72, '...');
        }

        return [
            'id' => (string) $price->id,
            'title' => $title,
            'description' => $fullDescription,
        ];
    }

    protected function appendConfiguredSections(
        MarketingFlowStep $step,
        array $sections,
        array $vars,
        ?int $categoryId = null,
        ?int $productCount = null,
        int $shownCount = 0,
    ): array {
        foreach ($step->getListConfig()['sections'] ?? [] as $section) {
            $rows = [];
            foreach ($section['rows'] ?? [] as $row) {
                $rows[] = [
                    'id' => (string) ($row['id'] ?? ''),
                    'title' => Str::limit((string) ($row['title'] ?? ''), 24, ''),
                    'description' => !empty($row['description'])
                        ? Str::limit((string) $row['description'], 72, '')
                        : null,
                ];
            }
            if ($rows !== []) {
                $sections[] = [
                    'title' => Str::limit((string) ($section['title'] ?? 'Opciones'), 24, ''),
                    'rows' => $rows,
                ];
            }
        }

        if ($step->config['include_navigation'] ?? true) {
            $navigationRows = [];

            if ($categoryId && $productCount !== null && $productCount > $shownCount) {
                $remaining = $productCount - $shownCount;
                $navigationRows[] = [
                    'id' => 'ver_mas_cat_' . $categoryId . '_' . $shownCount,
                    'title' => 'Ver más productos',
                    'description' => $remaining . ' opciones más',
                ];
            } elseif (!$categoryId) {
                $navigationRows[] = [
                    'id' => 'ver_mas_precios',
                    'title' => 'Ver categorías',
                    'description' => 'Explora el menú por categoría',
                ];
            }

            if ($categoryId) {
                $navigationRows[] = [
                    'id' => 'volver_categorias',
                    'title' => 'Volver a categorías',
                    'description' => 'Ver otras líneas de producto',
                ];
            }

            $navigationRows[] = [
                'id' => 'menu_principal',
                'title' => 'Menú principal',
                'description' => 'Volver al inicio',
            ];

            $sections[] = [
                'title' => 'Navegación',
                'rows' => $navigationRows,
            ];
        }

        $rowCount = 0;
        $trimmed = [];
        foreach ($sections as $section) {
            $rows = [];
            foreach ($section['rows'] ?? [] as $row) {
                if ($rowCount >= 10) {
                    break;
                }
                $rows[] = $row;
                $rowCount++;
            }
            if ($rows !== []) {
                $trimmed[] = ['title' => $section['title'], 'rows' => $rows];
            }
            if ($rowCount >= 10) {
                break;
            }
        }

        return $trimmed;
    }

    protected function composeListPayload(
        MarketingFlowStep $step,
        array $vars,
        array $sections,
        ?string $bodyOverride = null,
        ?string $listButtonOverride = null,
        ?array $headerOverride = null,
    ): array {
        $catalogMenu = $this->getCatalogMenu();
        $body = $bodyOverride ?? $step->renderMessage($vars);
        if ($body === '') {
            $body = trim((string) ($catalogMenu?->content ?? ''));
        }
        if ($body === '') {
            $empresa = $vars['nombre_empresa'] ?? 'Catálogo';
            $body = "*Catálogo de productos*\n*{$empresa}*\n\nSeleccione una categoría o escriba el código SKU del producto.";
        }

        $listButton = $listButtonOverride
            ?? ($step->getListConfig()['button'] ?? null)
            ?? ($catalogMenu?->button_text ?? null)
            ?? 'Ver categorías';
        $header = $headerOverride ?? $step->getRenderedHeader($vars);
        $footer = $step->getRenderedFooter($vars);

        if ($headerOverride === null && $step->getHeaderMode() === 'default') {
            $header = [
                'type' => 'text',
                'text' => $vars['nombre_empresa'] ?? 'Catálogo',
            ];
        }

        return WhatsappMessagePayload::list($body, $listButton, $sections, $header, $footer);
    }
}
