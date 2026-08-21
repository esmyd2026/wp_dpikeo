<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('payment_for', 20);
            $table->string('payment_method', 20);
            $table->decimal('amount', 12, 2);
            $table->timestamp('paid_at');
            $table->string('receipt_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('client_notes')->nullable();
            $table->text('review_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'created_at']);
            $table->index(['payment_for', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payment_receipts');
    }
};
