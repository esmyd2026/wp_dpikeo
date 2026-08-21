<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('image_path')
                ->constrained('whatsapp_prices')->nullOnDelete();
            $table->string('button_text', 20)->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn('button_text');
        });
    }
};
