<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normaliza el vocabulario de status a: pending, connected, disconnected,
     * error, requires_action (antes todo quedaba en el "active" genérico de
     * la migración original, que no distinguía si el número realmente tenía
     * credenciales cargadas). También agrega connection_type (demo/manual/
     * embedded_signup) para que el panel pueda mostrar cómo se conectó cada
     * cuenta sin inferirlo de la presencia del token.
     */
    public function up(): void
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_business_profiles', 'connection_type')) {
                $table->string('connection_type')->nullable()->after('status');
            }
        });

        DB::table('whatsapp_business_profiles')
            ->where('status', 'active')
            ->whereNotNull('phone_number_id')
            ->whereNotNull('access_token')
            ->update(['status' => 'connected']);

        DB::table('whatsapp_business_profiles')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('phone_number_id')->orWhereNull('access_token');
            })
            ->update(['status' => 'pending']);

        DB::table('whatsapp_business_profiles')
            ->where('phone_number_id', 'DEMO-PHONE-ID-001')
            ->update(['connection_type' => 'demo']);

        DB::table('whatsapp_business_profiles')
            ->whereNull('connection_type')
            ->where('status', 'connected')
            ->update(['connection_type' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            $table->dropColumn('connection_type');
        });
    }
};
