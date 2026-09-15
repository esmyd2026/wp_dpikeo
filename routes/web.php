<?php

use App\Http\Controllers\Admin\ActiveCompanyController;
use App\Http\Controllers\Admin\AdminBulkOrderController;
use App\Http\Controllers\Admin\BusinessBranchController;
use App\Http\Controllers\Admin\ChatbotController;
use App\Http\Controllers\Admin\ChatbotKeywordController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\CompanyStorefrontController;
use App\Http\Controllers\Admin\CompanyWhatsappController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\DeliveryDriverReportController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\FranchiseController;
use App\Http\Controllers\Admin\InventoryReportController;
use App\Http\Controllers\Admin\InvoicingReportController;
use App\Http\Controllers\Admin\KitchenBoardController;
use App\Http\Controllers\Admin\MarketingCampaignController;
use App\Http\Controllers\Admin\MarketingFlowController;
use App\Http\Controllers\Admin\MarketingFlowGraphController;
use App\Http\Controllers\Admin\MessageFailuresController;
use App\Http\Controllers\Admin\OrdersReportsController;
use App\Http\Controllers\Admin\PosOrderController;
use App\Http\Controllers\Admin\PricingSettingsController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Admin\UserProfileController;
use App\Http\Controllers\Admin\WhatsappReportsController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BulkOrderController;
use App\Http\Controllers\DeliveryConfirmationController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\OrderPdfController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\StorefrontAccountController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\WhatsappTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'show'])->name('home');
Route::get('/tienda/{company:slug}', [StorefrontController::class, 'show'])->name('storefront.show');
Route::get('/tienda/{company:slug}/catalogo', [StorefrontController::class, 'catalog'])->name('storefront.catalog');
Route::get('/tienda/{company:slug}/csrf', [StorefrontController::class, 'csrfToken'])->name('storefront.csrf');
Route::post('/tienda/{company:slug}/delivery/cotizar', [StorefrontController::class, 'deliveryQuote'])
    ->middleware('throttle:30,1')
    ->name('storefront.delivery.quote');
Route::post('/tienda/{company:slug}/pedido', [StorefrontController::class, 'submit'])->name('storefront.submit');
Route::post('/tienda/{company:slug}/pedido/{cart}/comprobante', [StorefrontController::class, 'uploadPaymentProof'])->name('storefront.order.payment-proof');
Route::post('/tienda/{company:slug}/cuenta/registro', [StorefrontAccountController::class, 'register'])->name('storefront.account.register');
Route::post('/tienda/{company:slug}/cuenta/entrar', [StorefrontAccountController::class, 'login'])->name('storefront.account.login');
Route::get('/tienda/{company:slug}/cuenta/google', [StorefrontAccountController::class, 'redirectToGoogle'])->middleware('throttle:20,1')->name('storefront.account.google.redirect');
Route::get('/tienda/{company:slug}/cuenta/google/callback', [StorefrontAccountController::class, 'handleGoogleCallback'])->middleware('throttle:20,1')->name('storefront.account.google.callback');
Route::post('/tienda/{company:slug}/cuenta/google/completar', [StorefrontAccountController::class, 'completeGoogleRegistration'])->middleware('throttle:10,1')->name('storefront.account.google.complete');
Route::post('/tienda/{company:slug}/cuenta/recuperar', [StorefrontAccountController::class, 'requestPasswordReset'])->name('storefront.account.password.request');
Route::post('/tienda/{company:slug}/cuenta/restablecer', [StorefrontAccountController::class, 'resetPassword'])->name('storefront.account.password.reset');
Route::post('/tienda/{company:slug}/cuenta/salir', [StorefrontAccountController::class, 'logout'])->name('storefront.account.logout');
Route::get('/tienda/{company:slug}/cuenta/yo', [StorefrontAccountController::class, 'me'])->name('storefront.account.me');
Route::put('/tienda/{company:slug}/cuenta/perfil', [StorefrontAccountController::class, 'updateProfile'])->name('storefront.account.profile.update');
Route::post('/tienda/{company:slug}/cuenta/direcciones', [StorefrontAccountController::class, 'storeAddress'])->name('storefront.account.addresses.store');
Route::delete('/tienda/{company:slug}/cuenta/direcciones/{address}', [StorefrontAccountController::class, 'deleteAddress'])->name('storefront.account.addresses.delete');
Route::get('/tienda/{company:slug}/cuenta/pedidos', [StorefrontAccountController::class, 'orders'])->name('storefront.account.orders');
Route::put('/tienda/{company:slug}/cuenta/pedidos/{cart}/facturacion', [StorefrontAccountController::class, 'updateInvoice'])->name('storefront.account.orders.invoice');
Route::post('/tienda/{company:slug}/cuenta/pedidos/{cart}/comprobante', [StorefrontAccountController::class, 'uploadPaymentProof'])->name('storefront.account.orders.payment-proof');
Route::get('/pedir', [LandingController::class, 'startOrder'])->name('landing.start-order');
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');

Route::get('/privacidad', [LegalController::class, 'privacy'])->name('legal.privacy');

// Product photos are stored on Laravel's public disk. This fallback keeps
// them available even though public/storage also contains brand assets.
Route::get('/storage/product-images/{filename}', [ProductImageController::class, 'show'])
    ->where('filename', '[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)')
    ->name('product-images.show');

Route::prefix('pedido')->name('bulk-order.')->group(function () {
    Route::get('/{token}', [BulkOrderController::class, 'show'])->name('show');
    Route::get('/{token}/catalogo', [BulkOrderController::class, 'catalog'])->name('catalog');
    Route::post('/{token}', [BulkOrderController::class, 'submit'])->name('submit');
});

// El repartidor confirma la entrega desde su propio celular, con un link
// público que se le manda por WhatsApp al despacharlo -- sin usuario del panel.
Route::prefix('entrega')->name('delivery-confirmation.')->group(function () {
    Route::get('/{token}', [DeliveryConfirmationController::class, 'show'])->name('show');
    Route::post('/{token}', [DeliveryConfirmationController::class, 'confirm'])->name('confirm');
    Route::post('/{token}/en-camino', [DeliveryConfirmationController::class, 'notifyOnTheWay'])->name('on-the-way');
});

Route::get('/orden/{order}/pdf', [OrderPdfController::class, 'downloadSigned'])
    ->name('order.pdf.signed')
    ->middleware('signed');

// Punto de venta: pantalla completa (mismo diseño del micrositio de WhatsApp)
// para tablets/equipos en tienda. Fuera del layout de administración, pero
// sigue exigiendo sesión de personal autorizado.
Route::prefix('punto-de-venta')->name('pos.')
    ->middleware(['auth', 'admin', 'permission:bulk_orders.create', 'platform.feature:orders'])
    ->group(function () {
        Route::get('/', [PosOrderController::class, 'create'])->name('create');
        Route::post('/', [PosOrderController::class, 'submit'])->name('submit');
    });

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view,dashboard.menu')
        ->name('dashboard');

    Route::post('/empresa-activa', [ActiveCompanyController::class, 'store'])
        ->name('active-company.store');

    Route::get('/reports/whatsapp', [WhatsappReportsController::class, 'index'])
        ->middleware('permission:dashboard.view,dashboard.menu,whatsapp_reports.menu')
        ->name('reports.whatsapp');

    Route::get('/reports/orders', [OrdersReportsController::class, 'index'])
        ->middleware(['permission:orders.view,orders.menu,orders_reports.menu', 'platform.feature:orders'])
        ->name('reports.orders');
    Route::get('/reports/orders/contable', [OrdersReportsController::class, 'exportAccounting'])
        ->middleware(['permission:orders.view,orders.menu,orders_reports.menu', 'platform.feature:orders'])
        ->name('reports.orders.accounting-export');
    Route::get('/reports/delivery', [DeliveryDriverReportController::class, 'index'])
        ->middleware(['permission:orders.view,orders.menu,delivery_reports.menu', 'platform.feature:orders'])
        ->name('reports.delivery');
    Route::get('/reports/delivery/export', [DeliveryDriverReportController::class, 'export'])
        ->middleware(['permission:orders.view,orders.menu,delivery_reports.menu', 'platform.feature:orders'])
        ->name('reports.delivery.export');
    Route::get('/reports/facturacion', [InvoicingReportController::class, 'index'])
        ->middleware(['permission:orders.billing', 'platform.feature:orders'])
        ->name('reports.invoicing');
    Route::get('/reports/inventory', [InventoryReportController::class, 'index'])
        ->middleware('permission:products.view,products.menu,inventory.menu')
        ->name('reports.inventory');

    Route::get('/sucursales', [BusinessBranchController::class, 'index'])
        ->middleware('permission:pricing_settings.view,pricing_settings.menu,branches.menu')
        ->name('branches.index');
    Route::post('/sucursales', [BusinessBranchController::class, 'store'])
        ->middleware('permission:pricing_settings.update')
        ->name('branches.store');
    Route::put('/sucursales/{branch}', [BusinessBranchController::class, 'update'])
        ->middleware('permission:pricing_settings.update')
        ->name('branches.update');
    Route::delete('/sucursales/{branch}', [BusinessBranchController::class, 'destroy'])
        ->middleware('permission:pricing_settings.update')
        ->name('branches.destroy');
    Route::get('/preguntas-frecuentes', [FaqController::class, 'index'])
        ->middleware('permission:pricing_settings.view,pricing_settings.menu,faqs.menu')
        ->name('faqs.index');
    Route::post('/preguntas-frecuentes', [FaqController::class, 'store'])
        ->middleware('permission:pricing_settings.update')
        ->name('faqs.store');
    Route::put('/preguntas-frecuentes/{faq}', [FaqController::class, 'update'])
        ->middleware('permission:pricing_settings.update')
        ->name('faqs.update');
    Route::delete('/preguntas-frecuentes/{faq}', [FaqController::class, 'destroy'])
        ->middleware('permission:pricing_settings.update')
        ->name('faqs.destroy');
    Route::get('/franquicias', [FranchiseController::class, 'index'])
        ->middleware('permission:pricing_settings.view,pricing_settings.menu,franchises.menu')
        ->name('franchises.index');
    Route::post('/franquicias', [FranchiseController::class, 'store'])
        ->middleware('permission:pricing_settings.update')
        ->name('franchises.store');
    Route::put('/franquicias/{franchise}', [FranchiseController::class, 'update'])
        ->middleware('permission:pricing_settings.update')
        ->name('franchises.update');
    Route::delete('/franquicias/{franchise}', [FranchiseController::class, 'destroy'])
        ->middleware('permission:pricing_settings.update')
        ->name('franchises.destroy');

    Route::get('/orders', [AdminController::class, 'orders'])
        ->middleware(['permission:orders.view,orders.menu', 'platform.feature:orders'])
        ->name('orders');
    Route::get('/comandas', [KitchenBoardController::class, 'index'])
        ->middleware(['permission:orders.view,orders.menu,kitchen.menu', 'platform.feature:orders'])
        ->name('kitchen.index');
    Route::get('/comandas/pantalla', [KitchenBoardController::class, 'display'])
        ->middleware(['permission:orders.view,kitchen.menu', 'platform.feature:orders'])
        ->name('kitchen.display');
    Route::get('/comandas/datos', [KitchenBoardController::class, 'data'])
        ->middleware(['permission:orders.view,kitchen.menu', 'platform.feature:orders'])
        ->name('kitchen.data');
    Route::get('/comandas/{id}/imprimir', [KitchenBoardController::class, 'print'])
        ->middleware(['permission:orders.view,kitchen.menu', 'platform.feature:orders'])
        ->name('kitchen.print');
    Route::post('/comandas/turnos/reiniciar', [KitchenBoardController::class, 'resetTurns'])
        ->middleware(['permission:orders.update', 'platform.feature:orders'])
        ->name('kitchen.turns.reset');
    Route::post('/comandas/{id}/estado', [KitchenBoardController::class, 'transition'])
        ->middleware(['permission:orders.update', 'platform.feature:orders'])
        ->name('kitchen.transition');
    Route::get('/delivery', [DeliveryController::class, 'index'])
        ->middleware(['permission:orders.view,orders.menu,delivery.menu', 'platform.feature:orders'])
        ->name('delivery.index');
    Route::get('/delivery/datos', [DeliveryController::class, 'data'])
        ->middleware(['permission:orders.view,delivery.menu', 'platform.feature:orders'])
        ->name('delivery.data');
    Route::post('/delivery/{id}/confirmar', [DeliveryController::class, 'confirmDelivery'])
        ->middleware(['permission:orders.update', 'platform.feature:orders'])
        ->name('delivery.confirm');
    Route::get('/delivery/repartidores', [DeliveryController::class, 'drivers'])
        ->middleware(['permission:orders.view,delivery.menu', 'platform.feature:orders'])
        ->name('delivery.drivers');
    Route::get('/delivery/{id}/sucursales', [DeliveryController::class, 'branchesForOrder'])
        ->middleware(['permission:orders.view,delivery.menu', 'platform.feature:orders'])
        ->name('delivery.branches');
    Route::post('/delivery/{id}/despachar', [DeliveryController::class, 'dispatchToDriver'])
        ->middleware(['permission:orders.update', 'platform.feature:orders'])
        ->name('delivery.dispatch');
    Route::get('/orders/export', [AdminController::class, 'exportOrders'])
        ->middleware(['permission:orders.view,orders.menu', 'platform.feature:orders'])
        ->name('orders.export');
    Route::get('/orders/poll', [AdminController::class, 'pollNewOrders'])
        ->middleware(['permission:orders.view,orders.menu', 'platform.feature:orders'])
        ->name('orders.poll');
    Route::get('/messages', [AdminController::class, 'messages'])
        ->middleware(['permission:chats.view,chats.menu', 'platform.feature:chat'])
        ->name('messages');
    Route::get('/orders/{id}/details', [AdminController::class, 'orderDetails'])
        ->middleware(['permission:orders.view,orders.menu', 'platform.feature:orders'])
        ->name('orders.details');
    Route::get('/orders/{id}/pdf', [OrderPdfController::class, 'downloadAdmin'])
        ->middleware(['permission:orders.view,orders.menu', 'platform.feature:orders'])
        ->name('orders.pdf');
    Route::get('/orders/{id}/payment-proof', [AdminController::class, 'orderPaymentProof'])
        ->middleware(['permission:orders.view,orders.menu', 'platform.feature:orders'])
        ->name('orders.payment-proof');
    Route::post('/orders/{id}/send-confirmation', [AdminController::class, 'sendOrderConfirmation'])
        ->middleware(['permission:orders.update', 'platform.feature:orders'])
        ->name('orders.send-confirmation');
    Route::post('/orders/{id}/fulfillment-costs', [AdminController::class, 'sendFulfillmentCosts'])
        ->middleware(['permission:orders.update', 'platform.feature:orders'])
        ->name('orders.fulfillment-costs');
    Route::put('/orders/{id}', [AdminController::class, 'updateOrder'])
        ->middleware(['permission:orders.update', 'platform.feature:orders'])
        ->name('orders.update');
    Route::post('/orders/{id}/notes', [AdminController::class, 'storeOrderNote'])
        ->middleware(['permission:orders.update', 'platform.feature:orders'])
        ->name('orders.notes.store');
    Route::delete('/orders/{id}', [AdminController::class, 'destroyOrder'])
        ->middleware(['permission:orders.delete', 'platform.feature:orders'])
        ->name('orders.destroy');
    Route::prefix('orders/bulk')->name('orders.bulk.')->middleware(['permission:bulk_orders.create', 'platform.feature:orders'])->group(function () {
        Route::get('/', [AdminBulkOrderController::class, 'create'])->name('create');
        Route::get('/contacts', [AdminBulkOrderController::class, 'searchContacts'])->name('contacts');
        Route::post('/contacts', [AdminBulkOrderController::class, 'storeContact'])->name('contacts.store');
        Route::get('/catalogo', [AdminBulkOrderController::class, 'catalog'])->name('catalog');
        Route::post('/', [AdminBulkOrderController::class, 'submit'])->name('submit');
    });
    Route::get('/chats', [AdminController::class, 'chats'])
        ->middleware(['permission:chats.view,chats.menu', 'platform.feature:chat'])
        ->name('chats');

    Route::get('/clients', [ClientController::class, 'index'])
        ->middleware('permission:clients.view,clients.menu')
        ->name('clients.index');
    Route::post('/clients/bulk-reactivate-bot', [ClientController::class, 'bulkReactivateBot'])
        ->middleware('permission:clients.update')
        ->name('clients.bulk-reactivate-bot');
    Route::get('/clients/{client}', [ClientController::class, 'show'])
        ->middleware('permission:clients.detail,clients.view')
        ->name('clients.show');
    Route::put('/clients/{client}', [ClientController::class, 'update'])
        ->middleware('permission:clients.update')
        ->name('clients.update');
    Route::post('/clients/{client}/notes', [ClientController::class, 'storeNote'])
        ->middleware('permission:clients.notes')
        ->name('clients.notes.store');
    Route::post('/clients/{client}/reset-password', [ClientController::class, 'resetPassword'])
        ->middleware('permission:clients.update')
        ->name('clients.reset-password');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])
        ->middleware('permission:clients.delete')
        ->name('clients.destroy');

    Route::get('/chats/{contact}', [AdminController::class, 'chat'])
        ->middleware(['permission:chats.open,chats.view', 'platform.feature:chat'])
        ->name('chat');
    Route::get('/chats/{contact}/messages', [AdminController::class, 'chat'])
        ->middleware(['permission:chats.open,chats.view', 'platform.feature:chat'])
        ->name('chat.messages');
    Route::get('/chats/{contact}/new-messages', [AdminController::class, 'getNewMessages'])
        ->middleware(['permission:chats.open,chats.view', 'platform.feature:chat'])
        ->name('chat.new-messages');
    Route::get('/chats/list/update', [AdminController::class, 'getContactsList'])
        ->middleware(['permission:chats.view,chats.menu', 'platform.feature:chat'])
        ->name('chat.contacts.update');
    Route::get('/agent-requests/poll', [AdminController::class, 'pollAgentRequests'])
        ->middleware(['permission:chats.view,chats.menu', 'platform.feature:chat'])
        ->name('agent-requests.poll');
    Route::post('/chats/send', [AdminController::class, 'sendMessage'])
        ->middleware(['permission:chats.send', 'platform.feature:chat'])
        ->name('chat.send');
    Route::post('/chats/typing', [AdminController::class, 'typingIndicator'])
        ->middleware(['permission:chats.send', 'platform.feature:chat'])
        ->name('chat.typing');
    Route::get('/messages/{message}/image', [AdminController::class, 'getImage'])
        ->middleware(['permission:chats.view,chats.open', 'platform.feature:chat'])
        ->name('message.image');
    Route::get('/contacts/{id}', [AdminController::class, 'contactDetails'])
        ->middleware(['permission:chats.view', 'platform.feature:chat']);
    Route::post('/contacts/{contact}/toggle-bot', [AdminController::class, 'toggleBot'])
        ->middleware(['permission:chats.toggle_bot', 'platform.feature:chat'])
        ->name('contact.toggle-bot');
    Route::post('/contacts/{contact}/toggle-blacklist', [AdminController::class, 'toggleBlacklist'])
        ->middleware(['permission:chats.toggle_bot', 'platform.feature:chat'])
        ->name('contact.toggle-blacklist');
    Route::post('/contacts/{contact}/dismiss-agent', [AdminController::class, 'dismissAgentRequest'])
        ->middleware(['permission:chats.send', 'platform.feature:chat'])
        ->name('contact.dismiss-agent');
    Route::post('/contacts/{contact}/reset-conversation', [AdminController::class, 'resetConversation'])
        ->middleware(['permission:chats.toggle_bot', 'platform.feature:chat'])
        ->name('contact.reset-conversation');
    Route::post('/contacts/{contact}/retry-bot-reply', [AdminController::class, 'retryBotReply'])
        ->middleware(['permission:chats.toggle_bot', 'platform.feature:chat'])
        ->name('contact.retry-bot-reply');

    Route::get('/menus', [ChatbotController::class, 'menus'])
        ->middleware('permission:menus.view,menus.menu')
        ->name('menus.index');
    Route::post('/menus', [ChatbotController::class, 'storeMenu'])
        ->middleware('permission:menus.update')
        ->name('menus.store');
    Route::get('/menus/{menu}', [ChatbotController::class, 'showMenu'])
        ->middleware('permission:menus.view')
        ->name('menus.show');
    Route::put('/menus/{menu}', [ChatbotController::class, 'updateMenu'])
        ->middleware('permission:menus.update')
        ->name('menus.update');
    Route::delete('/menus/{menu}', [ChatbotController::class, 'deleteMenu'])
        ->middleware('permission:menus.update')
        ->name('menus.delete');

    Route::post('/menu-items/bulk-status', [ChatbotController::class, 'bulkUpdateMenuItemsStatus'])
        ->middleware('permission:menus.update')
        ->name('menu-items.bulk-status');
    Route::post('/menu-items/all-products-image', [ChatbotController::class, 'updateAllProductsCategoryImage'])
        ->middleware('permission:menus.update')
        ->name('menu-items.all-products-image');
    Route::post('/menu-items', [ChatbotController::class, 'storeMenuItem'])
        ->middleware('permission:menus.update')
        ->name('menu-items.store');
    Route::get('/menu-items/{item}', [ChatbotController::class, 'showMenuItem'])
        ->middleware('permission:menus.view')
        ->name('menu-items.show');
    Route::put('/menu-items/{item}', [ChatbotController::class, 'updateMenuItem'])
        ->middleware('permission:menus.update')
        ->name('menu-items.update');
    Route::delete('/menu-items/{item}', [ChatbotController::class, 'deleteMenuItem'])
        ->middleware('permission:menus.update')
        ->name('menu-items.delete');

    Route::get('/products', [ProductController::class, 'index'])
        ->middleware('permission:products.view,products.menu')
        ->name('products.index');
    Route::get('/products/import/template', [ProductController::class, 'downloadImportTemplate'])
        ->middleware('permission:products.update')
        ->name('products.import.template');
    Route::get('/products/export', [ProductController::class, 'exportCatalog'])
        ->middleware('permission:products.view')
        ->name('products.export');
    Route::post('/products/import', [ProductController::class, 'importCatalog'])
        ->middleware('permission:products.update')
        ->name('products.import');
    Route::post('/products/bulk-status', [ProductController::class, 'bulkUpdateStatus'])
        ->middleware('permission:products.update')
        ->name('products.bulk-status');
    Route::get('/products/create', [ProductController::class, 'create'])
        ->middleware('permission:products.update')
        ->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('permission:products.update')
        ->name('products.store');
    Route::post('/products/{product}/duplicate', [ProductController::class, 'duplicate'])
        ->middleware('permission:products.update')
        ->name('products.duplicate');
    Route::get('/products/{product}', [ProductController::class, 'show'])
        ->middleware('permission:products.view')
        ->name('products.show');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])
        ->middleware('permission:products.update')
        ->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->middleware('permission:products.update')
        ->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('permission:products.update')
        ->name('products.destroy');

    Route::get('/chatbot/config', [ChatbotController::class, 'config'])
        ->middleware('permission:chatbot.view,chatbot.menu')
        ->name('chatbot.config');
    Route::put('/chatbot/config', [ChatbotController::class, 'updateConfig'])
        ->middleware('permission:chatbot.update')
        ->name('chatbot.config.update');
    Route::put('/chatbot/mensajes/{messageTemplate}', [ChatbotController::class, 'updateMessageTemplate'])
        ->middleware('permission:chatbot.update')
        ->name('chatbot.message-templates.update');

    Route::get('/chatbot/palabras-clave', [ChatbotKeywordController::class, 'index'])
        ->middleware('permission:chatbot.view,chatbot.menu')
        ->name('chatbot-keywords.index');
    Route::post('/chatbot/palabras-clave', [ChatbotKeywordController::class, 'store'])
        ->middleware('permission:chatbot.update')
        ->name('chatbot-keywords.store');
    Route::put('/chatbot/palabras-clave/{keyword}', [ChatbotKeywordController::class, 'update'])
        ->middleware('permission:chatbot.update')
        ->name('chatbot-keywords.update');
    Route::delete('/chatbot/palabras-clave/{keyword}', [ChatbotKeywordController::class, 'destroy'])
        ->middleware('permission:chatbot.update')
        ->name('chatbot-keywords.destroy');

    Route::prefix('empresas')->name('empresas.')->group(function () {
        Route::get('/', [CompanyWhatsappController::class, 'index'])
            ->middleware('permission:chatbot.view,chatbot.menu,companies.menu')
            ->name('index');
        Route::post('/', [CompanyWhatsappController::class, 'store'])
            ->middleware('permission:chatbot.update')
            ->name('store');
        Route::put('/{company:slug}', [CompanyWhatsappController::class, 'updateCompany'])
            ->middleware('permission:chatbot.update')
            ->name('update');
        Route::post('/{company:slug}/bot', [CompanyWhatsappController::class, 'toggleBot'])
            ->middleware('permission:chatbot.update')
            ->name('bot.toggle');
        Route::get('/{company:slug}/tienda', [CompanyStorefrontController::class, 'edit'])
            ->middleware('permission:chatbot.view,chatbot.menu,companies.menu')
            ->name('storefront.edit');
        Route::put('/{company:slug}/tienda', [CompanyStorefrontController::class, 'update'])
            ->middleware('permission:chatbot.update')
            ->name('storefront.update');
        Route::get('/{company:slug}/whatsapp', [CompanyWhatsappController::class, 'show'])
            ->middleware('permission:chatbot.view,chatbot.menu,companies.menu')
            ->name('whatsapp');
        Route::put('/{company:slug}/whatsapp', [CompanyWhatsappController::class, 'updateCredentials'])
            ->middleware('permission:chatbot.update')
            ->name('whatsapp.update');
        Route::post('/{company:slug}/whatsapp/embedded-signup', [CompanyWhatsappController::class, 'embeddedSignup'])
            ->middleware('permission:chatbot.update')
            ->name('whatsapp.embedded-signup');
        Route::get('/{company:slug}/whatsapp/{profile}', [CompanyWhatsappController::class, 'profileDetails'])
            ->middleware('permission:chatbot.view,chatbot.menu,companies.menu')
            ->name('whatsapp.profile.details');
        Route::post('/{company:slug}/whatsapp/{profile}/test', [CompanyWhatsappController::class, 'testConnection'])
            ->middleware('permission:chatbot.update')
            ->name('whatsapp.profile.test');
        Route::post('/{company:slug}/whatsapp/{profile}/disconnect', [CompanyWhatsappController::class, 'disconnect'])
            ->middleware('permission:chatbot.update')
            ->name('whatsapp.profile.disconnect');
        Route::post('/{company:slug}/whatsapp/{profile}/set-primary', [CompanyWhatsappController::class, 'setPrimary'])
            ->middleware('permission:chatbot.update')
            ->name('whatsapp.profile.set-primary');
        Route::post('/{company:slug}/whatsapp/{profile}/clone-config', [CompanyWhatsappController::class, 'cloneConfig'])
            ->middleware('permission:chatbot.update')
            ->name('whatsapp.profile.clone-config');
        Route::delete('/{company:slug}/whatsapp/{profile}', [CompanyWhatsappController::class, 'destroy'])
            ->middleware('permission:chatbot.update')
            ->name('whatsapp.profile.destroy');
    });

    Route::get('/marketing-flow', [MarketingFlowController::class, 'edit'])
        ->middleware('permission:marketing_flow.view,marketing_flow.menu')
        ->name('marketing-flow.edit');
    Route::put('/marketing-flow', [MarketingFlowController::class, 'update'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.update');

    Route::get('/marketing-flow/graph', [MarketingFlowGraphController::class, 'edit'])
        ->middleware('permission:marketing_flow.view,marketing_flow.menu')
        ->name('marketing-flow.graph.edit');
    Route::get('/marketing-flow/graph/data', [MarketingFlowGraphController::class, 'data'])
        ->middleware('permission:marketing_flow.view,marketing_flow.menu')
        ->name('marketing-flow.graph.data');
    Route::post('/marketing-flow/graph/nodes', [MarketingFlowGraphController::class, 'storeNode'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.nodes.store');
    Route::put('/marketing-flow/graph/nodes/{node}', [MarketingFlowGraphController::class, 'updateNode'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.nodes.update');
    Route::delete('/marketing-flow/graph/nodes/{node}', [MarketingFlowGraphController::class, 'destroyNode'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.nodes.destroy');
    Route::post('/marketing-flow/graph/edges', [MarketingFlowGraphController::class, 'storeEdge'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.edges.store');
    Route::delete('/marketing-flow/graph/edges/{edge}', [MarketingFlowGraphController::class, 'destroyEdge'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.edges.destroy');
    Route::post('/marketing-flow/graph/publish', [MarketingFlowGraphController::class, 'publish'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.publish');
    Route::post('/marketing-flow/graph/unpublish', [MarketingFlowGraphController::class, 'unpublish'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.unpublish');
    Route::get('/marketing-flow/graph/versions', [MarketingFlowGraphController::class, 'versions'])
        ->middleware('permission:marketing_flow.view,marketing_flow.menu')
        ->name('marketing-flow.graph.versions');
    Route::post('/marketing-flow/graph/versions/{version}/restore', [MarketingFlowGraphController::class, 'restoreVersion'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.versions.restore');
    Route::get('/marketing-flow/graph/catalog-options', [MarketingFlowGraphController::class, 'catalogOptions'])
        ->middleware('permission:marketing_flow.view,marketing_flow.menu')
        ->name('marketing-flow.graph.catalog-options');
    Route::post('/marketing-flow/graph/nodes/{node}/image', [MarketingFlowGraphController::class, 'uploadNodeImage'])
        ->middleware('permission:marketing_flow.update')
        ->name('marketing-flow.graph.nodes.image.store');
    Route::delete('/marketing-flow/graph/nodes/{node}/image', [MarketingFlowGraphController::class, 'destroyNodeImage'])
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

    Route::get('/pricing-settings', [PricingSettingsController::class, 'edit'])
        ->middleware('permission:pricing_settings.view,pricing_settings.menu')
        ->name('pricing-settings.edit');
    Route::put('/pricing-settings', [PricingSettingsController::class, 'update'])
        ->middleware('permission:pricing_settings.update')
        ->name('pricing-settings.update');

    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.view,roles.menu')
        ->name('roles.index');
    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware('permission:roles.update')
        ->name('roles.store');
    Route::put('/roles/{role}/permissions', [RoleController::class, 'updatePermissions'])
        ->middleware('permission:roles.update')
        ->name('roles.permissions.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:roles.update')
        ->name('roles.destroy');

    Route::get('/users', [UserAdminController::class, 'index'])
        ->middleware('permission:users.view,users.menu')
        ->name('users.index');
    Route::get('/users/create', [UserAdminController::class, 'create'])
        ->middleware('permission:users.create')
        ->name('users.create');
    Route::post('/users', [UserAdminController::class, 'store'])
        ->middleware('permission:users.create')
        ->name('users.store');
    Route::get('/users/{user}/edit', [UserAdminController::class, 'edit'])
        ->middleware('permission:users.update')
        ->name('users.edit');
    Route::put('/users/{user}', [UserAdminController::class, 'update'])
        ->middleware('permission:users.update')
        ->name('users.update');
    Route::patch('/users/{user}/toggle-active', [UserAdminController::class, 'toggleActive'])
        ->middleware('permission:users.update')
        ->name('users.toggle-active');
    Route::put('/users/{user}/role', [UserAdminController::class, 'updateRole'])
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

    Route::get('/profile', [UserProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [UserProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [UserProfileController::class, 'updatePassword'])->name('profile.password.update');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::post('/admin/orders/{id}/status', [AdminController::class, 'updateOrderStatus'])
    ->middleware(['auth', 'admin', 'permission:orders.update', 'platform.feature:orders']);
