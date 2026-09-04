<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Máximo un perfil principal usable por empresa" se garantiza a nivel de
     * aplicación (transacción en CompanyWhatsappController::setPrimary), no
     * con un índice único condicionado -- MySQL no soporta un unique index
     * parcial (WHERE is_primary), y una columna generada agrega complejidad
     * que no hace falta para una sola bandera administrada siempre por un
     * único punto de escritura.
     */
    public function up(): void
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_business_profiles', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });
    }
};
