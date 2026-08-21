<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('whatsapp_prices', function (Blueprint $table) {
            $table->integer('stock')->default(0)->after('is_active');
            $table->boolean('allow_quantity_selection')->default(true)->after('stock');
            $table->integer('min_quantity')->default(1)->after('allow_quantity_selection');
            $table->integer('max_quantity')->default(999)->after('min_quantity');
        });
    }

    public function down()
    {
        Schema::table('whatsapp_prices', function (Blueprint $table) {
            $table->dropColumn(['stock', 'allow_quantity_selection', 'min_quantity', 'max_quantity']);
        });
    }
};
