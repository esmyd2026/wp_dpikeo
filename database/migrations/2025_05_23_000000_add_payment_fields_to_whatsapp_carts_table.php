<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('whatsapp_carts', function (Blueprint $table) {
            $table->string('payment_status')->nullable()->after('status');
            $table->string('payment_method')->nullable()->after('payment_status');
            $table->string('payment_reference')->nullable()->after('payment_method');
        });
    }

    public function down()
    {
        Schema::table('whatsapp_carts', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'payment_method', 'payment_reference']);
        });
    }
};
