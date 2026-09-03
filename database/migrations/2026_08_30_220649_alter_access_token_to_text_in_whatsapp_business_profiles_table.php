<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los tokens permanentes de WhatsApp Cloud API (Meta) suelen superar los
     * 255 caracteres del VARCHAR original y truncaban con error SQL al guardar.
     * MODIFY es sintaxis MySQL; en SQLite (usado por los tests) las columnas
     * TEXT/VARCHAR ya son de tipo dinámico, así que no hace falta nada ahí.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE whatsapp_business_profiles MODIFY access_token TEXT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE whatsapp_business_profiles MODIFY access_token VARCHAR(255) NULL');
        }
    }
};
