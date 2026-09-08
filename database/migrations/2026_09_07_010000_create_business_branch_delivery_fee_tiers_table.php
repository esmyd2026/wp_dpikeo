<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_branch_delivery_fee_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_branch_id')->constrained('business_branches')->cascadeOnDelete();
            $table->decimal('from_km', 6, 2);
            // null = "en adelante" (sin tope superior para este tramo).
            $table->decimal('to_km', 6, 2)->nullable();
            $table->decimal('price', 8, 2);
            $table->timestamps();

            $table->index(['business_branch_id', 'from_km']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_branch_delivery_fee_tiers');
    }
};
