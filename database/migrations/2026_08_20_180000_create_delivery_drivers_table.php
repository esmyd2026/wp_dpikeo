<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repartidores conocidos: se usan para armar el contacto de WhatsApp que se
 * le manda al cliente cuando su pedido sale en camino, y para reutilizar el
 * mismo repartidor en próximos despachos sin volver a escribir sus datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->nullable()->constrained('whatsapp_business_profiles')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('phone_number');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamps();

            $table->unique(['business_profile_id', 'phone_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_drivers');
    }
};
