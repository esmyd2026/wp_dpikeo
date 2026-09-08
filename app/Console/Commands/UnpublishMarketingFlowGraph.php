<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\MarketingFlow;
use Illuminate\Console\Command;

/**
 * Deja de usar el flujo visual (grafo) para una empresa, sin borrar nada: el
 * bot vuelve a atenderse con "Flujo del bot" (el editor clásico de pasos)
 * para saludo, menú principal, etc. Pensado para cuando el grafo publicado
 * divergió del editor clásico y el admin prefiere no mantener los dos en
 * paralelo -- se puede volver a publicar el grafo cuando se quiera, sus
 * nodos quedan intactos.
 */
class UnpublishMarketingFlowGraph extends Command
{
    protected $signature = 'marketing-flow:unpublish {company : Slug de la empresa (ver la URL de Empresas -> WhatsApp)}';

    protected $description = 'Despublica el flujo visual de una empresa para que el bot vuelva a usar el editor clásico (Flujo del bot).';

    public function handle(): int
    {
        $company = Company::where('slug', $this->argument('company'))->first();
        if (!$company) {
            $this->error("No existe ninguna empresa con el slug '{$this->argument('company')}'.");

            return self::FAILURE;
        }

        $profileIds = $company->whatsappAccounts()->pluck('id');
        if ($profileIds->isEmpty()) {
            $this->error("La empresa '{$company->name}' todavía no tiene ningún número de WhatsApp conectado.");

            return self::FAILURE;
        }

        // Una empresa puede tener más de un WhatsappBusinessProfile (manual +
        // Embedded Signup, por ejemplo) -- se despublica el flujo de
        // cualquiera que tenga uno, no solo del primero, para no adivinar
        // cuál es "el" número real.
        $flows = MarketingFlow::whereIn('business_profile_id', $profileIds)->where('is_default', true)->get();
        if ($flows->isEmpty()) {
            $this->info("La empresa '{$company->name}' no tiene ningún flujo configurado -- nada que despublicar.");

            return self::SUCCESS;
        }

        $anyUpdated = false;
        foreach ($flows as $flow) {
            if ($flow->versions()->where('is_current', true)->update(['is_current' => false]) > 0) {
                $anyUpdated = true;
            }
        }

        if ($anyUpdated) {
            $this->info("Listo. El flujo visual de '{$company->name}' quedó despublicado -- el bot ya usa el editor clásico (Flujo del bot).");
        } else {
            $this->info("El flujo visual de '{$company->name}' ya estaba despublicado -- no había nada que hacer.");
        }

        return self::SUCCESS;
    }
}
