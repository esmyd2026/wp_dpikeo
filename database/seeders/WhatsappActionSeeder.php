<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WhatsappActionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $actions = [
            // Acciones de productos
            [
                'code' => 'ver_producto',
                'name' => 'Ver Detalles del Producto',
                'description' => 'Muestra los detalles completos de un producto específico',
                'type' => 'product',
                'requires_product' => true,
                'metadata' => json_encode(['shows_details' => true]),
                'is_active' => true
            ],
            [
                'code' => 'comprar',
                'name' => 'Comprar Producto',
                'description' => 'Inicia el proceso de compra de un producto',
                'type' => 'product',
                'requires_product' => true,
                'metadata' => json_encode(['requires_confirmation' => true]),
                'is_active' => true
            ],
            [
                'code' => 'volver_productos',
                'name' => 'Volver a Productos',
                'description' => 'Regresa al menú principal de productos',
                'type' => 'menu',
                'requires_product' => false,
                'metadata' => json_encode(['returns_to_menu' => true]),
                'is_active' => true
            ],
            // Acciones de menú
            [
                'code' => 'ver_carrito',
                'name' => 'Ver Carrito',
                'description' => 'Muestra el contenido actual del carrito',
                'type' => 'menu',
                'requires_product' => false,
                'metadata' => json_encode(['shows_cart' => true]),
                'is_active' => true
            ],
            [
                'code' => 'finalizar_compra',
                'name' => 'Finalizar Compra',
                'description' => 'Inicia el proceso de finalización de compra',
                'type' => 'menu',
                'requires_product' => false,
                'metadata' => json_encode(['starts_checkout' => true]),
                'is_active' => true
            ],
            [
                'code' => 'menu_principal',
                'name' => 'Volver al Menú Principal',
                'description' => 'Regresa al menú principal de la aplicación',
                'type' => 'menu',
                'requires_product' => false,
                'metadata' => json_encode(['returns_to_main_menu' => true]),
                'is_active' => true
            ],
            [
                'code' => 'ver_categorias',
                'name' => 'Ver Categorías',
                'description' => 'Muestra las categorías de productos disponibles',
                'type' => 'menu',
                'requires_product' => false,
                'metadata' => json_encode(['shows_categories' => true]),
                'is_active' => true
            ],
            [
                'code' => 'ver_pedidos',
                'name' => 'Ver Mis Pedidos',
                'description' => 'Muestra el historial de pedidos del usuario',
                'type' => 'menu',
                'requires_product' => false,
                'metadata' => json_encode(['shows_orders' => true]),
                'is_active' => true
            ],
            // Acciones de pago
            [
                'code' => 'pago_transferencia',
                'name' => 'Pago por Transferencia',
                'description' => 'Selecciona pago por transferencia bancaria',
                'type' => 'payment',
                'requires_product' => false,
                'metadata' => json_encode(['payment_method' => 'transfer']),
                'is_active' => true
            ],
            [
                'code' => 'pago_efectivo',
                'name' => 'Pago en Efectivo',
                'description' => 'Selecciona pago en efectivo',
                'type' => 'payment',
                'requires_product' => false,
                'metadata' => json_encode(['payment_method' => 'cash']),
                'is_active' => true
            ],
            [
                'code' => 'pago_tarjeta',
                'name' => 'Pago con Tarjeta',
                'description' => 'Selecciona pago con tarjeta',
                'type' => 'payment',
                'requires_product' => false,
                'metadata' => json_encode(['payment_method' => 'card']),
                'is_active' => true
            ],
            // Acciones generales
            [
                'code' => 'contactar_vendedor',
                'name' => 'Contactar Vendedor',
                'description' => 'Inicia una conversación con un vendedor',
                'type' => 'general',
                'requires_product' => false,
                'metadata' => json_encode(['connects_to_agent' => true]),
                'is_active' => true
            ],
            [
                'code' => 'ver_promociones',
                'name' => 'Ver Promociones',
                'description' => 'Muestra las promociones activas',
                'type' => 'general',
                'requires_product' => false,
                'metadata' => json_encode(['shows_promotions' => true]),
                'is_active' => true
            ],
            [
                'code' => 'ayuda',
                'name' => 'Ayuda',
                'description' => 'Muestra información de ayuda y soporte',
                'type' => 'general',
                'requires_product' => false,
                'metadata' => json_encode(['shows_help' => true]),
                'is_active' => true
            ]
        ];

        foreach ($actions as $action) {
            DB::table('whatsapp_actions')->updateOrInsert(
                ['code' => $action['code']],
                $action
            );
        }
    }
}
