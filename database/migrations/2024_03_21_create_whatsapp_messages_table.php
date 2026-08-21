<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('whatsapp_messages')) {
            Schema::create('whatsapp_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_profile_id')->nullable()->constrained('whatsapp_business_profiles');
                $table->foreignId('contact_id')->constrained('whatsapp_contacts');
                $table->string('message_id');
                $table->text('content');
                $table->string('type');
                $table->string('status');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        } else {
            // Si la tabla existe, solo agregamos las columnas que faltan
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('whatsapp_messages', 'business_profile_id')) {
                    $table->foreignId('business_profile_id')->nullable()->constrained('whatsapp_business_profiles');
                }
                if (!Schema::hasColumn('whatsapp_messages', 'metadata')) {
                    $table->json('metadata')->nullable();
                }
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
