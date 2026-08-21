<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_contacts', 'business_profile_id')) {
                $table->foreignId('business_profile_id')
                    ->nullable()
                    ->after('phone_number')
                    ->constrained('whatsapp_business_profiles')
                    ->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropForeign(['business_profile_id']);
            $table->dropColumn('business_profile_id');
        });
    }
};
