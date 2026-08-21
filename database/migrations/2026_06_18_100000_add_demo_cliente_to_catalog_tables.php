<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_menu_items', function (Blueprint $table) {
            $table->string('demo_cliente', 64)->nullable()->after('is_active')->index();
        });

        Schema::table('whatsapp_prices', function (Blueprint $table) {
            $table->string('demo_cliente', 64)->nullable()->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_menu_items', function (Blueprint $table) {
            $table->dropColumn('demo_cliente');
        });

        Schema::table('whatsapp_prices', function (Blueprint $table) {
            $table->dropColumn('demo_cliente');
        });
    }
};
