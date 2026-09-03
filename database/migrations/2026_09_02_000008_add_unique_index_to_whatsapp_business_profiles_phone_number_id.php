<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nada impedía que dos WhatsappBusinessProfile (de dos empresas
     * distintas) compartieran el mismo phone_number_id -- byPhoneNumberId()
     * y la resolución del webhook habrían quedado a merced de cuál fila
     * apareciera primero. Un número de WhatsApp real solo puede estar
     * conectado a una cuenta a la vez, así que es correcto que sea único
     * (NULL sigue permitido varias veces: números todavía no conectados).
     */
    public function up(): void
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            $table->unique('phone_number_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            $table->dropUnique(['phone_number_id']);
        });
    }
};
