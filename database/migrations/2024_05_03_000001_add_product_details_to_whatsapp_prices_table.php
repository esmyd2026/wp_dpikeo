<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('whatsapp_prices', function (Blueprint $table) {
            $table->string('quantity')->nullable()->after('description');
            $table->string('flavor')->nullable()->after('quantity');
            $table->string('format')->nullable()->after('flavor');
            $table->text('benefits')->nullable()->after('format');
            $table->text('characteristics')->nullable()->after('benefits');
            $table->text('image')->nullable()->after('characteristics');
        });
    }

    public function down()
    {
        Schema::table('whatsapp_prices', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'flavor', 'format', 'benefits', 'nutritional_info']);
        });
    }
};
