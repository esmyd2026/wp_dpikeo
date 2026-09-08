<?php

namespace App\Support;

use App\Models\WhatsappChatbotConfig;

/**
 * Plantillas propias de cada empresa para los mensajes que arma el sistema
 * durante el cobro. Las acciones, importes y estados siguen controlados por
 * código; aquí solo se parametriza la redacción que ve el cliente/equipo.
 */
final class PaymentMessageTemplates
{
    /** @return array<string, array{label: string, description: string, body: string, variables: array<string, string>}> */
    public static function definitions(): array
    {
        return [
            'order_review' => [
                'label' => 'Resumen antes de confirmar el pedido',
                'description' => 'Se muestra después de elegir la forma de pago y antes de los botones Confirmar, Seguir comprando o Cancelar.',
                'body' => "📋 *Resumen de tu pedido*\nPedido *#{{order_number}}*\n\n{{items}}{{fulfillment}}💳 *Pago*\n{{payment_method}}\n\n{{transfer_instructions}}{{cost_breakdown}}{{note}}¿Confirmas tu pedido?",
                'variables' => self::variables([
                    'order_number', 'items', 'fulfillment', 'payment_method',
                    'transfer_instructions', 'cost_breakdown', 'note',
                ]),
            ],
            'bank_transfer' => [
                'label' => 'Datos y advertencia de transferencia',
                'description' => 'Acompaña el resumen cuando el cliente paga por transferencia. Los datos bancarios se toman del campo configurado arriba.',
                'body' => "{{bank_instructions}}⚠️ Solo aceptamos *transferencias inmediatas*. Verifica bien los datos antes de transferir: si el pago no se acredita de inmediato, no podremos despachar tu pedido.\n\n",
                'variables' => self::variables(['bank_instructions']),
            ],
            'card_payment' => [
                'label' => 'Link de pago con tarjeta',
                'description' => 'Acompaña el botón que abre la página externa de pago con tarjeta.',
                'body' => '💳 Puedes pagar el pedido *{{order_number}}* por *{{currency}} {{total}}* directamente aquí:',
                'variables' => self::variables(['order_number', 'currency', 'total', 'payment_url']),
            ],
            'card_payment_repeat' => [
                'label' => 'Link de tarjeta ya enviado',
                'description' => 'Se responde si el cliente intenta continuar un pedido cuyo link de pago ya fue enviado.',
                'body' => "Ya te enviamos el link para pagar tu pedido *{{order_number}}* con tarjeta:\n{{payment_url}}",
                'variables' => self::variables(['order_number', 'payment_url']),
            ],
            'card_payment_unavailable' => [
                'label' => 'Pago con tarjeta no disponible',
                'description' => 'Se muestra si el método fue seleccionado pero la empresa no tiene un enlace HTTPS configurado.',
                'body' => 'El pago con tarjeta no está disponible por ahora. Por favor elige otro método de pago o escríbenos.',
                'variables' => self::variables(['order_number']),
            ],
            'order_confirmed' => [
                'label' => 'Pedido confirmado',
                'description' => 'Encabezado y datos que se envían cuando el cliente confirma el pedido.',
                'body' => "✅ *¡Pedido confirmado!*\n\n📦 *Número de pedido:* {{order_number}}\n{{cost_breakdown}}💳 *Método de pago:* {{payment_method}}\n\n{{fulfillment}}",
                'variables' => self::variables(['order_number', 'cost_breakdown', 'payment_method', 'fulfillment']),
            ],
            'payment_total_pending' => [
                'label' => 'Total pendiente de confirmación',
                'description' => 'Se añade cuando caja todavía debe confirmar el envío o empaque antes de solicitar el comprobante.',
                'body' => '🕐 Tu pedido se encuentra registrado. Pronto nuestro equipo te confirmará el total a pagar y ahí te pediremos tu comprobante.',
                'variables' => [],
            ],
            'proof_pending' => [
                'label' => 'Aviso de comprobante pendiente',
                'description' => 'Se añade al pedido confirmado cuando ya se conoce el total y falta que el cliente envíe el comprobante.',
                'body' => "🕐 Tu pedido queda *pendiente de verificación* hasta que recibamos tu comprobante. En cuanto lo enviemos a revisión, te confirmamos por este mismo chat.\n\n",
                'variables' => self::variables(['order_number']),
            ],
            'proof_request' => [
                'label' => 'Solicitud del comprobante',
                'description' => 'Indica el pedido y el monto exacto que debe respaldar la imagen o el PDF.',
                'body' => "*Comprobante de pago*\n\nPedido: *{{order_number}}*\nTotal: *{{currency}} {{total}}*\n\nEnvía la captura o comprobante para verificarlo.",
                'variables' => self::variables(['order_number', 'currency', 'total']),
            ],
            'proof_reminder' => [
                'label' => 'Recordatorio de comprobante',
                'description' => 'Se responde si el cliente continúa escribiendo mientras todavía falta el archivo.',
                'body' => "🕐 Seguimos esperando el comprobante de pago de tu pedido *{{order_number}}*.\n\nEnvía la imagen o PDF del comprobante, o cancela el pedido si prefieres no continuar.",
                'variables' => self::variables(['order_number']),
            ],
            'proof_received' => [
                'label' => 'Comprobante recibido por el cliente',
                'description' => 'Confirmación inmediata al cliente después de registrar correctamente su imagen o PDF.',
                'body' => '✅ Comprobante recibido para el pedido *{{order_number}}*. Lo verificaremos pronto.',
                'variables' => self::variables(['order_number']),
            ],
            'proof_after_costs' => [
                'label' => 'Solicitud después de confirmar costos',
                'description' => 'Cierre del mensaje que confirma el total definitivo de envío o empaque.',
                'body' => '_Quedamos atentos a su comprobante de pago_',
                'variables' => self::variables(['order_number', 'currency', 'total']),
            ],
            'pay_at_register' => [
                'label' => 'Pedido para pagar en caja',
                'description' => 'Se usa para pedidos que deben presentar su número y cancelar directamente en caja.',
                'body' => "✅ *¡Pedido registrado!*\nPedido *#{{order_number}}*\n\n{{fulfillment}}{{cost_breakdown}}🧾 Pasa a caja con tu número de pedido para cancelar. ¡Gracias por tu pedido!",
                'variables' => self::variables(['order_number', 'fulfillment', 'cost_breakdown']),
            ],
            'staff_proof_received' => [
                'label' => 'Aviso interno de comprobante recibido',
                'description' => 'Se envía únicamente a los números autorizados del equipo para que revisen el pago en Pedidos.',
                'body' => "📎 *Comprobante de pago recibido*\n\n📦 Pedido: {{order_number}}\n👤 Cliente: {{customer}}\n💰 Total: \${{total}}\n\nRevísalo en el panel de Pedidos.",
                'variables' => self::variables(['order_number', 'customer', 'total']),
            ],
        ];
    }

    /** @param array<string, scalar|null> $replacements */
    public static function render(?WhatsappChatbotConfig $config, string $key, array $replacements = []): string
    {
        $definition = self::definitions()[$key] ?? null;
        if (! $definition) {
            return '';
        }

        $custom = trim((string) ($config?->metadata['payment_templates'][$key] ?? ''));
        $body = $custom !== '' ? $custom : $definition['body'];

        foreach ($definition['variables'] as $variable => $description) {
            $body = str_replace('{{'.$variable.'}}', (string) ($replacements[$variable] ?? ''), $body);
        }

        return $body;
    }

    /** @return string[] */
    public static function unknownVariables(string $key, string $body): array
    {
        $allowed = array_keys(self::definitions()[$key]['variables'] ?? []);
        preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', $body, $matches);

        return array_values(array_diff(array_unique($matches[1] ?? []), $allowed));
    }

    /** @param string[] $names @return array<string, string> */
    private static function variables(array $names): array
    {
        $descriptions = [
            'order_number' => 'Número del pedido',
            'items' => 'Productos, cantidades y precios',
            'fulfillment' => 'Sucursal, tipo, dirección y persona que recibe',
            'payment_method' => 'Forma de pago elegida',
            'transfer_instructions' => 'Bloque completo de datos de transferencia',
            'cost_breakdown' => 'Subtotal, IVA y costos aplicables',
            'note' => 'Nota del cliente, si existe',
            'bank_instructions' => 'Bloque de datos bancarios configurados',
            'currency' => 'Moneda, por ejemplo USD',
            'total' => 'Total con dos decimales',
            'customer' => 'Nombre o teléfono del cliente',
            'payment_url' => 'Enlace HTTPS configurado para pagar',
        ];

        return array_intersect_key($descriptions, array_flip($names));
    }
}
