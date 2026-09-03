<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mismo caso que whatsapp_prices.sku (ver
     * 2026_09_02_000006_scope_whatsapp_prices_sku_uniqueness_by_business.php):
     * el slug de franchises era único a nivel de toda la plataforma, así que
     * dos empresas no podrían usar el mismo slug (ej. "principal"). Se
     * reemplaza por único compuesto (business_profile_id, slug).
     */
    public function up(): void
    {
        Schema::table('franchises', function (Blueprint $table) {
            $table->dropUnique('franchises_slug_unique');
            $table->unique(['business_profile_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('franchises', function (Blueprint $table) {
            $table->dropUnique(['business_profile_id', 'slug']);
            $table->unique('slug');
        });
    }
};
