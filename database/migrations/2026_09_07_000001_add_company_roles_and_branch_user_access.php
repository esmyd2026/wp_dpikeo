<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['company_id', 'name']);
        });

        Schema::table('company_user', function (Blueprint $table) {
            // El rol pertenece a la membresía de empresa. Se conserva users.role_id
            // como respaldo para superadministradores y datos anteriores.
            $table->foreignId('role_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::create('business_branch_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_branch_id')->constrained('business_branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['business_branch_id', 'user_id']);
        });

        // Mantiene el rol efectivo actual en cada empresa después del despliegue.
        DB::table('company_user')->orderBy('id')->eachById(function ($membership) {
            $roleId = DB::table('users')->where('id', $membership->user_id)->value('role_id');
            DB::table('company_user')->where('id', $membership->id)->update(['role_id' => $roleId]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_branch_user');

        Schema::table('company_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'name']);
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
