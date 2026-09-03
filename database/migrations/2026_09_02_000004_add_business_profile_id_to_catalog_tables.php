<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * whatsapp_menu_items y whatsapp_prices (categorías y productos del
     * catálogo) eran globales -- ninguna empresa nueva podría tener su
     * propio catálogo. Se agrega business_profile_id igual que ya existe en
     * whatsapp_menus (su padre) y como ya se hizo con franchise_id en
     * 2026_08_14_000004_create_franchises_and_link_catalog.php. Todo lo
     * existente se asigna al primer WhatsappBusinessProfile (dpikeo) para
     * no perder ni tocar sus datos actuales.
     */
    public function up(): void
    {
        Schema::table('whatsapp_menu_items', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_menu_items', 'business_profile_id')) {
                $table->foreignId('business_profile_id')->nullable()->after('menu_id')
                    ->constrained('whatsapp_business_profiles')->cascadeOnDelete();
                $table->index('business_profile_id');
            }
        });

        Schema::table('whatsapp_prices', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_prices', 'business_profile_id')) {
                $table->foreignId('business_profile_id')->nullable()->after('menu_item_id')
                    ->constrained('whatsapp_business_profiles')->cascadeOnDelete();
                $table->index('business_profile_id');
            }
        });

        $defaultProfileId = DB::table('whatsapp_business_profiles')->orderBy('id')->value('id');

        if ($defaultProfileId) {
            DB::table('whatsapp_menu_items')->whereNull('business_profile_id')->update(['business_profile_id' => $defaultProfileId]);
            DB::table('whatsapp_prices')->whereNull('business_profile_id')->update(['business_profile_id' => $defaultProfileId]);

            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE whatsapp_menu_items MODIFY business_profile_id BIGINT UNSIGNED NOT NULL');
                DB::statement('ALTER TABLE whatsapp_prices MODIFY business_profile_id BIGINT UNSIGNED NOT NULL');
            }
        }
    }

    public function down(): void
    {
        Schema::table('whatsapp_prices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_profile_id');
        });
        Schema::table('whatsapp_menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_profile_id');
        });
    }
};
