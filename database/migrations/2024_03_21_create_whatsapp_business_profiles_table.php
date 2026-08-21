<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('whatsapp_business_profiles')) {
            Schema::create('whatsapp_business_profiles', function (Blueprint $table) {
                $table->id();
                $table->string('whatsapp_business_id')->nullable();
                $table->string('phone_number')->unique();
                $table->string('business_name');
                $table->string('display_name');
                $table->string('status')->default('active');
                $table->string('access_token')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        } else {
            // Si la tabla existe, solo agregamos las columnas que faltan
            Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('whatsapp_business_profiles', 'whatsapp_business_id')) {
                    $table->string('whatsapp_business_id')->nullable();
                }
                if (!Schema::hasColumn('whatsapp_business_profiles', 'business_name')) {
                    $table->string('business_name');
                }
                if (!Schema::hasColumn('whatsapp_business_profiles', 'display_name')) {
                    $table->string('display_name');
                }
                if (!Schema::hasColumn('whatsapp_business_profiles', 'status')) {
                    $table->string('status')->default('active');
                }
                if (!Schema::hasColumn('whatsapp_business_profiles', 'access_token')) {
                    $table->string('access_token')->nullable();
                }
                if (!Schema::hasColumn('whatsapp_business_profiles', 'metadata')) {
                    $table->json('metadata')->nullable();
                }
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_business_profiles');
    }
};
