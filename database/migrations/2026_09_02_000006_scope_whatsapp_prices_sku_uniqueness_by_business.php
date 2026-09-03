<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El SKU era único a nivel de toda la plataforma (whatsapp_prices_sku_unique),
     * lo que impediría que dos empresas distintas usaran el mismo SKU (ej.
     * "P001" en ambas). Se reemplaza por un único compuesto
     * (business_profile_id, sku): sigue siendo imposible duplicar SKU dentro
     * de una misma empresa, pero cada empresa tiene su propio espacio de SKUs.
     */
    public function up(): void
    {
        Schema::table('whatsapp_prices', function (Blueprint $table) {
            $table->dropUnique('whatsapp_prices_sku_unique');
            $table->unique(['business_profile_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_prices', function (Blueprint $table) {
            $table->dropUnique(['business_profile_id', 'sku']);
            $table->unique('sku');
        });
    }
};
