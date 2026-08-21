<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_business_profiles', 'phone_number_id')) {
                $table->string('phone_number_id')->nullable()->after('phone_number');
            }
        });
    }

    public function down()
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            $table->dropColumn('phone_number_id');
        });
    }
};
