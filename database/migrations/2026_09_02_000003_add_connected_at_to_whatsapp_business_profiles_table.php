<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_business_profiles', 'connected_at')) {
                $table->timestamp('connected_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            $table->dropColumn('connected_at');
        });
    }
};
