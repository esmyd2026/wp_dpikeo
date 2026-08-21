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
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_contacts', 'bot_enabled')) {
                $table->boolean('bot_enabled')->default(true)->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_contacts', 'bot_enabled')) {
                $table->dropColumn('bot_enabled');
            }
        });
    }
};
