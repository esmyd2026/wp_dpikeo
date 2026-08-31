<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Models\WhatsappChatbotConfig;
use App\Models\Franchise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Services\DemoClienteService;
use App\Services\ProductImageService;

class ChatbotController extends Controller
{
    public function __construct(
        private readonly DemoClienteService $demoCliente,
        private readonly ProductImageService $categoryImages,
    ) {}
    /**
     * Gestión de categorías del catálogo (items del menú prices_menu).
     */
    public function menus()
    {
        $categories = WhatsappMenuItem::catalogCategories()
            ->with('franchise:id,name,slug')
            ->withCount([
                'prices',
                'prices as active_prices_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('order')
            ->orderBy('title')
            ->get();

        $productStats = WhatsappPrice::summaryStats();

        $stats = [
            'total' => $categories->count(),
            'active' => $categories->where('is_active', true)->count(),
            'with_products' => $categories->where('prices_count', '>', 0)->count(),
            'empty' => $categories->where('prices_count', 0)->count(),
            'products_total' => $productStats['total'],
            'products_active' => $productStats['active'],
            'products_unassigned' => $productStats['total'] - WhatsappPrice::inCatalogCategoriesCount(),
        ];

        return view('admin.menus.index', compact('categories', 'stats') + [
            'demoClienteOptions' => $this->demoCliente->options(),
            'activeDemoCliente' => $this->demoCliente->activeKey(),
            'franchises' => Franchise::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    /**
     * Devuelve una categoría en JSON (edición AJAX).
     */
    public function showMenuItem(WhatsappMenuItem $item)
    {
        $this->ensureCatalogCategory($item);

        return response()->json($this->formatCategory($item->loadCount('prices')));
    }

    /**
     * Muestra la vista de gestión de productos
     */
    public function products()
    {
        $products = WhatsappPrice::with('category')->orderBy('sku')->get();
        $categories = WhatsappMenu::where('type', 'category')->get();
        return view('admin.products.index', compact('products', 'categories'));
    }

    /**
     * Muestra la vista de configuración del chatbot
     */
    public function config()
    {
        $config = WhatsappChatbotConfig::first();
        $messageTemplates = MessageTemplate::orderBy('name')->get();
        $businessProfile = \App\Models\WhatsappBusinessProfile::first();

        return view('admin.chatbot.config', compact('config', 'messageTemplates', 'businessProfile'));
    }

    /**
     * Actualiza el texto de un mensaje automático (cambio de estado, costo
     * de envío, etc.). No cubre el flujo conversacional del bot, ese se edita
     * desde el editor de flujo de marketing.
     */
    public function updateMessageTemplate(Request $request, MessageTemplate $messageTemplate)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $messageTemplate->update(['body' => $validated['body']]);

        return redirect()->back()->with('success', "Mensaje «{$messageTemplate->name}» actualizado correctamente");
    }

    /**
     * Actualiza la configuración del chatbot
     */
    public function updateConfig(Request $request)
    {
        $validated = $request->validate([
            'bot_name' => 'nullable|string|max:255',
            'welcome_message' => 'nullable|string',
            'fallback_message' => 'nullable|string',
            'response_delay' => 'nullable|integer|min:0|max:10000',
            'abandoned_cart_timeout_minutes' => 'nullable|integer|min:5|max:10080',
            'iva_enabled' => 'nullable|boolean',
            'iva_percentage' => 'nullable|numeric|min:0|max:100',
            'bank_transfer_instructions' => 'nullable|string|max:1500',
            'delivery_dispatch_keyword' => 'nullable|string|max:30',
            'delivery_dispatch_numbers' => 'nullable|string|max:500',
            'privacy_notice_enabled' => 'nullable|boolean',
            'privacy_notice_text' => 'nullable|string|max:1024',
            'privacy_notice_link' => 'nullable|url|max:500',
            'primary_color' => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
            'bot_avatar' => 'nullable|string|max:500',
            'bot_avatar_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'remove_bot_avatar' => 'nullable|boolean',
            'font_family' => 'nullable|string|max:50',
            'monitoring_phone_number' => 'nullable|string|max:20',
            'monitoring_email' => 'nullable|email|max:255',
            'landing_accent_color' => 'nullable|string|max:20',
            'landing_logo_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'remove_landing_logo' => 'nullable|boolean',
            'landing_hero_eyebrow' => 'nullable|string|max:80',
            'landing_hero_title' => 'nullable|string|max:160',
            'landing_hero_title_highlight' => 'nullable|string|max:80',
            'landing_hero_subtitle' => 'nullable|string|max:400',
            'landing_order_cta_label' => 'nullable|string|max:60',
            'landing_order_online_title' => 'nullable|string|max:120',
            'landing_order_online_text' => 'nullable|string|max:400',
            'landing_order_online_cta_label' => 'nullable|string|max:60',
            'landing_step_1_title' => 'nullable|string|max:80',
            'landing_step_1_text' => 'nullable|string|max:300',
            'landing_step_2_title' => 'nullable|string|max:80',
            'landing_step_2_text' => 'nullable|string|max:300',
            'landing_step_3_title' => 'nullable|string|max:80',
            'landing_step_3_text' => 'nullable|string|max:300',
            'landing_cta_band_title' => 'nullable|string|max:120',
            'landing_cta_band_subtitle' => 'nullable|string|max:300',
            'landing_phone_msg_1' => 'nullable|string|max:200',
            'landing_phone_msg_2' => 'nullable|string|max:200',
            'landing_phone_msg_3' => 'nullable|string|max:200',
            'landing_phone_btn_1' => 'nullable|string|max:60',
            'landing_phone_btn_2' => 'nullable|string|max:60',
            'landing_phone_msg_4' => 'nullable|string|max:200',
        ]);

        $config = WhatsappChatbotConfig::first();
        if (!$config) {
            $config = new WhatsappChatbotConfig();
            $businessProfile = \App\Models\WhatsappBusinessProfile::first();
            if ($businessProfile) {
                $config->business_profile_id = $businessProfile->id;
            }
        }

        $metadata = $config->metadata ?? [];
        $metadata['bot_name'] = $validated['bot_name'] ?? null;
        $metadata['response_delay'] = $validated['response_delay'] ?? 1000;
        $metadata['abandoned_cart_timeout_minutes'] = $validated['abandoned_cart_timeout_minutes'] ?? null;
        $metadata['iva_enabled'] = $request->boolean('iva_enabled');
        $metadata['iva_percentage'] = $validated['iva_percentage'] ?? 0;
        $metadata['bank_transfer_instructions'] = trim((string) ($validated['bank_transfer_instructions'] ?? '')) ?: null;
        $metadata['delivery_dispatch_keyword'] = trim((string) ($validated['delivery_dispatch_keyword'] ?? '')) ?: '2501';
        $metadata['delivery_dispatch_numbers'] = trim((string) ($validated['delivery_dispatch_numbers'] ?? '')) ?: null;
        $metadata['privacy_notice_enabled'] = $request->boolean('privacy_notice_enabled');
        $metadata['privacy_notice_text'] = $validated['privacy_notice_text'] ?? null;
        $metadata['privacy_notice_link'] = $validated['privacy_notice_link'] ?? url('/privacidad');
        $metadata['primary_color'] = WhatsappChatbotConfig::normalizeHexColor(
            $validated['primary_color'] ?? null,
            '#005c4b'
        );
        $metadata['secondary_color'] = WhatsappChatbotConfig::normalizeHexColor(
            $validated['secondary_color'] ?? null,
            '#075e54'
        );
        $metadata['bot_avatar'] = $validated['bot_avatar'] ?? null;
        $metadata['font_family'] = $validated['font_family'] ?? 'Arial';

        if ($request->boolean('remove_bot_avatar')) {
            $this->deleteBotAvatarFile($metadata['bot_avatar_path'] ?? null);
            unset($metadata['bot_avatar'], $metadata['bot_avatar_path']);
        } elseif ($request->hasFile('bot_avatar_image')) {
            $this->deleteBotAvatarFile($metadata['bot_avatar_path'] ?? null);
            $profileId = $config->business_profile_id ?? \App\Models\WhatsappBusinessProfile::first()?->id ?? 'default';
            $metadata['bot_avatar_path'] = $request->file('bot_avatar_image')
                ->store("chatbot-avatars/{$profileId}", 'public');
        }

        // Contenido de la página de inicio pública (/), ver LandingController.
        $landing = $metadata['landing'] ?? [];
        $landing['accent_color'] = WhatsappChatbotConfig::normalizeHexColor(
            $validated['landing_accent_color'] ?? null,
            \App\Http\Controllers\LandingController::DEFAULTS['accent_color']
        );
        foreach (\App\Http\Controllers\LandingController::DEFAULTS as $key => $default) {
            if (in_array($key, ['accent_color', 'logo_path'], true)) {
                continue;
            }
            $landing[$key] = $validated['landing_'.$key] ?? $default;
        }

        if ($request->boolean('remove_landing_logo')) {
            $this->deleteBotAvatarFile($landing['logo_path'] ?? null);
            $landing['logo_path'] = null;
        } elseif ($request->hasFile('landing_logo_image')) {
            $this->deleteBotAvatarFile($landing['logo_path'] ?? null);
            $profileId = $config->business_profile_id ?? \App\Models\WhatsappBusinessProfile::first()?->id ?? 'default';
            $landing['logo_path'] = $request->file('landing_logo_image')
                ->store("landing/{$profileId}", 'public');
        }
        $metadata['landing'] = $landing;

        $config->metadata = $metadata;
        $config->welcome_message = $validated['welcome_message'] ?? $config->welcome_message;
        $config->default_response = $validated['fallback_message'] ?? $config->default_response;
        $config->monitoring_phone_number = $validated['monitoring_phone_number'] ?? null;
        $config->monitoring_email = $validated['monitoring_email'] ?? null;
        $config->monitoring_enabled = $request->has('monitoring_enabled')
            && $request->input('monitoring_enabled') == '1';
        $config->save();

        return redirect()->back()->with('success', 'Configuración actualizada correctamente');
    }

    /**
     * Guarda las credenciales de WhatsApp Cloud API (Meta) que usa el bot
     * para enviar y recibir mensajes reales. Estos datos viven en la tabla
     * whatsapp_business_profiles, no en el .env — por eso necesitan este
     * formulario en vez de solo variables de entorno.
     */
    public function updateWhatsappCredentials(Request $request)
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:30',
            'phone_number_id' => 'required|string|max:60',
            'whatsapp_business_id' => 'nullable|string|max:60',
            'access_token' => 'nullable|string|max:1000',
        ], [
            'phone_number.required' => 'El número de WhatsApp es obligatorio.',
            'phone_number_id.required' => 'El Phone Number ID es obligatorio.',
        ]);

        $profile = \App\Models\WhatsappBusinessProfile::first();
        if (!$profile) {
            $profile = new \App\Models\WhatsappBusinessProfile([
                'business_name' => 'DPIKEOS',
                'display_name' => 'DPIKEOS',
                'status' => 'active',
            ]);
        }

        $profile->phone_number = $validated['phone_number'];
        $profile->phone_number_id = $validated['phone_number_id'];
        $profile->whatsapp_business_id = $validated['whatsapp_business_id'] ?: null;

        // El token no se muestra en el formulario por seguridad. Si el campo
        // llega vacío, se asume que el usuario no quiso cambiarlo y se deja
        // el que ya estaba guardado.
        if (!empty($validated['access_token'])) {
            $profile->access_token = $validated['access_token'];
        }

        $profile->save();

        return redirect()->route('admin.chatbot.config')
            ->with('success', 'Credenciales de WhatsApp guardadas correctamente.');
    }

    protected function deleteBotAvatarFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Crea un nuevo menú
     */
    public function storeMenu(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:button,list,text',
            'content' => 'required|string',
            'button_text' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:50',
            'action_id' => 'required|string|max:50',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $menu = new WhatsappMenu($validated);
        $menu->save();

        return redirect()->back()->with('success', 'Menú creado correctamente');
    }

    /**
     * Actualiza un menú existente
     */
    public function updateMenu(Request $request, WhatsappMenu $menu)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:button,list,text',
            'content' => 'required|string',
            'button_text' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:50',
            'action_id' => 'required|string|max:50',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $menu->update($validated);

        return redirect()->back()->with('success', 'Menú actualizado correctamente');
    }

    /**
     * Elimina un menú
     */
    public function deleteMenu(WhatsappMenu $menu)
    {
        DB::transaction(function () use ($menu) {
            // Eliminar items asociados
            $menu->items()->delete();
            // Eliminar menú
            $menu->delete();
        });

        return response()->json(['message' => 'Menú eliminado correctamente']);
    }

    /**
     * Crea un nuevo producto
     */
    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:50|unique:whatsapp_prices,sku',
            'name' => 'required|string|max:255',
            'menu_item_id' => 'required|exists:whatsapp_menus,id',
            'price' => 'required|numeric|min:0',
            'promo_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'benefits' => 'nullable|string',
            'nutritional_info' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $product = new WhatsappPrice($validated);
        $product->save();

        return redirect()->back()->with('success', 'Producto creado correctamente');
    }

    /**
     * Actualiza un producto existente
     */
    public function updateProduct(Request $request, WhatsappPrice $product)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:50|unique:whatsapp_prices,sku,' . $product->id,
            'name' => 'required|string|max:255',
            'menu_item_id' => 'required|exists:whatsapp_menus,id',
            'price' => 'required|numeric|min:0',
            'promo_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'benefits' => 'nullable|string',
            'nutritional_info' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $product->update($validated);

        return redirect()->back()->with('success', 'Producto actualizado correctamente');
    }

    /**
     * Elimina un producto
     */
    public function deleteProduct(WhatsappPrice $product)
    {
        $product->delete();
        return response()->json(['message' => 'Producto eliminado correctamente']);
    }

    /**
     * Crea una categoría del catálogo.
     */
    public function storeMenuItem(Request $request)
    {
        $pricesMenu = $this->getPricesMenu();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:50',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'franchise_id' => 'required|integer|exists:franchises,id',
        ]);

        $title = trim($validated['title']);
        $order = $validated['order'] ?? ((int) WhatsappMenuItem::catalogCategories()->max('order') + 1);

        $franchise = Franchise::query()->whereKey($validated['franchise_id'])->where('is_active', true)->firstOrFail();
        $item = WhatsappMenuItem::create([
            'menu_id' => $pricesMenu->id,
            'franchise_id' => $franchise->id,
            'title' => $title,
            'description' => $validated['description'] ?? null,
            'action_id' => $this->makeUniqueActionId($pricesMenu->id, $title),
            'icon' => $validated['icon'] ?? '📦',
            'image' => $request->hasFile('image')
                ? $this->categoryImages->store($request->file('image'), null, 'category-images')
                : null,
            'order' => $order,
            'is_active' => $request->boolean('is_active', true),
            'demo_cliente' => $franchise->slug,
        ]);

        return response()->json([
            'message' => 'Categoría creada correctamente',
            'category' => $this->formatCategory($item->loadCount('prices')),
        ]);
    }

    /**
     * Actualiza una categoría del catálogo.
     */
    public function updateMenuItem(Request $request, WhatsappMenuItem $item)
    {
        $this->ensureCatalogCategory($item);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:50',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'remove_image' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'franchise_id' => 'required|integer|exists:franchises,id',
        ]);

        $title = trim($validated['title']);

        $franchise = Franchise::query()->whereKey($validated['franchise_id'])->where('is_active', true)->firstOrFail();

        $imagePath = $item->image;
        if ($request->boolean('remove_image')) {
            $this->categoryImages->delete($imagePath);
            $imagePath = null;
        } elseif ($request->hasFile('image')) {
            $imagePath = $this->categoryImages->store($request->file('image'), $item->image, 'category-images');
        }

        $item->update([
            'franchise_id' => $franchise->id,
            'title' => $title,
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? $item->icon ?? '📦',
            'image' => $imagePath,
            'order' => $validated['order'] ?? $item->order,
            'is_active' => $request->boolean('is_active'),
            'demo_cliente' => $franchise->slug,
        ]);

        WhatsappPrice::where('menu_item_id', $item->id)->update([
            'category' => $title,
            'franchise_id' => $franchise->id,
            'demo_cliente' => $franchise->slug,
        ]);

        return response()->json([
            'message' => 'Categoría actualizada correctamente',
            'category' => $this->formatCategory($item->fresh()->loadCount('prices')),
        ]);
    }

    /**
     * Activa o desactiva categorías del catálogo en lote.
     */
    public function bulkUpdateMenuItemsStatus(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:whatsapp_menu_items,id',
            'is_active' => 'required',
        ]);

        $pricesMenu = $this->getPricesMenu();
        $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isActive === null) {
            return response()->json(['message' => 'Estado inválido.'], 422);
        }

        $updated = WhatsappMenuItem::query()
            ->where('menu_id', $pricesMenu->id)
            ->whereIn('id', $validated['ids'])
            ->update(['is_active' => $isActive]);

        return response()->json([
            'message' => $isActive
                ? "{$updated} categoría(s) activada(s) correctamente."
                : "{$updated} categoría(s) desactivada(s) correctamente.",
            'updated' => $updated,
        ]);
    }

    /**
     * Elimina una categoría (solo si no tiene productos).
     */
    public function deleteMenuItem(WhatsappMenuItem $item)
    {
        $this->ensureCatalogCategory($item);

        if ($item->prices()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: la categoría tiene productos asociados. Desactívala o mueve los productos primero.',
            ], 422);
        }

        $item->delete();

        return response()->json(['message' => 'Categoría eliminada correctamente']);
    }

    private function getPricesMenu(): WhatsappMenu
    {
        $menu = WhatsappMenu::where('action_id', 'prices_menu')->first();

        if (!$menu) {
            abort(500, 'No está configurado el menú de catálogo (prices_menu).');
        }

        return $menu;
    }

    private function ensureCatalogCategory(WhatsappMenuItem $item): void
    {
        $pricesMenu = $this->getPricesMenu();

        if ((int) $item->menu_id !== (int) $pricesMenu->id) {
            abort(404);
        }
    }

    private function makeUniqueActionId(int $menuId, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title, '_') ?: 'categoria';
        $base = Str::limit($base, 40, '');
        $actionId = $base;
        $suffix = 1;

        while (
            WhatsappMenuItem::where('menu_id', $menuId)
                ->where('action_id', $actionId)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $actionId = $base . '_' . $suffix;
            $suffix++;
        }

        return $actionId;
    }

    private function formatCategory(WhatsappMenuItem $item): array
    {
        if (!isset($item->prices_count)) {
            $item->loadCount([
                'prices',
                'prices as active_prices_count' => fn ($query) => $query->where('is_active', true),
            ]);
        }

        return [
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'icon' => $item->icon,
            'image_url' => $item->image_url,
            'order' => $item->order,
            'is_active' => (bool) $item->is_active,
            'demo_cliente' => $item->demo_cliente,
            'franchise_id' => $item->franchise_id,
            'action_id' => $item->action_id,
            'products_count' => (int) ($item->prices_count ?? 0),
            'active_products_count' => (int) ($item->active_prices_count ?? 0),
        ];
    }
}
