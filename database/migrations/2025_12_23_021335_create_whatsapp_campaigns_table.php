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
        Schema::create('whatsapp_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained('whatsapp_business_profiles')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('message_type', ['text', 'template', 'image', 'interactive'])->default('text');
            $table->text('message_content')->nullable(); // Para mensajes de texto
            $table->foreignId('template_id')->nullable()->constrained('whatsapp_templates')->onDelete('set null');
            $table->json('template_variables')->nullable(); // Variables para plantillas
            $table->enum('recipient_type', ['all', 'filtered', 'selected'])->default('all');
            $table->json('recipient_filters')->nullable(); // Filtros para destinatarios
            $table->json('selected_contacts')->nullable(); // IDs de contactos seleccionados manualmente
            $table->enum('status', ['draft', 'scheduled', 'sending', 'completed', 'paused', 'cancelled'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('read_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaigns');
    }
};
