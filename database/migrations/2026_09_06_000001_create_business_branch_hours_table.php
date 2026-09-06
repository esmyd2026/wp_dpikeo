<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_branch_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_branch_id')->constrained()->cascadeOnDelete();
            // 0=domingo ... 6=sábado (mismo orden que Carbon::dayOfWeek).
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_closed')->default(false);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();

            $table->unique(['business_branch_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_branch_hours');
    }
};
