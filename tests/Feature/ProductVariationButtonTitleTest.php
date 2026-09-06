<?php

namespace Tests\Feature;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappContact;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug real reportado en vivo: el botón de una variación con emoji (ej. "🥤
 * c/gas $3.99") aparecía mutilado y sin el emoji en WhatsApp.
 * getProductDetails() volvía a truncar los títulos con strlen()/substr()
 * (cuentan BYTES) después de que Str::limit() ya los había dejado bien
 * recortados por caracteres -- un emoji ocupa 4 bytes pero es un solo
 * carácter para el límite real de 20 de WhatsApp, así que un título que
 * entraba perfecto se cortaba igual, y el corte a nivel de bytes partía el
 * emoji a la mitad (por eso desaparecía del todo). El botón ya no antepone su
 * propio ícono de carrito (WhatsApp ya dibuja uno junto a cada botón de
 * respuesta) -- si el admin quiere un ícono, lo pone en el nombre de la
 * variación, como en este test.
 */
class ProductVariationButtonTitleTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(WhatsappService $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }

    public function test_a_short_variation_title_with_an_emoji_is_never_mangled_by_the_byte_based_safety_net(): void
    {
        $profile = WhatsappBusinessProfile::create([
            'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS', 'phone_number' => '593990000001',
            'whatsapp_business_id' => 'test-business', 'access_token' => 'test',
            'status' => WhatsappBusinessProfile::STATUS_CONNECTED,
        ]);
        $menu = WhatsappMenu::create([
            'business_profile_id' => $profile->id, 'title' => 'Menú', 'type' => 'list',
            'content' => 'Menú', 'action_id' => 'prices_menu',
        ]);
        $category = WhatsappMenuItem::create([
            'menu_id' => $menu->id, 'business_profile_id' => $profile->id, 'title' => 'Combos', 'action_id' => 'cat_test',
        ]);
        $product = WhatsappPrice::create([
            'menu_item_id' => $category->id, 'business_profile_id' => $profile->id, 'category' => 'Combos', 'sku' => 'VAR-TEST',
            'name' => 'Box Tender', 'price' => 3.75, 'currency' => 'USD', 'is_active' => true, 'stock' => 20,
            'metadata' => [
                'variations' => [
                    ['title' => '🥤 c/gas', 'price' => 3.99],
                    ['title' => 'Sin gas', 'price' => 3.50],
                ],
            ],
        ]);
        $contact = WhatsappContact::create(['phone_number' => '593990000002', 'name' => 'Cliente']);

        $service = new WhatsappService();
        $details = $this->invoke($service, 'getProductDetails', [$product->id, $contact]);

        $titles = collect($details['interactive']['action']['buttons'])->pluck('reply.title')->all();

        $this->assertSame('🥤 c/gas $3.99', $titles[0]);
        $this->assertSame('Sin gas $3.50', $titles[1]);
        foreach ($titles as $title) {
            $this->assertLessThanOrEqual(20, mb_strlen($title));
        }
    }
}
