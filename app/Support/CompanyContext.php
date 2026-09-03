<?php

namespace App\Support;

use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotConfig;
use RuntimeException;

/**
 * Punto único para responder "para qué empresa estoy trabajando en este
 * momento" y lo que cuelga de eso (perfil de WhatsApp, config del bot).
 * WhatsappService resuelve esto internamente vía su $businessProfile ya
 * asignado (no se lo reemplaza para no reescribir su lógica); esta clase es
 * para el resto del código -- controladores de admin, comandos, servicios de
 * pedidos -- que hoy resolvía "la empresa" copiando `::first()` en cada
 * lugar.
 */
class CompanyContext
{
    public function __construct(
        public readonly ?Company $company,
        public readonly ?WhatsappBusinessProfile $businessProfile,
    ) {
    }

    public static function forBusinessProfile(?WhatsappBusinessProfile $profile): self
    {
        return new self($profile?->company, $profile);
    }

    public static function forCompany(Company $company): self
    {
        return new self($company, $company->whatsappAccounts()->first());
    }

    /**
     * Empresa activa de la sesión del admin autenticado, validada contra las
     * empresas que ese usuario puede administrar (User::canAccessCompany()).
     * Es la que deben usar TODAS las pantallas administrativas con datos
     * comerciales sensibles (catálogo, pedidos, campañas, credenciales). A
     * diferencia de default(), nunca resuelve "la primera empresa de la
     * base" -- si la sesión no tiene una empresa válida, cae a la primera
     * empresa AUTORIZADA de ese usuario (nunca una que no le pertenezca), y
     * si no tiene ninguna autorizada, falla en vez de adivinar.
     */
    public static function current(): self
    {
        $user = auth()->user();

        if (!$user) {
            throw new RuntimeException('No hay un usuario autenticado para resolver la empresa activa.');
        }

        $companyId = session('active_company_id');
        $company = $companyId ? Company::find($companyId) : null;

        if (!$company || !$user->canAccessCompany($company)) {
            $company = $user->authorizedCompanies()->first();

            if (!$company) {
                throw new RuntimeException('Este usuario no tiene ninguna empresa autorizada para administrar.');
            }

            session(['active_company_id' => $company->id]);
        }

        return self::forCompany($company);
    }

    /**
     * Cambia la empresa activa de la sesión. Devuelve false sin cambiar nada
     * si el usuario autenticado no está autorizado para esa empresa -- el
     * company_id nunca se acepta a ciegas solo porque vino en el request.
     */
    public static function switchTo(Company $company): bool
    {
        $user = auth()->user();
        if (!$user || !$user->canAccessCompany($company)) {
            return false;
        }

        session(['active_company_id' => $company->id]);

        return true;
    }

    /**
     * SOLO para contextos legacy sin usuario/sesión ni tenant conocido
     * (ej. un artisan command viejo que todavía no recibe su empresa
     * explícita). Resuelve "la primera empresa de la base" sin ninguna
     * autorización -- por eso NUNCA debe usarse en una pantalla
     * administrativa ni en un flujo que ya conoce su empresa. Preferir
     * current(), forCompany() o forBusinessProfile() en cualquier código
     * nuevo.
     */
    public static function default(): self
    {
        return self::forBusinessProfile(WhatsappBusinessProfile::orderBy('id')->first());
    }

    public function companyId(): ?int
    {
        return $this->company?->id;
    }

    public function businessProfileId(): ?int
    {
        return $this->businessProfile?->id;
    }

    /**
     * Config del bot de ESTA empresa, o null si todavía no tiene una propia.
     * Nunca cae a la de otra empresa: una empresa nueva sin configurar debe
     * verse "sin configurar", no heredar en silencio el branding/contenido
     * de otra. El fallback a `::first()` solo aplica en el caso legacy de no
     * tener ninguna empresa resuelta en absoluto (ver default()).
     */
    public function chatbotConfig(): ?WhatsappChatbotConfig
    {
        if ($this->businessProfile) {
            return WhatsappChatbotConfig::where('business_profile_id', $this->businessProfile->id)->first();
        }

        return WhatsappChatbotConfig::first();
    }
}
