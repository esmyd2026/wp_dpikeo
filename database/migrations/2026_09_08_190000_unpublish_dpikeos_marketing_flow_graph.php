<?php

use App\Models\Company;
use App\Models\MarketingFlow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Bug real reportado en vivo: el flujo visual (grafo) publicado divergió del
 * editor clásico ("Flujo del bot") para el saludo -- el grafo seguía
 * mandando el texto genérico de fábrica (sin imagen, sin la marca Pike),
 * mientras el admin ya tenía todo eso configurado en el editor clásico, que
 * el grafo publicado ignora por completo. El admin pidió explícitamente que
 * el bot use el editor clásico y no el flujo visual. Se despublica acá (en
 * vez de depender de que se corra el comando marketing-flow:unpublish a
 * mano) para que el fix quede aplicado con el próximo "php artisan migrate",
 * que ya es parte del deploy normal.
 *
 * No borra nada del grafo: si más adelante se quiere retomar, alcanza con
 * volver a publicarlo desde el editor visual.
 */
return new class extends Migration
{
    public function up(): void
    {
        $companies = Company::query()
            ->where('slug', 'like', '%pikeos%')
            ->orWhere('name', 'like', '%pikeos%')
            ->get();

        foreach ($companies as $company) {
            $profileIds = $company->whatsappAccounts()->pluck('id');
            if ($profileIds->isEmpty()) {
                continue;
            }

            $flows = MarketingFlow::whereIn('business_profile_id', $profileIds)->where('is_default', true)->get();
            foreach ($flows as $flow) {
                $updated = $flow->versions()->where('is_current', true)->update(['is_current' => false]);
                if ($updated > 0) {
                    Log::info('[unpublish_dpikeos_marketing_flow_graph] Flujo visual despublicado', [
                        'company' => $company->slug,
                        'flow_id' => $flow->id,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // No se re-publica automáticamente al revertir -- si esto se revierte,
        // hacerlo a propósito desde el editor visual (botón "Publicar").
    }
};
