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
    /**
     * Orden deliberado: sigue la misma secuencia en la que el cliente los
     * recibe durante una compra real (resumen → confirmación → comprobante),
     * agrupados con 'group' para que el panel los muestre con encabezados en
     * vez de una sola lista plana de 14 mensajes sin relación aparente entre
     * sí. El aviso al equipo va aparte porque no lo lee el cliente.
     *
     * @return array<string, array{group: string, label: string, description: string, body: string, variables: array<string, string>}>
     */
    public static function definitions(): array
    {
        return [
            // --- 1. Antes de confirmar: resumen y forma de pago ---
            'order_review' => [
                'group' => 'Antes de confirmar',
                'label' => 'Resumen del pedido',
                'description' => 'Se muestra después de elegir la forma de pago y antes de los botones Confirmar, Seguir comprando o Cancelar.',
                'body' => "📋 *Resumen de tu pedido*\nPedido *#{{order_number}}*\n\n{{items}}{{fulfillment}}💳 *Pago*\n{{payment_method}}\n\n{{transfer_instructions}}{{cost_breakdown}}{{note}}¿Confirmas tu pedido?",
                'variables' => self::variables([
                    'order_number', 'items', 'fulfillment', 'payment_method',
                    'transfer_instructions', 'cost_breakdown', 'total', 'note',
                ]),
            ],
            'bank_transfer' => [
                'group' => 'Antes de confirmar',
                'label' => 'Datos y advertencia de transferencia',
                'description' => 'Se inserta dentro del resumen (variable {{transfer_instructions}}) solo cuando el cliente paga por transferencia. Los datos bancarios salen del campo configurado arriba.',
                'body' => "{{bank_instructions}}⚠️ *Importante*\nSolo aceptamos transferencias inmediatas. Verifica los datos antes de realizar el pago. Si el pago no se acredita de inmediato, no podremos despachar tu pedido.\n\n",
                'variables' => self::variables(['bank_instructions']),
            ],
            'card_payment' => [
                'group' => 'Antes de confirmar',
                'label' => 'Link de pago con tarjeta',
                'description' => 'Acompaña el botón que abre la página externa de pago con tarjeta, la primera vez que se genera el link.',
                'body' => '💳 Puedes pagar el pedido *{{order_number}}* por *{{currency}} {{total}}* directamente aquí:',
                'variables' => self::variables(['order_number', 'currency', 'total', 'payment_url']),
            ],
            'card_payment_repeat' => [
                'group' => 'Antes de confirmar',
                'label' => 'Link de tarjeta ya enviado',
                'description' => 'Se responde si el cliente escribe de nuevo sobre un pedido cuyo link de pago ya fue enviado antes.',
                'body' => "Ya te enviamos el link para pagar tu pedido *{{order_number}}* con tarjeta:\n{{payment_url}}",
                'variables' => self::variables(['order_number', 'payment_url']),
            ],
            'card_payment_unavailable' => [
                'group' => 'Antes de confirmar',
                'label' => 'Pago con tarjeta no disponible',
                'description' => 'Se muestra si el cliente elige tarjeta pero la empresa todavía no tiene un enlace de pago (HTTPS) configurado.',
                'body' => 'El pago con tarjeta no está disponible por ahora. Por favor elige otro método de pago o escríbenos.',
                'variables' => self::variables(['order_number']),
            ],

            // --- 2. Al confirmar el pedido ---
            'order_confirmed' => [
                'group' => 'Al confirmar el pedido',
                'label' => 'Pedido confirmado',
                'description' => 'Encabezado, total y (si paga por transferencia) los datos bancarios que se envían apenas el cliente toca "Confirmar". A este cuerpo se le agrega justo después payment_total_pending o proof_pending, según el caso.',
                'body' => "✅ *¡Pedido confirmado!*\n\n📦 Pedido: *{{order_number}}*\n\n💳 *Total a pagar: USD {{total}}*\n\n{{transfer_instructions}}",
                'variables' => self::variables(['order_number', 'total', 'cost_breakdown', 'payment_method', 'fulfillment', 'transfer_instructions']),
            ],
            'payment_total_pending' => [
                'group' => 'Al confirmar el pedido',
                'label' => 'Total pendiente de confirmación',
                'description' => 'Se agrega al final de "Pedido confirmado" cuando caja todavía debe confirmar el costo de envío o empaque -- mientras eso no se resuelva, no se pide comprobante todavía.',
                'body' => '🕐 Tu pedido se encuentra registrado. Pronto nuestro equipo te confirmará el total a pagar y ahí te pediremos tu comprobante.',
                'variables' => [],
            ],
            'proof_after_costs' => [
                'group' => 'Al confirmar el pedido',
                'label' => 'Cierre al confirmar costos pendientes',
                'description' => 'Frase corta que cierra el mensaje de caja cuando por fin confirma el envío/empaque que estaba pendiente (el número y total ya van arriba en ese mensaje, no hace falta repetirlos aquí).',
                'body' => '_Quedamos atentos a su comprobante de pago_',
                'variables' => self::variables(['order_number', 'currency', 'total']),
            ],
            'proof_pending' => [
                'group' => 'Al confirmar el pedido',
                'label' => 'Aviso de comprobante pendiente',
                'description' => 'Se agrega al final de "Pedido confirmado" cuando el total YA se conoce (caso contrario a payment_total_pending) -- avisa que falta el comprobante.',
                'body' => "📎 Cuando realices la transferencia, envía aquí la captura o comprobante para verificar el pago.\n\nTu pedido permanecerá *pendiente de verificación* hasta que confirmemos el pago. Te notificaremos por este mismo chat.\n\n",
                'variables' => self::variables(['order_number']),
            ],
            'pay_at_register' => [
                'group' => 'Al confirmar el pedido',
                'label' => 'Pedido para pagar en caja',
                'description' => 'Reemplaza por completo a "Pedido confirmado" -- se usa en vez de ese, no junto a él -- para pedidos que se pagan presentando el número directamente en caja (no piden comprobante).',
                'body' => "✅ *¡Pedido registrado!*\nPedido *#{{order_number}}*\n\n{{fulfillment}}{{cost_breakdown}}🧾 Pasa a caja con tu número de pedido para cancelar. ¡Gracias por tu pedido!",
                'variables' => self::variables(['order_number', 'fulfillment', 'cost_breakdown']),
            ],

            // --- 3. Comprobante de pago ---
            'proof_request' => [
                'group' => 'Comprobante de pago',
                'label' => 'Solicitud del comprobante',
                'description' => 'Mensaje aparte (no va pegado a "Pedido confirmado") que pide la imagen o PDF, indicando el monto exacto que debe respaldar.',
                'body' => "*Comprobante de pago*\n\nPedido: *{{order_number}}*\nTotal: *{{currency}} {{total}}*\n\nEnvía la captura o comprobante para verificarlo.",
                'variables' => self::variables(['order_number', 'currency', 'total']),
            ],
            'proof_reminder' => [
                'group' => 'Comprobante de pago',
                'label' => 'Recordatorio de comprobante',
                'description' => 'Se responde si el cliente sigue escribiendo mientras todavía no ha mandado el archivo del comprobante.',
                'body' => "🕐 Seguimos esperando el comprobante de pago de tu pedido *{{order_number}}*.\n\nEnvía la imagen o PDF del comprobante, o cancela el pedido si prefieres no continuar.",
                'variables' => self::variables(['order_number']),
            ],
            'proof_received' => [
                'group' => 'Comprobante de pago',
                'label' => 'Comprobante recibido',
                'description' => 'Confirmación inmediata al cliente apenas su imagen o PDF queda registrado -- todavía no significa que el pago fue verificado, solo que llegó.',
                'body' => '✅ Comprobante recibido para el pedido *{{order_number}}*. Lo verificaremos pronto.',
                'variables' => self::variables(['order_number']),
            ],

            // --- 4. Aviso interno (no lo ve el cliente) ---
            'staff_proof_received' => [
                'group' => 'Aviso interno al equipo',
                'label' => 'Comprobante recibido (equipo)',
                'description' => 'Se envía únicamente a los números autorizados del equipo (no al cliente) para que revisen el pago en el panel de Pedidos.',
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

    /**
     * Todas estas se arman solas con los datos del pedido -- no son un
     * campo de texto que se edite en otro lado, solo se insertan tal cual
     * las genera el sistema. La excepción real es {{bank_instructions}},
     * que sí viene de un campo de configuración (se aclara en su texto).
     *
     * @param string[] $names @return array<string, string>
     */
    private static function variables(array $names): array
    {
        $descriptions = [
            'order_number' => 'Número del pedido, ej. "ORD-010". Se arma solo con el pedido.',
            'items' => 'Lista de productos: nombre, cantidad y precio unitario de cada línea, uno debajo del otro. Se arma sola con los productos del pedido.',
            'fulfillment' => 'Bloque "🚚 Entrega" ya armado: sucursal, "Para llevar/servir", y si es delivery también la dirección y quién recibe. Se arma solo con los datos de entrega del pedido -- no se edita aparte.',
            'payment_method' => 'Texto de la forma de pago elegida, ej. "Transferencia o depósito bancario", "Efectivo" o "Tarjeta". Se arma solo según lo que eligió el cliente.',
            'transfer_instructions' => 'Bloque completo de datos bancarios + la advertencia de "solo transferencias inmediatas" (vacío si no paga por transferencia). Es el resultado ya renderizado de la plantilla "Datos y advertencia de transferencia" (más abajo en este mismo grupo), que a su vez usa el campo "Datos para transferencias o depósitos" de arriba en esta pantalla.',
            'cost_breakdown' => 'Subtotal/IVA si aplica y costo de envío (o "por confirmar" si aún no se calcula). Se arma solo con el pedido -- no incluye el total, para eso usa {{total}} aparte.',
            'total' => 'Monto total del pedido con dos decimales, ej. "8.50" (sin el símbolo $). Si el envío todavía está pendiente de confirmar, se arma como "8.50 + envío (por confirmar)" en vez de un número que después cambiaría.',
            'note' => 'Nota que el cliente escribió al pedido, con el prefijo "📝 Nota:" (vacío si no dejó ninguna). Se arma sola con el pedido.',
            'bank_instructions' => 'Los datos bancarios tal como están escritos en el campo "Datos para transferencias o depósitos", más arriba en esta misma pantalla (vacío si ese campo no está lleno). Para editarlos, cambia ese campo, no esta plantilla.',
            'currency' => 'Código de moneda, siempre "USD".',
            'customer' => 'Nombre del contacto si lo tiene guardado, o su número de teléfono. Se arma solo con el contacto.',
            'payment_url' => 'Enlace de pago con tarjeta generado para ese pedido específico. Se arma solo, no se edita aparte.',
        ];

        return array_intersect_key($descriptions, array_flip($names));
    }
}
