<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_branches', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('delivery_fee_per_unit', 8, 2)->nullable()->after('longitude');
            $table->decimal('delivery_fee_km_unit', 8, 2)->nullable()->after('delivery_fee_per_unit');
            $table->decimal('delivery_fee_minimum', 8, 2)->nullable()->after('delivery_fee_km_unit');
        });
    }

    public function down(): void
    {
        Schema::table('business_branches', function (Blueprint $table) {
            $table->dropColumn([
                'latitude',
                'longitude',
                'delivery_fee_per_unit',
                'delivery_fee_km_unit',
                'delivery_fee_minimum',
            ]);
        });
    }
};
