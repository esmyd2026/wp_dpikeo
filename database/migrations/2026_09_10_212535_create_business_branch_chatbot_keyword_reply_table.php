<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nombre corto a propósito: MySQL limita los identificadores (incluidos
        // los de las llaves foráneas autogeneradas) a 64 caracteres, y el
        // nombre "obvio" business_branch_chatbot_keyword_reply lo supera.
        Schema::create('keyword_reply_branch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_branch_id')->constrained('business_branches')->cascadeOnDelete();
            $table->foreignId('chatbot_keyword_reply_id')->constrained('chatbot_keyword_replies')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['business_branch_id', 'chatbot_keyword_reply_id'], 'keyword_reply_branch_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_reply_branch');
    }
};
