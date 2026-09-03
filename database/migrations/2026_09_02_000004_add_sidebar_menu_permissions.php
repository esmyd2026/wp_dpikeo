<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $definitions = [
            'whatsapp_reports.menu' => ['module' => 'chats', 'name' => 'Reportes de WhatsApp'],
            'kitchen.menu' => ['module' => 'orders', 'name' => 'Comandas'],
            'delivery.menu' => ['module' => 'orders', 'name' => 'Delivery'],
            'orders_reports.menu' => ['module' => 'orders', 'name' => 'Reporte de pedidos'],
            'inventory.menu' => ['module' => 'products', 'name' => 'Inventario'],
            'companies.menu' => ['module' => 'chatbot', 'name' => 'Empresas y conexión Meta'],
            'franchises.menu' => ['module' => 'pricing_settings', 'name' => 'Franquicias'],
            'branches.menu' => ['module' => 'pricing_settings', 'name' => 'Sucursales'],
        ];

        $sortOrder = 900;
        foreach ($definitions as $key => $definition) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                [
                    'module' => $definition['module'],
                    'name' => $definition['name'],
                    'type' => 'submenu',
                    'sort_order' => $sortOrder++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        // Conserva lo que cada rol ya veía antes de separar los controles.
        // A partir de aquí cada enlace puede desactivarse individualmente.
        $inheritance = [
            'dashboard.menu' => ['whatsapp_reports.menu'],
            'orders.menu' => ['kitchen.menu', 'delivery.menu', 'orders_reports.menu'],
            'products.menu' => ['inventory.menu'],
            'chatbot.menu' => ['companies.menu'],
            'pricing_settings.menu' => ['franchises.menu', 'branches.menu'],
        ];

        foreach ($inheritance as $parentKey => $childKeys) {
            $parentId = DB::table('permissions')->where('key', $parentKey)->value('id');
            if (!$parentId) {
                continue;
            }

            $roleIds = DB::table('role_permission')->where('permission_id', $parentId)->pluck('role_id');
            $childIds = DB::table('permissions')->whereIn('key', $childKeys)->pluck('id');

            foreach ($roleIds as $roleId) {
                foreach ($childIds as $childId) {
                    DB::table('role_permission')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $childId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('key', [
            'whatsapp_reports.menu',
            'kitchen.menu',
            'delivery.menu',
            'orders_reports.menu',
            'inventory.menu',
            'companies.menu',
            'franchises.menu',
            'branches.menu',
        ])->delete();
    }
};
