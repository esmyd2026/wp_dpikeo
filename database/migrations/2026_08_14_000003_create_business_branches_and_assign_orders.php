<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained('whatsapp_business_profiles')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 24);
            $table->string('phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_profile_id', 'code']);
        });

        Schema::table('whatsapp_carts', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('contact_id')
                ->constrained('business_branches')->nullOnDelete();
            $table->index('branch_id');
        });

        // Toda empresa inicia con una sucursal matriz. El administrador puede
        // renombrarla o añadir locales desde el nuevo módulo de sucursales.
        DB::table('whatsapp_business_profiles')->orderBy('id')->get()->each(function ($profile) {
            DB::table('business_branches')->insert([
                'business_profile_id' => $profile->id,
                'name' => 'Matriz',
                'code' => 'MATRIZ',
                'phone' => $profile->phone_number,
                'address' => data_get(json_decode($profile->metadata ?? '{}', true), 'address'),
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_carts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::dropIfExists('business_branches');
    }
};
