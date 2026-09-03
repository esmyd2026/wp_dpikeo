<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_business_profiles', 'company_id')) {
                $table->foreignId('company_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('companies')
                    ->onDelete('cascade');
            }
        });

        $dpikeoId = DB::table('companies')->where('slug', 'dpikeo')->value('id');

        if ($dpikeoId) {
            DB::table('whatsapp_business_profiles')
                ->whereNull('company_id')
                ->update(['company_id' => $dpikeoId]);
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE whatsapp_business_profiles MODIFY company_id BIGINT UNSIGNED NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
