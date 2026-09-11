<?php

namespace Tests\Feature;

use App\Models\BusinessBranch;
use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Services\OrderPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderPdfPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_uses_the_order_company_logo_and_explains_delivery_and_payment(): void
    {
        config(['order_pdf.iva_rate' => 0]);
        Storage::fake('public');
        Storage::disk('public')->put(
            'landing/pdf-company/logo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nQAAAABJRU5ErkJggg==')
        );

        $company = Company::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Negocio del pedido',
            'slug' => 'negocio-pdf',
            'status' => 'active',
        ]);
        $profile = WhatsappBusinessProfile::create([
            'company_id' => $company->id,
            'business_name' => 'Negocio del pedido',
            'display_name' => 'Mi negocio',
            'phone_number' => '593990000001',
            'phone_number_id' => 'PHONE-PDF',
            'whatsapp_business_id' => 'WABA-PDF',
            'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
            'metadata' => ['address' => 'Av. Principal 123', 'city' => 'Guayaquil'],
        ]);
        WhatsappChatbotConfig::create([
            'business_profile_id' => $profile->id,
            'metadata' => ['landing' => ['logo_path' => 'landing/pdf-company/logo.png']],
        ]);
        $branch = BusinessBranch::create([
            'business_profile_id' => $profile->id,
            'name' => 'Urdesa',
            'code' => 'URDESA',
            'is_default' => true,
            'is_active' => true,
        ]);
        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id,
            'title' => 'Menú',
            'type' => 'list',
            'content' => 'Menú',
            'action_id' => 'prices_menu_pdf',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id,
            'business_profile_id' => $profile->id,
            'title' => 'Combos',
            'action_id' => 'cat_pdf',
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id,
            'business_profile_id' => $profile->id,
            'sku' => 'PDF1',
            'category' => 'Combos',
            'name' => 'Combo especial',
            'description' => 'Producto de prueba',
            'price' => 8.25,
            'currency' => 'USD',
            'is_active' => true,
        ]);
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '593999999999',
            'name' => 'Cliente de prueba',
            'status' => 'active',
        ]);
        $order = WhatsappCart::create([
            'contact_id' => $contact->id,
            'branch_id' => $branch->id,
            'status' => WhatsappCart::STATUS_COMPLETED,
            'total' => 13.25,
            'payment_method' => 'transferencia',
            'payment_status' => 'proof_submitted',
            'metadata' => [
                'order_details' => ['order_number' => 'ORD-PDF-001'],
                'service_type' => 'llevar',
                'pickup_mode' => 'delivery',
                'delivery_recipient_name' => 'Cliente de prueba',
                'delivery_location' => ['manual_address' => 'Av. de las Pruebas 456'],
                'delivery_fee' => 5,
                'delivery_fee_applied' => 5,
                'delivery_fee_pending_review' => false,
            ],
        ]);
        $order->items()->create([
            'whatsapp_price_id' => $product->id,
            'name' => $product->name,
            'price' => 8.25,
            'quantity' => 1,
        ]);

        $payload = app(OrderPdfService::class)->buildPayload($order);
        $html = view('pdf.order-ticket', $payload)->render();

        $this->assertStringStartsWith('data:image/png;base64,', $payload['company']['logo_data_uri']);
        $this->assertSame(5.0, $payload['totals']['delivery_fee']);
        $this->assertSame(13.25, $payload['totals']['total']);
        $this->assertSame('Pago confirmado', $payload['order']['payment_status_label']);
        $this->assertArrayNotHasKey('status', $payload['order']);
        $this->assertStringContainsString('Costo de delivery', $html);
        $this->assertStringContainsString('Pago confirmado', $html);
        $this->assertStringContainsString('No se debe cobrar nuevamente', $html);
        $this->assertStringNotContainsString('proof_submitted', $html);
        $this->assertStringNotContainsString('Entregado', $html);
    }

    public function test_pdf_never_invents_tax_on_top_of_the_recorded_order_total(): void
    {
        config([
            'order_pdf.iva_rate' => 0.15,
            'order_pdf.prices_include_iva' => false,
        ]);

        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'Negocio',
            'display_name' => 'Negocio',
            'phone_number' => '593990000010',
            'phone_number_id' => 'PHONE-TOTAL',
            'whatsapp_business_id' => 'WABA-TOTAL',
            'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id,
            'title' => 'Menú',
            'type' => 'list',
            'content' => 'Menú',
            'action_id' => 'prices_menu_total',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id,
            'business_profile_id' => $profile->id,
            'title' => 'Productos',
            'action_id' => 'cat_total',
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id,
            'business_profile_id' => $profile->id,
            'sku' => 'PDF2',
            'category' => 'Productos',
            'name' => 'Pedido sin recargo',
            'price' => 11.74,
            'currency' => 'USD',
            'is_active' => true,
        ]);
        $contact = WhatsappContact::create([
            'business_profile_id' => $profile->id,
            'phone_number' => '593999999998',
            'name' => 'Cliente',
            'status' => 'active',
        ]);
        $order = WhatsappCart::create([
            'contact_id' => $contact->id,
            'status' => WhatsappCart::STATUS_COMPLETED,
            'total' => 11.74,
        ]);
        $order->items()->create([
            'whatsapp_price_id' => $product->id,
            'name' => $product->name,
            'price' => 11.74,
            'quantity' => 1,
        ]);

        $payload = app(OrderPdfService::class)->buildPayload($order);

        $this->assertSame(11.74, $payload['totals']['subtotal']);
        $this->assertSame(0, $payload['totals']['iva']);
        $this->assertSame(11.74, $payload['totals']['total']);
    }
}
