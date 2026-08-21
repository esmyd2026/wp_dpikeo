<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_prices', function (Blueprint $table) {
            $table->string('sku', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_prices', function (Blueprint $table) {
            $table->string('sku', 4)->change();
        });
    }
};
