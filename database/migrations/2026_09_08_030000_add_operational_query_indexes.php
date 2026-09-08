<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El webhook identifica al cliente por empresa y teléfono. La
        // validación evita que una migración arregle rendimiento eliminando
        // o fusionando datos silenciosamente si una instalación ya tiene
        // duplicados que deben revisarse manualmente.
        $hasDuplicateContacts = DB::table('whatsapp_contacts')
            ->select('business_profile_id', 'phone_number')
            ->whereNotNull('business_profile_id')
            ->groupBy('business_profile_id', 'phone_number')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateContacts) {
            throw new RuntimeException(
                'No se puede crear el índice único de contactos: existen teléfonos duplicados dentro de una misma empresa.'
            );
        }

        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->unique(['business_profile_id', 'phone_number'], 'contacts_profile_phone_unique');
            $table->index(['business_profile_id', 'status', 'name'], 'contacts_profile_status_name_idx');
            $table->index(['business_profile_id', 'last_inbound_at'], 'contacts_profile_inbound_idx');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->index(['contact_id', 'created_at'], 'messages_contact_created_idx');
            $table->index(['business_profile_id', 'created_at'], 'messages_profile_created_idx');
        });

        Schema::table('whatsapp_carts', function (Blueprint $table) {
            $table->index(['contact_id', 'status', 'created_at'], 'carts_contact_status_created_idx');
            $table->index(['branch_id', 'status', 'created_at'], 'carts_branch_status_created_idx');
            $table->index(['status', 'created_at'], 'carts_status_created_idx');
        });

        Schema::table('whatsapp_prices', function (Blueprint $table) {
            $table->index(['business_profile_id', 'is_active', 'name'], 'prices_profile_active_name_idx');
        });

        Schema::table('whatsapp_menu_items', function (Blueprint $table) {
            $table->index(['business_profile_id', 'is_active', 'order'], 'menu_items_profile_active_order_idx');
        });

        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->index(['business_profile_id', 'status', 'created_at'], 'campaigns_profile_status_created_idx');
            $table->index(['business_profile_id', 'status', 'sent_at'], 'campaigns_profile_status_sent_idx');
        });

        Schema::table('whatsapp_message_failures', function (Blueprint $table) {
            $table->index(
                ['business_profile_id', 'resolved_at', 'created_at'],
                'failures_profile_resolved_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_message_failures', function (Blueprint $table) {
            $table->dropIndex('failures_profile_resolved_created_idx');
        });

        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->dropIndex('campaigns_profile_status_created_idx');
            $table->dropIndex('campaigns_profile_status_sent_idx');
        });

        Schema::table('whatsapp_menu_items', function (Blueprint $table) {
            $table->dropIndex('menu_items_profile_active_order_idx');
        });

        Schema::table('whatsapp_prices', function (Blueprint $table) {
            $table->dropIndex('prices_profile_active_name_idx');
        });

        Schema::table('whatsapp_carts', function (Blueprint $table) {
            $table->dropIndex('carts_contact_status_created_idx');
            $table->dropIndex('carts_branch_status_created_idx');
            $table->dropIndex('carts_status_created_idx');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('messages_contact_created_idx');
            $table->dropIndex('messages_profile_created_idx');
        });

        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropUnique('contacts_profile_phone_unique');
            $table->dropIndex('contacts_profile_status_name_idx');
            $table->dropIndex('contacts_profile_inbound_idx');
        });
    }
};
