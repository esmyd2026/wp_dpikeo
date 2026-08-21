<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('sender_type')->default('client')->after('message_id'); // client or system
            $table->string('receiver_type')->default('system')->after('sender_type'); // client or system
        });
    }

    public function down()
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn(['sender_type', 'receiver_type']);
        });
    }
};
