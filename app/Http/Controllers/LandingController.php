<?php

namespace App\Http\Controllers;

use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Services\BulkOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LandingController extends Controller
{
    public const DEFAULTS = [
        'accent_color' => '#25d366',
        'logo_path' => null,
        'hero_eyebrow' => 'Pedidos por WhatsApp',
        'hero_title' => 'Pide tu comida favorita sin bajar ninguna app',
        'hero_title_highlight' => 'ninguna app',
        'hero_subtitle' => 'Escríbenos por WhatsApp, arma tu pedido en segundos y síguelo hasta que llegue a tu puerta — todo desde el chat que ya usas todos los días.',
        'order_cta_label' => 'Pedir por WhatsApp',
        'order_online_title' => 'O arma tu pedido aquí mismo',
        'order_online_text' => 'Prefieres no salir de la web? Elige tus productos, escribe tu nombre y te damos tu número de pedido para pagar en caja.',
        'order_online_cta_label' => 'Ordenar en línea',
        'step_1_title' => 'Escríbenos',
        'step_1_text' => 'Toca "Pedir por WhatsApp" o envíanos un mensaje. El bot te saluda y te muestra el menú al instante.',
        'step_2_title' => 'Arma tu pedido',
        'step_2_text' => 'Elige tus productos, agrégalos al carrito y dinos si es para llevar, para servir o con delivery.',
        'step_3_title' => 'Confirma y listo',
        'step_3_text' => 'Elige cómo pagar, confirma tu pedido y sigue su estado por el mismo chat hasta que te llegue.',
        'cta_band_title' => '¿Se te antojó algo?',
        'cta_band_subtitle' => 'Empieza tu pedido ahora mismo — te responde el bot al instante, todos los días.',
        'phone_msg_1' => '👋 ¡Hola! Bienvenido a {{negocio}}. ¿Qué se te antoja hoy?',
        'phone_msg_2' => 'Quiero ver el menú',
        'phone_msg_3' => '🍽️ Aquí tienes nuestras categorías. Elige una y arma tu pedido.',
        'phone_btn_1' => '🛍️ Ver menú',
        'phone_btn_2' => '🛒 Ver carrito',
        'phone_msg_4' => '✅ ¡Pedido confirmado! Te avisamos apenas esté en camino 🛵',
    ];

    public function index(): View
    {
        $profile = WhatsappBusinessProfile::first();
        $businessName = $profile?->business_name ?: 'DPIKEOS';

        $config = WhatsappChatbotConfig::where('business_profile_id', $profile?->id)->first()
            ?? WhatsappChatbotConfig::first();
        $landing = array_merge(self::DEFAULTS, $config?->metadata['landing'] ?? []);

        foreach ($landing as $key => $value) {
            if (is_string($value)) {
                $landing[$key] = str_replace('{{negocio}}', $businessName, $value);
            }
        }

        $demoNumber = preg_replace('/[^0-9]/', '', config('pricing.demo.whatsapp_number', config('whatsapp.demo_whatsapp_number', '')));
        $orderMessage = rawurlencode('¡Hola! Quiero hacer un pedido 🍗');
        $orderWhatsappUrl = $demoNumber ? "https://wa.me/{$demoNumber}?text={$orderMessage}" : null;

        return view('landing', [
            'businessName' => $businessName,
            'orderWhatsappUrl' => $orderWhatsappUrl,
            'landing' => $landing,
            'accentRgb' => WhatsappChatbotConfig::hexToRgb($landing['accent_color']),
            'logoUrl' => $landing['logo_path'] ? asset('storage/'.$landing['logo_path']) : asset('storage/img/dpikeologo.jpg'),
            'canOrderOnline' => app(BulkOrderService::class)->isAvailable(),
        ]);
    }

    /**
     * Punto de entrada público (sin WhatsApp) al mismo micrositio de pedidos
     * que usa el bot: crea un contacto "de web" desechable y su token, igual
     * que hace caja para ventas de mostrador (ver AdminBulkOrderController),
     * y manda al cliente directo a /pedido/{token}.
     */
    public function startOrder(BulkOrderService $bulkOrders): RedirectResponse
    {
        abort_unless($bulkOrders->isAvailable(), 404);

        $profile = WhatsappBusinessProfile::first();

        $contact = WhatsappContact::create([
            'business_profile_id' => $profile?->id,
            'phone_number' => 'WEB-'.now()->format('YmdHis').'-'.Str::lower(Str::random(5)),
            'name' => null,
            'status' => 'active',
            'bot_enabled' => false,
        ]);

        $token = $bulkOrders->issueToken($contact);
        abort_unless($token, 404);

        return redirect($bulkOrders->formUrl($token));
    }
}
