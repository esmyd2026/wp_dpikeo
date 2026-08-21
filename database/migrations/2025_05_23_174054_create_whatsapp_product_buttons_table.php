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
        Schema::create('whatsapp_actions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('Código único de la acción (ej: ver_producto, comprar)');
            $table->string('name')->comment('Nombre descriptivo de la acción');
            $table->string('description')->nullable()->comment('Descripción detallada de la acción');
            $table->string('type')->default('product')->comment('Tipo de acción (product, menu, general)');
            $table->boolean('requires_product')->default(false)->comment('Indica si la acción requiere un ID de producto');
            $table->json('metadata')->nullable()->comment('Datos adicionales específicos de la acción');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Índices
            $table->index('code');
            $table->index('type');
            $table->index('is_active');
        });

        Schema::create('whatsapp_buttons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained('whatsapp_business_profiles')->onDelete('cascade');
            $table->foreignId('action_id')->constrained('whatsapp_actions')->onDelete('cascade');
            $table->string('title')->comment('Título del botón que se muestra al usuario');
            $table->string('icon')->nullable()->comment('Emoji o ícono del botón');
            $table->string('type')->default('reply')->comment('Tipo de botón (reply, url, etc)');
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0)->comment('Orden de aparición del botón');
            $table->json('metadata')->nullable()->comment('Datos adicionales específicos del botón');
            $table->timestamps();

            // Índices
            $table->index(['business_profile_id', 'action_id']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_buttons');
        Schema::dropIfExists('whatsapp_actions');
    }
};
