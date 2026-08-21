<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_chatbot_configs', function (Blueprint $table) {
            $table->boolean('monitoring_enabled')->default(false)->after('is_active');
            $table->string('monitoring_phone_number')->nullable()->after('monitoring_enabled');
            $table->string('monitoring_email')->nullable()->after('monitoring_phone_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_chatbot_configs', function (Blueprint $table) {
            $table->dropColumn(['monitoring_enabled', 'monitoring_phone_number', 'monitoring_email']);
        });
    }
};
