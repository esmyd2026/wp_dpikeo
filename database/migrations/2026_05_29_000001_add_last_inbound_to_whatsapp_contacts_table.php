<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->string('last_inbound_message_id')->nullable()->after('bot_enabled');
            $table->timestamp('last_inbound_at')->nullable()->after('last_inbound_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropColumn(['last_inbound_message_id', 'last_inbound_at']);
        });
    }
};
