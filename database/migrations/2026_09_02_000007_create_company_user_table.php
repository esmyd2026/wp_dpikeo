<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quién puede administrar qué empresa. Los super_admin (Siglo
     * Tecnológico) tienen acceso a todas sin necesitar fila acá (ver
     * User::canAccessCompany()); todo usuario no-super-admin necesita una
     * fila explícita. Se backfillean todos los usuarios existentes a la
     * primera empresa (dpikeo) para no quitarle acceso a nadie que ya
     * administraba el sistema antes de multiempresa.
     */
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        $firstCompanyId = DB::table('companies')->orderBy('id')->value('id');

        if ($firstCompanyId) {
            $now = now();
            $rows = DB::table('users')->pluck('id')->map(fn ($userId) => [
                'company_id' => $firstCompanyId,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if ($rows !== []) {
                DB::table('company_user')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
