<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soporte para desconexión LOCAL (no destructiva contra Meta) y para el
     * resultado de "Probar conexión". disconnected_at nunca borra
     * phone_number_id/waba_id/connection_type/connected_at -- la fila se
     * conserva completa para trazabilidad histórica, solo cambia `status`.
     */
    public function up(): void
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_business_profiles', 'disconnected_at')) {
                $table->timestamp('disconnected_at')->nullable()->after('connected_at');
            }
            if (!Schema::hasColumn('whatsapp_business_profiles', 'last_verified_at')) {
                $table->timestamp('last_verified_at')->nullable()->after('disconnected_at');
            }
            if (!Schema::hasColumn('whatsapp_business_profiles', 'last_verification_status')) {
                $table->string('last_verification_status')->nullable()->after('last_verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            $table->dropColumn(['disconnected_at', 'last_verified_at', 'last_verification_status']);
        });
    }
};
