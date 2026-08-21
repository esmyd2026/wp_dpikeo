<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('whatsapp_chatbot_responses', function (Blueprint $table) {
            $table->id();
            $table->string('keyword');
            $table->text('response');
            $table->string('type')->default('text');
            $table->boolean('is_active')->default(true);
            $table->boolean('show_menu')->default(true);
            $table->integer('order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_chatbot_responses');
    }
};
