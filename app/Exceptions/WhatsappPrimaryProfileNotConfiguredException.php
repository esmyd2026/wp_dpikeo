<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una empresa tiene 2+ WhatsappBusinessProfile usables (status=connected) y
 * ninguno marcado is_primary=true. CompanyContext::forCompany() nunca elige
 * uno arbitrariamente en ese caso -- lanza esto para forzar que un admin
 * elija explícitamente el número principal desde el panel.
 */
class WhatsappPrimaryProfileNotConfiguredException extends RuntimeException
{
    public const CODE = 'WHATSAPP_PRIMARY_PROFILE_NOT_CONFIGURED';

    public static function forCompany(string $companySlug, int $usableCount): self
    {
        return new self(self::CODE . ": la empresa '{$companySlug}' tiene {$usableCount} números conectados y ninguno marcado como principal.");
    }
}
