<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_daily_counters', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->unsignedSmallInteger('last_number')->default(0);
            $table->timestamp('reset_at')->nullable();
            $table->unsignedBigInteger('reset_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_daily_counters');
    }
};
