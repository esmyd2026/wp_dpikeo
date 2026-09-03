<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Primera pieza de la arquitectura multiempresa: cada fila de
     * whatsapp_business_profiles pasará a pertenecer a una Company (ver
     * migración add_company_id_to_whatsapp_business_profiles_table). dpikeo
     * se siembra aquí mismo como la empresa id=1 para que esa migración
     * pueda asignarle las filas existentes.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        DB::table('companies')->insert([
            'uuid' => (string) Str::uuid(),
            'name' => 'dpikeo',
            'slug' => 'dpikeo',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
