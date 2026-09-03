<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * franchises no tenía ninguna relación con whatsapp_business_profiles,
     * así que una franquicia (y todo lo que cuelga de ella via franchise_id)
     * no tenía dueño de empresa. Se agrega para cerrar el último hueco del
     * catálogo: menu_items/prices ya quedaron con business_profile_id
     * directo, pero también se llega a ellos por franchise_id, y esa
     * franquicia debe pertenecer a la misma empresa.
     */
    public function up(): void
    {
        Schema::table('franchises', function (Blueprint $table) {
            if (!Schema::hasColumn('franchises', 'business_profile_id')) {
                $table->foreignId('business_profile_id')->nullable()->after('id')
                    ->constrained('whatsapp_business_profiles')->cascadeOnDelete();
                $table->index('business_profile_id');
            }
        });

        $defaultProfileId = DB::table('whatsapp_business_profiles')->orderBy('id')->value('id');

        if ($defaultProfileId) {
            DB::table('franchises')->whereNull('business_profile_id')->update(['business_profile_id' => $defaultProfileId]);
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE franchises MODIFY business_profile_id BIGINT UNSIGNED NOT NULL');
            }
        }
    }

    public function down(): void
    {
        Schema::table('franchises', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_profile_id');
        });
    }
};
