<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Dos cosas en una sola migración de datos (no de esquema):
     *
     * 1. Re-normaliza cualquier status='active' remanente (mismo criterio que
     *    2026_09_02_000009_normalize_whatsapp_business_profiles_status): con
     *    credenciales completas -> connected, si no -> pending. Es idempotente
     *    -- si ya no queda ningún 'active', no actualiza nada.
     *
     * 2. Backfill de is_primary por empresa, SIN elegir arbitrariamente:
     *    - 0 perfiles usables (status=connected): nada que marcar.
     *    - exactamente 1 usable: se marca ese, es inequívoco.
     *    - 2+ usables: solo se marca si EXACTAMENTE UNO coincide con el
     *      phone_number_id que ya estaba configurado en el .env
     *      (WHATSAPP_PHONE_NUMBER_ID) -- ese es el número que la plataforma
     *      realmente usaba en producción antes de existir multiempresa/varios
     *      perfiles, así que es un criterio real, no una suposición. Si no hay
     *      match único, la empresa queda sin principal a propósito: el panel
     *      la debe mostrar como "requiere selección de número principal".
     */
    public function up(): void
    {
        DB::table('whatsapp_business_profiles')
            ->where('status', 'active')
            ->whereNotNull('phone_number_id')
            ->whereNotNull('access_token')
            ->update(['status' => 'connected']);

        DB::table('whatsapp_business_profiles')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('phone_number_id')->orWhereNull('access_token');
            })
            ->update(['status' => 'pending']);

        $legacyPhoneNumberId = config('whatsapp.phone_number_id');

        $companyIds = DB::table('whatsapp_business_profiles')
            ->whereNotNull('company_id')
            ->distinct()
            ->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $usable = DB::table('whatsapp_business_profiles')
                ->where('company_id', $companyId)
                ->where('status', 'connected')
                ->get(['id', 'phone_number_id']);

            if ($usable->count() === 1) {
                DB::table('whatsapp_business_profiles')
                    ->where('id', $usable->first()->id)
                    ->update(['is_primary' => true]);

                continue;
            }

            if ($usable->count() >= 2 && $legacyPhoneNumberId) {
                $matches = $usable->filter(fn ($row) => $row->phone_number_id === $legacyPhoneNumberId);

                if ($matches->count() === 1) {
                    DB::table('whatsapp_business_profiles')
                        ->where('id', $matches->first()->id)
                        ->update(['is_primary' => true]);
                }

                // Sin match único: no se marca ninguno. La empresa queda en
                // estado "requiere selección de número principal" a propósito.
            }
        }
    }

    public function down(): void
    {
        // Los datos de status ya normalizados no se revierten (sería volver a
        // un vocabulario legacy sin sentido); is_primary sí se puede limpiar.
        DB::table('whatsapp_business_profiles')->update(['is_primary' => false]);
    }
};
