<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PIN de verificación en dos pasos que Meta exige al registrar un número
     * en Cloud API (POST /{phone_number_id}/register). Se guarda cifrado,
     * igual que access_token, para poder re-registrar el número en el futuro
     * sin depender de que un admin lo recuerde.
     */
    public function up(): void
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_business_profiles', 'two_factor_pin')) {
                $table->string('two_factor_pin')->nullable()->after('access_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            $table->dropColumn('two_factor_pin');
        });
    }
};
