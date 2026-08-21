<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Templates table for storing message templates
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->text('content');
            $table->string('language');
            $table->string('status')->default('pending');
            $table->string('template_id')->nullable();
            $table->json('variables')->nullable();
            $table->json('components')->nullable();
            $table->timestamps();
        });

        // Business profiles table
        Schema::create('whatsapp_business_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('phone_number');
            $table->string('whatsapp_business_id');
            $table->string('access_token');
            $table->string('webhook_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Contacts table
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number');
            $table->string('name')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Messages table
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained('whatsapp_business_profiles');
            $table->foreignId('contact_id')->constrained('whatsapp_contacts');
            $table->string('message_id');
            $table->text('content');
            $table->string('type');
            $table->string('status');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Conversations table
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained('whatsapp_business_profiles');
            $table->foreignId('contact_id')->constrained('whatsapp_contacts');
            $table->string('status')->default('active');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        // Chatbot flows table
        Schema::create('whatsapp_chatbot_flows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trigger_keyword');
            $table->json('flow_steps');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Message templates approvals
        Schema::create('whatsapp_template_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('whatsapp_templates');
            $table->string('status');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_template_approvals');
        Schema::dropIfExists('whatsapp_chatbot_flows');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_contacts');
        Schema::dropIfExists('whatsapp_business_profiles');
        Schema::dropIfExists('whatsapp_templates');
    }
};
