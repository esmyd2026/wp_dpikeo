<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('whatsapp_chatbot_responses', function (Blueprint $table) {
            $table->json('contacts')->nullable()->after('type');
        });
    }

    public function down()
    {
        Schema::table('whatsapp_chatbot_responses', function (Blueprint $table) {
            $table->dropColumn('contacts');
        });
    }
};
