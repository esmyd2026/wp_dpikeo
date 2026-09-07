<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminBulkOrderController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MarketingCampaignController;
use App\Http\Controllers\Admin\MessageFailuresController;
use App\Http\Controllers\Admin\OrdersReportsController;
use App\Http\Controllers\Admin\WhatsappReportsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\WhatsappTemplateController;

Route::get('/', [App\Http\Controllers\LandingController::class, 'index'])->name('home');
Route::get('/pedir', [App\Http\Controllers\LandingController::class, 'startOrder'])->name('landing.start-order');
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');

Route::get('/privacidad', [App\Http\Controllers\LegalController::class, 'privacy'])->name('legal.privacy');

// Product photos are stored on Laravel's public disk. This fallback keeps
// them available even though public/storage also contains brand assets.
Route::get('/storage/product-images/{filename}', [App\Http\Controllers\ProductImageController::class, 'show'])
    ->where('filename', '[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)')
    ->name('product-images.show');

Route::prefix('pedido')->name('bulk-order.')->group(function () {
    Route::get('/{token}', [App\Http\Controllers\BulkOrderController::class, 'show'])->name('show');
    Route::get('/{token}/catalogo', [App\Http\Controllers\BulkOrderController::class, 'catalog'])->name('catalog');
    Route::post('/{token}', [App\Http\Controllers\BulkOrderController::class, 'submit'])->name('submit');
});

// El repartidor confirma la entrega desde su propio celular, con un link
// público que se le manda por WhatsApp al despacharlo -- sin usuario del panel.
Route::prefix('entrega')->name('delivery-confirmation.')->group(function () {
    Route::get('/{token}', [App\Http\Controllers\DeliveryConfirmationController::class, 'show'])->name('show');
    Route::post('/{token}', [App\Http\Controllers\DeliveryConfirmationController::class, 'confirm'])->name('confirm');
});

Route::get('/orden/{order}/pdf', [App\Http\Controllers\OrderPdfController::class, 'downloadSigned'])
    ->name('order.pdf.signed')
    ->middleware('signed');

// Punto de venta: pantalla completa (mismo diseño del micrositio de WhatsApp)
// para tablets/equipos en tienda. Fuera del layout de administración, pero
// sigue exigiendo sesión de personal autorizado.
Route::prefix('punto-de-venta')->name('pos.')
    ->middleware(['auth', 'admin', 'permission:bulk_orders.create', 'platform.feature:orders'])
    ->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\PosOrderController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Admin\PosOrderController::class, 'submit'])->name('submit');
    });

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view,dashboard.menu')
        ->name('dashboard');

    Route::post('/empresa-activa', [App\Http\Controllers\Admin\ActiveCompanyController::class, 'store'])
        ->name('active-company.store');

    Route::get('/reports/whatsapp', [WhatsappReportsController::class, 'index'])
        ->middleware('permission:dashboard.view,dashboard.menu,whatsapp_reports.menu')
        ->name('reports.whatsapp');

    Route::get('/reports/orders', [OrdersReportsController::class, 'index'])
        ->middleware(['permission:orders.view,orders.menu,orders_reports.menu', 'platform.feature:orders'])
        ->name('reports.orders');
    Route::get('/reports/inventory', [App\Http\Controllers\Admin\InventoryReportController::class, 'index'])
        ->middleware('permission:products.view,products.menu,inventory.menu')
        ->name('reports.inventory');

    Route::get('/sucursales', [App\Http\Controllers\Admin\BusinessBranchController::class, 'index'])
        ->middleware('permission:pricing_settings.view,pricing_settings.menu,branches.menu')
        ->name('branches.index');
    Route::post('/sucursales', [App\Http\Controllers\Admin\BusinessBranchController::class, 'store'])
        ->middleware('permission:pricing_settings.update')
        ->name('branches.store');
    Route::put('/sucursales/{branch}', [App\Http\Controllers\Admin\BusinessBranchController::class, 'update'])
        ->middleware('permission:pricing_settings.update')
        ->name('branches.update');
    Route::delete('/sucursales/{branch}', [App\Http\Controllers\Admin\BusinessBranchController::class, 'destroy'])
        ->middleware('permission:pricing_settings.update')
        ->name('branches.destroy');
    Route::get('/franquicias', [App\Http\Controllers\Admin\FranchiseController::class, 'index'])
        ->middleware('permission:pricing_settings.view,pricing_settings.menu,franchises.menu')
        ->name('franchises.index');
    Route::post('/franquicias', [App\Http\Controllers\Admin\FranchiseController::class, 'store'])
        ->middleware('permission:pricing_settings.update')
        ->name('franchises.store');
    Route::put('/franquicias/{franchise}', [App\Http\Controllers\Admin\FranchiseController::class, 'update'])
        ->middleware('permission:pricing_settings.update')
        ->name('franchises.update');
    Route::delete('/franquicias/{franchise}', [App\Http\Controllers\Admin\FranchiseController::class, 'destroy'])
        ->middleware('permission:pricing_settings.update')
        ->name('franchises.destroy');

    Route::get('/orders', [App\Http\Controllers\AdminController::class, 'orders'])
        ->middleware(['permission:orders.view,orders.menu', 'platform.feature:orders'])
        ->name('orders');
    Route::get('/comandas', [App\Http\Controllers\Admin\KitchenBoardController::class, 'index'])
        ->middleware(['permission:orders.view,orders.menu,kitchen.menu', 'platform.feature:orders'])
        ->name('kitchen.index');
    Route::get('/comandas/pantalla', [App\Http\Controllers\Admin\KitchenBoardController::class, 'display'])
        ->middleware(['permission:orders.view,kitchen.menu', 'platform.feature:orders'])
        ->name('kitchen.display');
    Route::get('/comandas/datos', [App\Http\Controllers\Admin\KitchenBoardController::class, 'data'])
        ->middleware(['permission:orders.view,kitchen.menu', 'platform.feature:orders'])
        ->name('kitchen.data');
    Route::get('/comandas/{id}/imprimir', [App\Http\Controllers\Admin\KitchenBoardController::class, 'print'])
        ->middleware(['permission:orders.view,kitchen.menu', 'platform.feature:orders'])
        ->name('kitchen.print');
    Route::post('/comandas/turnos/reiniciar', [App\Http\Controllers\Admin\KitchenBoardController::class, 'resetTurns'])
        ->middleware(['permission:orders.update', 'platform.feature:orders'])
        ->name('kitchen.turns.reset');
    Route::post('/comandas/{id}/estado', [App\Http\Controllers\Admin\KitchenBoardController::class, 'transition'])
        ->middleware(['permission:orders.update', 'platform.feature:orders'])
        ->name('kitchen.transition');
    Route::get('/delivery', [App\Http\Controllers\Admin\DeliveryController::class, 'index'])
        ->middleware(['permission:orders.view,orders.menu,delivery.menu', 'platform.feature:orders'])
        ->name('delivery.index');
    Route::get('/delivery/datos', [App\Http\Controllers\Admin\DeliveryController::class, 'data'])
        ->middleware(['permission:orders.view,delivery.menu', 'platform.feature:orders'])
        ->name('delivery.data');
    Route::post('/delivery/{id}/confirmar', [App\Http\Controllers\Admin\DeliveryController::class, 'confirmDelivery'])
        ->middleware(['permission:orders.update', 'platform.feature:orders'])
        ->name('delivery.confirm');
    Route::get('/delivery/repartidores', [App\Http\Controllers\Admin\DeliveryController::class, 'drivers'])
        ->middleware(['permission:orders.view,delivery.menu', 'platform.feature:orders'])
        ->name('delivery.drivers');
    Route::post('/delivery/{id}/despachar', [App\Http\Controllers\Admin\DeliveryController::class, 'dispatchToDriver'])
        ->middleware(['permission:orders.update', 'platform.feature:orders'])
        ->name('delivery.dispatch');
    Route::get('/orders/export', [App\Http\Controllers\AdminController::class, 'exportOrders'])
        ->middleware(['permission:orders.view,orders.menu', 'platform.feature:orders'])
        ->name('orders.export');
    Route::get('/orders/poll', [App\Http\Controllers\AdminController::class, 'pollNewOrders'])
        ->middleware(['permission:orders.view,orders.menu', 'platform.feature:orders'])
        ->name('orders.poll');
    Route::get('/messages', [App\Http\Controllers\AdminController::class, 'messages'])
        ->middleware(['permission:chats.view,chats.menu', 'platform.feature:chat'])
        ->name('messages');
    Route::get('/orders/{id}/details', [App\Http\Controllers\AdminController::class, 'orderDetails'])
        ->middleware(['permission:orders.view,orders.menu', 'platform.feature:orders'])
        ->name('orders.details');
    Route::get('/orders/{id}/pdf', [App\Http\Controllers\OrderPdfController::class, 'downloadAdmin'])
        ->middleware(['permission:orders.view,orders.menu', 'platform.feature:orders'])
        ->name('orders.pdf');
    Route::get('/orders/{id}/payment-proof', [App\Http\Controllers\AdminController::class, 'orderPaymentProof'])
        ->middleware(['permission:orders.view,orders.menu', 'platform.feature:orders'])
        ->name('orders.payment-proof');
    Route::post('/orders/{id}/send-confirmation', [App\Http\Controllers\AdminController::class, 'sendOrderConfirmation'])
        ->middleware(['permission:orders.update', 'platform.feature:orders'])
        ->name('orders.send-confirmation');
    Route::post('/orders/{id}/fulfillment-costs', [App\Http\Controllers\AdminController::class, 'sendFulfillmentCosts'])
        ->middleware(['permission:orders.update', 'platform.feature:orders'])
        ->name('orders.fulfillment-costs');
    Route::put('/orders/{id}', [App\Http\Controllers\AdminController::class, 'updateOrder'])
        ->middleware(['permission:orders.update', 'platform.feature:orders'])
        ->name('orders.update');
    Route::post('/orders/{id}/notes', [App\Http\Controllers\AdminController::class, 'storeOrderNote'])
        ->middleware(['permission:orders.update', 'platform.feature:orders'])
        ->name('orders.notes.store');
    Route::delete('/orders/{id}', [App\Http\Controllers\AdminController::class, 'destroyOrder'])
        ->middleware(['permission:orders.delete', 'platform.feature:orders'])
        ->name('orders.destroy');
    Route::prefix('orders/bulk')->name('orders.bulk.')->middleware(['permission:bulk_orders.create', 'platform.feature:orders'])->group(function () {
    Route::get('/', [AdminBulkOrderController::class, 'create'])->name('create');
    Route::get('/contacts', [AdminBulkOrderController::class, 'searchContacts'])->name('contacts');
    Route::post('/contacts', [AdminBulkOrderController::class, 'storeContact'])->name('contacts.store');
        Route::get('/catalogo', [AdminBulkOrderController::class, 'catalog'])->name('catalog');
        Route::post('/', [AdminBulkOrderController::class, 'submit'])->name('submit');
    });
    Route::get('/chats', [App\Http\Controllers\AdminController::class, 'chats'])
        ->middleware(['permission:chats.view,chats.menu', 'platform.feature:chat'])
        ->name('chats');

    Route::get('/clients', [App\Http\Controllers\Admin\ClientController::class, 'index'])
        ->middleware('permission:clients.view,clients.menu')
        ->name('clients.index');
    Route::get('/clients/{client}', [App\Http\Controllers\Admin\ClientController::class, 'show'])
        ->middleware('permission:clients.detail,clients.view')
        ->name('clients.show');
    Route::put('/clients/{client}', [App\Http\Controllers\Admin\ClientController::class, 'update'])
        ->middleware('permission:clients.update')
        ->name('clients.update');
    Route::post('/clients/{client}/notes', [App\Http\Controllers\Admin\ClientController::class, 'storeNote'])
        ->middleware('permission:clients.notes')
        ->name('clients.notes.store');

    Route::get('/chats/{contact}', [App\Http\Controllers\AdminController::class, 'chat'])
        ->middleware(['permission:chats.open,chats.view', 'platform.feature:chat'])
        ->name('chat');
    Route::get('/chats/{contact}/messages', [App\Http\Controllers\AdminController::class, 'chat'])
        ->middleware(['permission:chats.open,chats.view', 'platform.feature:chat'])
        ->name('chat.messages');
    Route::get('/chats/{contact}/new-messages', [App\Http\Controllers\AdminController::class, 'getNewMessages'])
        ->middleware(['permission:chats.open,chats.view', 'platform.feature:chat'])
        ->name('chat.new-messages');
    Route::get('/chats/list/update', [App\Http\Controllers\AdminController::class, 'getContactsList'])
        ->middleware(['permission:chats.view,chats.menu', 'platform.feature:chat'])
        ->name('chat.contacts.update');
    Route::get('/agent-requests/poll', [App\Http\Controllers\AdminController::class, 'pollAgentRequests'])
        ->middleware(['permission:chats.view,chats.menu', 'platform.feature:chat'])
        ->name('agent-requests.poll');
    Route::post('/chats/send', [App\Http\Controllers\AdminController::class, 'sendMessage'])
        ->middleware(['permission:chats.send', 'platform.feature:chat'])
        ->name('chat.send');
    Route::post('/chats/typing', [App\Http\Controllers\AdminController::class, 'typingIndicator'])
        ->middleware(['permission:chats.send', 'platform.feature:chat'])
        ->name('chat.typing');
    Route::get('/messages/{message}/image', [App\Http\Controllers\AdminController::class, 'getImage'])
        ->middleware(['permission:chats.view,chats.open', 'platform.feature:chat'])
        ->name('message.image');
    Route::get('/contacts/{id}', [App\Http\Controllers\AdminController::class, 'contactDetails'])
        ->middleware(['permission:chats.view', 'platform.feature:chat']);
    Route::post('/contacts/{contact}/toggle-bot', [App\Http\Controllers\AdminController::class, 'toggleBot'])
        ->middleware(['permission:chats.toggle_bot', 'platform.feature:chat'])
        ->name('contact.toggle-bot');
    Route::post('/contacts/{contact}/dismiss-agent', [App\Http\Controllers\AdminController::class, 'dismissAgentRequest'])
        ->middleware(['permission:chats.send', 'platform.feature:chat'])
        ->name('contact.dismiss-agent');
    Route::post('/contacts/{contact}/reset-conversation', [App\Http\Controllers\AdminController::class, 'resetConversation'])
        ->middleware(['permission:chats.toggle_bot', 'platform.feature:chat'])
        ->name('contact.reset-conversation');

    Route::get('/menus', [App\Http\Controllers\Admin\ChatbotController::class, 'menus'])
        ->middleware('permission:menus.view,menus.menu')
        ->name('menus.index');
    Route::post('/menus', [App\Http\Controllers\Admin\ChatbotController::class, 'storeMenu'])
        ->middleware('permission:menus.update')
        ->name('menus.store');
    Route::get('/menus/{menu}', [App\Http\Controllers\Admin\ChatbotController::class, 'showMenu'])
        ->middleware('permission:menus.view')
        ->name('menus.show');
    Route::put('/menus/{menu}', [App\Http\Controllers\Admin\ChatbotController::class, 'updateMenu'])
        ->middleware('permission:menus.update')
        ->name('menus.update');
    Route::delete('/menus/{menu}', [App\Http\Controllers\Admin\ChatbotController::class, 'deleteMenu'])
        ->middleware('permission:menus.update')
        ->name('menus.delete');

    Route::post('/menu-items/bulk-status', [App\Http\Controllers\Admin\ChatbotController::class, 'bulkUpdateMenuItemsStatus'])
        ->middleware('permission:menus.update')
        ->name('menu-items.bulk-status');
    Route::post('/menu-items', [App\Http\Controllers\Admin\ChatbotController::class, 'storeMenuItem'])
        ->middleware('permission:menus.update')
        ->name('menu-items.store');
    Route::get('/menu-items/{item}', [App\Http\Controllers\Admin\ChatbotController::class, 'showMenuItem'])
        ->middleware('permission:menus.view')
        ->name('menu-items.show');
    Route::put('/menu-items/{item}', [App\Http\Controllers\Admin\ChatbotController::class, 'updateMenuItem'])
        ->middleware('permission:menus.update')
        ->name('menu-items.update');
    Route::delete('/menu-items/{item}', [App\Http\Controllers\Admin\ChatbotController::class, 'deleteMenuItem'])
        ->middleware('permission:menus.update')
        ->name('menu-items.delete');

    Route::get('/products', [App\Http\Controllers\Admin\ProductController::class, 'index'])
        ->middleware('permission:products.view,products.menu')
        ->name('products.index');
    Route::get('/products/import/template', [App\Http\Controllers\Admin\ProductController::class, 'downloadImportTemplate'])
        ->middleware('permission:products.update')
        ->name('products.import.template');
    Route::get('/products/export', [App\Http\Controllers\Admin\ProductController::class, 'exportCatalog'])
        ->middleware('permission:products.view')
        ->name('products.export');
    Route::post('/products/import', [App\Http\Controllers\Admin\ProductController::class, 'importCatalog'])
        ->middleware('permission:products.update')
        ->name('products.import');
    Route::post('/products/bulk-status', [App\Http\Controllers\Admin\ProductController::class, 'bulkUpdateStatus'])
        ->middleware('permission:products.update')
        ->name('products.bulk-status');
    Route::get('/products/create', [App\Http\Controllers\Admin\ProductController::class, 'create'])
        ->middleware('permission:products.update')
        ->name('products.create');
    Route::post('/products', [App\Http\Controllers\Admin\ProductController::class, 'store'])
        ->middleware('permission:products.update')
        ->name('products.store');
    Route::post('/products/{product}/duplicate', [App\Http\Controllers\Admin\ProductController::class, 'duplicate'])
        ->middleware('permission:products.update')
        ->name('products.duplicate');
    Route::get('/products/{product}', [App\Http\Controllers\Admin\ProductController::class, 'show'])
        ->middleware('permission:products.view')
        ->name('products.show');
    Route::get('/products/{product}/edit', [App\Http\Controllers\Admin\ProductController::class, 'edit'])
        ->middleware('permission:products.update')
        ->name('products.edit');
    Route::put('/products/{product}', [App\Http\Controllers\Admin\ProductController::class, 'update'])
        ->middleware('permission:products.update')
        ->name('products.update');
    Route::delete('/products/{product}', [App\Http\Controllers\Admin\ProductController::class, 'destroy'])
        ->middleware('permission:products.update')
        ->name('products.destroy');

    Route::get('/chatbot/config', [App\Http\Controllers\Admin\ChatbotController::class, 'config'])
        ->middleware('permission:chatbot.view,chatbot.menu')
        ->name('chatbot.config');
    Route::put('/chatbot/config', [App\Http\Controllers\Admin\ChatbotController::class, 'updateConfig'])
        ->middleware('permission:chatbot.update')
        ->name('chatbot.config.update');
    Route::put('/chatbot/mensajes/{messageTemplate}', [App\Http\Controllers\Admin\ChatbotController::class, 'updateMessageTemplate'])
        ->middleware('permission:chatbot.update')
        ->name('chatbot.message-templates.update');

    Route::prefix('empresas')->name('empresas.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\CompanyWhatsappController::class, 'index'])
            ->middleware('permission:chatbot.view,chatbot.menu,companies.menu')
            ->name('index');
        Route::post('/', [App\Http\Controllers\Admin\CompanyWhatsappController::class, 'store'])
            ->middleware('permission:chatbot.update')
            ->name('store');
        Route::put('/{company:slug}', [App\Http\Controllers\Admin\CompanyWhatsappController::class, 'updateCompany'])
            ->middleware('permission:chatbot.update')
            ->name('update');
        Route::get('/{company:slug}/whatsapp', [App\Http\Controllers\Admin\CompanyWhatsappController::class, 'show'])
            ->middleware('permission:chatbot.view,chatbot.menu,companies.menu')
            ->name('whatsapp');
        Route::put('/{company:slug}/whatsapp', [App\Http\Controllers\Admin\CompanyWhatsappController::class, 'updateCredentials'])
            ->middleware('permission:chatbot.update')
            ->name('whatsapp.update');
        Route::post('/{company:slug}/whatsapp/embedded-signup', [App\Http\Controllers\Admin\CompanyWhatsappController::class, 'embeddedSignup'])
            ->middleware('permission:chatbot.update')
            ->name('whatsapp.embedded-signup');
        Route::get('/{company:slug}/whatsapp/{profile}', [App\Http\Controllers\Admin\CompanyWhatsappController::class, 'profileDetails'])
            ->middleware('permission:chatbot.view,chatbot.menu,companies.menu')
            ->name('whatsapp.profile.details');
        Route::post('/{company:slug}/whatsapp/{profile}/test', [App\Http\Controllers\Admin\CompanyWhatsappController::class, 'testConnection'])
            ->middleware('permission:chatbot.update')
            ->name('whatsapp.profile.test');
        Route::post('/{company:slug}/whatsapp/{profile}/disconnect', [App\Http\Controllers\Admin\CompanyWhatsappController::class, 'disconnect'])
            ->middleware('permission:chatbot.update')
            ->name('whatsapp.profile.disconnect');
        Route::post('/{company:slug}/whatsapp/{profile}/set-primary', [App\Http\Controllers\Admin\CompanyWhatsappController::class, 'setPrimary'])
            ->middleware('permission:chatbot.update')
            ->name('whatsapp.profile.set-primary');
        Route::delete('/{company:slug}/whatsapp/{profile}', [App\Http\Controllers\Admin\CompanyWhatsappController::class, 'destroy'])
            ->middleware('permission:chatbot.update')
            ->name('whatsapp.profile.destroy');
    });

    Route::get('/marketing-flow', [App\Http\Controllers\Admin\MarketingFlowController::class, 'edit'])
        ->middleware('permission:marketing_flow.view,marketing_flow.menu')
        ->name('marketing-flow.edit');
    Route::put('/marketing-flow', [App\Http\Controllers\Admin\MarketingFlowController::class, 'update'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.update');

    Route::get('/marketing-flow/graph', [App\Http\Controllers\Admin\MarketingFlowGraphController::class, 'edit'])
        ->middleware('permission:marketing_flow.view,marketing_flow.menu')
        ->name('marketing-flow.graph.edit');
    Route::get('/marketing-flow/graph/data', [App\Http\Controllers\Admin\MarketingFlowGraphController::class, 'data'])
        ->middleware('permission:marketing_flow.view,marketing_flow.menu')
        ->name('marketing-flow.graph.data');
    Route::post('/marketing-flow/graph/nodes', [App\Http\Controllers\Admin\MarketingFlowGraphController::class, 'storeNode'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.nodes.store');
    Route::put('/marketing-flow/graph/nodes/{node}', [App\Http\Controllers\Admin\MarketingFlowGraphController::class, 'updateNode'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.nodes.update');
    Route::delete('/marketing-flow/graph/nodes/{node}', [App\Http\Controllers\Admin\MarketingFlowGraphController::class, 'destroyNode'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.nodes.destroy');
    Route::post('/marketing-flow/graph/edges', [App\Http\Controllers\Admin\MarketingFlowGraphController::class, 'storeEdge'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.edges.store');
    Route::delete('/marketing-flow/graph/edges/{edge}', [App\Http\Controllers\Admin\MarketingFlowGraphController::class, 'destroyEdge'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.edges.destroy');
    Route::post('/marketing-flow/graph/publish', [App\Http\Controllers\Admin\MarketingFlowGraphController::class, 'publish'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.publish');
    Route::get('/marketing-flow/graph/versions', [App\Http\Controllers\Admin\MarketingFlowGraphController::class, 'versions'])
        ->middleware('permission:marketing_flow.view,marketing_flow.menu')
        ->name('marketing-flow.graph.versions');
    Route::post('/marketing-flow/graph/versions/{version}/restore', [App\Http\Controllers\Admin\MarketingFlowGraphController::class, 'restoreVersion'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.versions.restore');
    Route::get('/marketing-flow/graph/catalog-options', [App\Http\Controllers\Admin\MarketingFlowGraphController::class, 'catalogOptions'])
        ->middleware('permission:marketing_flow.view,marketing_flow.menu')
        ->name('marketing-flow.graph.catalog-options');
    Route::post('/marketing-flow/graph/nodes/{node}/image', [App\Http\Controllers\Admin\MarketingFlowGraphController::class, 'uploadNodeImage'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.nodes.image.store');
    Route::delete('/marketing-flow/graph/nodes/{node}/image', [App\Http\Controllers\Admin\MarketingFlowGraphController::class, 'destroyNodeImage'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.nodes.image.destroy');

    Route::prefix('campanas')->name('marketing.')->group(function () {
        Route::get('/', [MarketingCampaignController::class, 'index'])
            ->middleware('permission:campaigns.view,campaigns.menu')
            ->name('index');
        Route::get('/nueva', [MarketingCampaignController::class, 'create'])
            ->middleware('permission:campaigns.manage')
            ->name('create');
        Route::post('/', [MarketingCampaignController::class, 'store'])
            ->middleware('permission:campaigns.manage')
            ->name('store');
        // Trae las plantillas aprobadas en la cuenta de Meta (Graph API) y las
        // guarda/actualiza en whatsapp_templates para poder elegirlas al crear
        // una campaña. Antes esto solo existía como endpoint público sin auth.
        Route::post('/plantillas/sincronizar', [WhatsappTemplateController::class, 'getApprovedTemplates'])
            ->middleware('permission:campaigns.manage')
            ->name('templates.sync');
        Route::get('/{id}', [MarketingCampaignController::class, 'show'])
            ->middleware('permission:campaigns.view')
            ->name('show');
        Route::get('/{id}/editar', [MarketingCampaignController::class, 'edit'])
            ->middleware('permission:campaigns.manage')
            ->name('edit');
        Route::put('/{id}', [MarketingCampaignController::class, 'update'])
            ->middleware('permission:campaigns.manage')
            ->name('update');
        Route::delete('/{id}', [MarketingCampaignController::class, 'destroy'])
            ->middleware('permission:campaigns.manage')
            ->name('destroy');
        Route::post('/{id}/enviar', [MarketingCampaignController::class, 'send'])
            ->middleware('permission:campaigns.send')
            ->name('send');
        Route::post('/{id}/reprogramar', [MarketingCampaignController::class, 'reschedule'])
            ->middleware('permission:campaigns.send')
            ->name('reschedule');
    });

    Route::get('/pricing-settings', [App\Http\Controllers\Admin\PricingSettingsController::class, 'edit'])
        ->middleware('permission:pricing_settings.view,pricing_settings.menu')
        ->name('pricing-settings.edit');
    Route::put('/pricing-settings', [App\Http\Controllers\Admin\PricingSettingsController::class, 'update'])
        ->middleware('permission:pricing_settings.update')
        ->name('pricing-settings.update');


    Route::get('/roles', [App\Http\Controllers\Admin\RoleController::class, 'index'])
        ->middleware('permission:roles.view,roles.menu')
        ->name('roles.index');
    Route::post('/roles', [App\Http\Controllers\Admin\RoleController::class, 'store'])
        ->middleware('permission:roles.update')
        ->name('roles.store');
    Route::put('/roles/{role}/permissions', [App\Http\Controllers\Admin\RoleController::class, 'updatePermissions'])
        ->middleware('permission:roles.update')
        ->name('roles.permissions.update');
    Route::delete('/roles/{role}', [App\Http\Controllers\Admin\RoleController::class, 'destroy'])
        ->middleware('permission:roles.update')
        ->name('roles.destroy');

    Route::get('/users', [App\Http\Controllers\Admin\UserAdminController::class, 'index'])
        ->middleware('permission:users.view,users.menu')
        ->name('users.index');
    Route::get('/users/create', [App\Http\Controllers\Admin\UserAdminController::class, 'create'])
        ->middleware('permission:users.create')
        ->name('users.create');
    Route::post('/users', [App\Http\Controllers\Admin\UserAdminController::class, 'store'])
        ->middleware('permission:users.create')
        ->name('users.store');
    Route::get('/users/{user}/edit', [App\Http\Controllers\Admin\UserAdminController::class, 'edit'])
        ->middleware('permission:users.update')
        ->name('users.edit');
    Route::put('/users/{user}', [App\Http\Controllers\Admin\UserAdminController::class, 'update'])
        ->middleware('permission:users.update')
        ->name('users.update');
    Route::patch('/users/{user}/toggle-active', [App\Http\Controllers\Admin\UserAdminController::class, 'toggleActive'])
        ->middleware('permission:users.update')
        ->name('users.toggle-active');
    Route::put('/users/{user}/role', [App\Http\Controllers\Admin\UserAdminController::class, 'updateRole'])
        ->middleware('permission:users.update,roles.update')
        ->name('users.role.update');

    Route::prefix('fallos-envio')->name('message-failures.')->group(function () {
        Route::get('/', [MessageFailuresController::class, 'index'])
            ->middleware('permission:message_failures.view,message_failures.menu')
            ->name('index');
        Route::get('/poll', [MessageFailuresController::class, 'poll'])
            ->middleware('permission:message_failures.view')
            ->name('poll');
        Route::post('/{failure}/resolver', [MessageFailuresController::class, 'resolve'])
            ->middleware('permission:message_failures.manage')
            ->name('resolve');
    });

    Route::get('/profile', [App\Http\Controllers\Admin\UserProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [App\Http\Controllers\Admin\UserProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [App\Http\Controllers\Admin\UserProfileController::class, 'updatePassword'])->name('profile.password.update');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::post('/admin/orders/{id}/status', [App\Http\Controllers\AdminController::class, 'updateOrderStatus'])
    ->middleware(['auth', 'admin', 'permission:orders.update', 'platform.feature:orders']);
