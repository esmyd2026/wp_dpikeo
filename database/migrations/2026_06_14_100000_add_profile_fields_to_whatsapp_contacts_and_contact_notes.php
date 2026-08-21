<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->string('national_id', 20)->nullable()->after('name');
            $table->string('address', 500)->nullable()->after('national_id');
            $table->date('birth_date')->nullable()->after('address');

            $table->index('national_id');
        });

        Schema::create('whatsapp_contact_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('user_id');
            $table->text('body');
            $table->timestamps();

            $table->foreign('contact_id')->references('id')->on('whatsapp_contacts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['contact_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contact_notes');

        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropIndex(['national_id']);
            $table->dropColumn(['national_id', 'address', 'birth_date']);
        });
    }
};
