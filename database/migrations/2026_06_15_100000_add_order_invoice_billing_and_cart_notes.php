<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->string('billing_type', 10)->nullable()->after('birth_date');
            $table->string('billing_id', 20)->nullable()->after('billing_type');
            $table->string('billing_legal_name', 255)->nullable()->after('billing_id');

            $table->index('billing_id');
        });

        Schema::table('whatsapp_carts', function (Blueprint $table) {
            $table->boolean('requires_invoice')->default(false)->after('note');
            $table->string('invoice_status', 20)->default('none')->after('requires_invoice');
            $table->json('invoice_data')->nullable()->after('invoice_status');
        });

        Schema::create('whatsapp_cart_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('whatsapp_cart_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 20)->default('internal');
            $table->text('body');
            $table->timestamps();

            $table->foreign('whatsapp_cart_id')->references('id')->on('whatsapp_carts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['whatsapp_cart_id', 'created_at']);
            $table->index(['whatsapp_cart_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_cart_notes');

        Schema::table('whatsapp_carts', function (Blueprint $table) {
            $table->dropColumn(['requires_invoice', 'invoice_status', 'invoice_data']);
        });

        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropIndex(['billing_id']);
            $table->dropColumn(['billing_type', 'billing_id', 'billing_legal_name']);
        });
    }
};
