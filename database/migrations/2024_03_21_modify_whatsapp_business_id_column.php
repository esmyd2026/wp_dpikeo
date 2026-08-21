<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            $table->string('whatsapp_business_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            $table->string('whatsapp_business_id')->change();
        });
    }
};
