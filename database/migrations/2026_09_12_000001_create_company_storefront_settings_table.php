<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_storefront_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('logo_path')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->string('primary_color', 7)->default('#E85D04');
            $table->string('secondary_color', 7)->default('#7C2D12');
            $table->string('accent_color', 7)->default('#FFD166');
            $table->string('custom_domain')->nullable()->unique();
            $table->text('google_maps_api_key')->nullable();
            $table->string('google_maps_map_id')->nullable();
            $table->boolean('storefront_enabled')->default(true);
            $table->timestamps();
        });

        $companyId = DB::table('companies')->where('slug', 'dpikeo')->value('id');
        if ($companyId) {
            DB::table('company_storefront_settings')->insert([
                'company_id' => $companyId,
                'logo_path' => 'storage/img/dpikeologo.jpg',
                'hero_image_path' => 'storage/img/Combo Familiar.jpg',
                'primary_color' => '#E85D04',
                'secondary_color' => '#7C2D12',
                'accent_color' => '#FFD166',
                'storefront_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_storefront_settings');
    }
};
