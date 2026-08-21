<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franchises', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 64)->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('whatsapp_menu_items', function (Blueprint $table) {
            $table->foreignId('franchise_id')->nullable()->after('menu_id')
                ->constrained('franchises')->nullOnDelete();
            $table->index('franchise_id');
        });

        Schema::table('whatsapp_prices', function (Blueprint $table) {
            $table->foreignId('franchise_id')->nullable()->after('menu_item_id')
                ->constrained('franchises')->nullOnDelete();
            $table->index('franchise_id');
        });

        $now = now();
        DB::table('franchises')->insert([
            'name' => 'DPIKEOS · Club Dpikeolovers',
            'slug' => 'dpikeos',
            'description' => 'Franquicia principal de pollo, combos y hamburguesas.',
            'is_default' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $franchiseId = DB::table('franchises')->where('slug', 'dpikeos')->value('id');
        DB::table('whatsapp_menu_items')->where('demo_cliente', 'dpikeos')->update(['franchise_id' => $franchiseId]);
        DB::table('whatsapp_prices')->where('demo_cliente', 'dpikeos')->update(['franchise_id' => $franchiseId]);
    }

    public function down(): void
    {
        Schema::table('whatsapp_prices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('franchise_id');
        });
        Schema::table('whatsapp_menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('franchise_id');
        });
        Schema::dropIfExists('franchises');
    }
};
