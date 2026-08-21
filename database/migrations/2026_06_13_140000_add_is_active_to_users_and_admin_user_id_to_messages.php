<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_admin');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('admin_user_id')->nullable()->after('contact_id');
            $table->foreign('admin_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['admin_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropForeign(['admin_user_id']);
            $table->dropIndex(['admin_user_id', 'created_at']);
            $table->dropColumn('admin_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
