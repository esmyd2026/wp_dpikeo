<?php

namespace App\Console\Commands;

use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Models\WhatsappPrice;
use Illuminate\Console\Command;

/**
 * Genera comandas exclusivamente visuales para probar caja, cocina y TV.
 * No llama a Meta, no manda WhatsApp y no descuenta inventario.
 */
class SeedDemoKitchenOrders extends Command
{
    protected $signature = 'dpikeos:demo-comandas {--keep : Conserva los pedidos demo anteriores}';

    protected $description = 'Crea comandas de demostración para el tablero de cocina y la pantalla TV.';

    public function handle(): int
    {
        $products = WhatsappPrice::query()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->orderBy('id')
            ->take(5)
            ->get();

        if ($products->isEmpty()) {
            $this->error('No hay productos activos con stock para crear las comandas demo.');

            return self::FAILURE;
        }

        if (!$this->option('keep')) {
            WhatsappCart::query()
                ->where('metadata->demo_kitchen', true)
                ->each(function (WhatsappCart $cart): void {
                    $cart->items()->delete();
                    $cart->notes()->delete();
                    $cart->delete();
                });
        }

        $contact = WhatsappContact::query()->firstOrCreate(
            ['phone_number' => '593000000000'],
            ['name' => 'Cliente de demostración', 'status' => 'active', 'bot_enabled' => false]
        );

        $scenarios = [
            ['status' => WhatsappCart::STATUS_CONFIRMED, 'minutes' => 2, 'lines' => [[0, 1]]],
            ['status' => WhatsappCart::STATUS_CONFIRMED, 'minutes' => 5, 'lines' => [[1, 2]]],
            ['status' => WhatsappCart::STATUS_CONFIRMED, 'minutes' => 8, 'lines' => [[2, 1], [0, 1]]],
            ['status' => WhatsappCart::STATUS_PREPARING, 'minutes' => 7, 'lines' => [[0, 2], [3, 1]]],
            ['status' => WhatsappCart::STATUS_PREPARING, 'minutes' => 14, 'lines' => [[1, 1]]],
            ['status' => WhatsappCart::STATUS_PREPARING, 'minutes' => 22, 'lines' => [[2, 1], [4, 1]]],
            ['status' => WhatsappCart::STATUS_READY, 'minutes' => 4, 'lines' => [[3, 2]]],
            ['status' => WhatsappCart::STATUS_READY, 'minutes' => 11, 'lines' => [[4, 1], [0, 1]]],
        ];

        foreach ($scenarios as $index => $scenario) {
            $createdAt = now()->subMinutes($scenario['minutes']);
            $cart = WhatsappCart::create([
                'contact_id' => $contact->id,
                'total' => 0,
                'status' => $scenario['status'],
                'note' => null,
                'metadata' => [
                    'demo_kitchen' => true,
                    'source' => 'kitchen_demo_seed',
                    'order_details' => ['order_number' => 'ORD-'.str_pad((string) (9001 + $index), 6, '0', STR_PAD_LEFT)],
                ],
            ]);

            $total = 0;
            foreach ($scenario['lines'] as [$productIndex, $quantity]) {
                $product = $products[$productIndex % $products->count()];
                $price = $product->is_promo && $product->promo_price ? (float) $product->promo_price : (float) $product->price;
                $cart->items()->create([
                    'whatsapp_price_id' => $product->id,
                    'name' => $product->name,
                    'price' => $price,
                    'quantity' => $quantity,
                    'line_note' => null,
                ]);
                $total += $price * $quantity;
            }

            $cart->forceFill([
                'total' => round($total, 2),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        $this->info('8 comandas de demostración creadas. No se enviaron mensajes ni se modificó inventario.');

        return self::SUCCESS;
    }
}
