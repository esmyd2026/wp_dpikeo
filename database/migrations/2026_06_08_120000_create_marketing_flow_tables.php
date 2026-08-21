<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained('whatsapp_business_profiles')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(true);
            $table->timestamps();
        });

        Schema::create('marketing_flow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('marketing_flows')->cascadeOnDelete();
            $table->string('step_key', 60);
            $table->string('name');
            $table->text('message_template')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['flow_id', 'step_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_flow_steps');
        Schema::dropIfExists('marketing_flows');
    }
};
