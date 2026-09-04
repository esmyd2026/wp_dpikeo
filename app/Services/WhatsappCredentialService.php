<?php

namespace App\Services;

use App\Models\Company;
use App\Models\WhatsappBusinessProfile;

/**
 * Único punto para resolver qué cuenta de WhatsApp (credenciales) usar.
 * Evita que distintas partes del código lean config('whatsapp.*') o
 * WhatsappBusinessProfile::first() por su cuenta y queden desincronizadas
 * entre sí cuando exista más de una empresa/número.
 */
class WhatsappCredentialService
{
    public function forCompany(Company $company): ?WhatsappBusinessProfile
    {
        return $company->whatsappAccounts()->usable()->first();
    }

    /**
     * Nunca resuelve un perfil desconectado: un número dado de baja localmente
     * no debe volver a autenticar envíos ni webhooks solo porque su fila
     * (conservada por trazabilidad) sigue teniendo el mismo phone_number_id.
     */
    public function byPhoneNumberId(string $phoneNumberId): ?WhatsappBusinessProfile
    {
        return WhatsappBusinessProfile::where('phone_number_id', $phoneNumberId)->usable()->first();
    }

}
