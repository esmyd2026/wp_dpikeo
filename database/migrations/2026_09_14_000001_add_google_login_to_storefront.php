<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_storefront_settings', function (Blueprint $table) {
            $table->text('google_oauth_client_id')->nullable()->after('google_maps_map_id');
            $table->text('google_oauth_client_secret')->nullable()->after('google_oauth_client_id');
        });

        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->string('google_id')->nullable()->after('password');
            $table->string('google_email')->nullable()->after('google_id');
            $table->unique(['business_profile_id', 'google_id'], 'contacts_profile_google_unique');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropUnique('contacts_profile_google_unique');
            $table->dropColumn(['google_id', 'google_email']);
        });

        Schema::table('company_storefront_settings', function (Blueprint $table) {
            $table->dropColumn(['google_oauth_client_id', 'google_oauth_client_secret']);
        });
    }
};
