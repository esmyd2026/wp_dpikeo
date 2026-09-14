<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: el cliente confundió el campo "Correo (opcional)"
 * (contacto) con "Correo de facturación" (solo obligatorio si elige
 * "Factura con datos") -- pensó que el correo en general era obligatorio
 * porque "Confirmar" no avanzaba sin llenar los datos de facturación. El
 * campo de contacto siempre fue opcional de verdad (JS y backend ya lo
 * tratan como nullable); lo que hacía falta era dejar clara la diferencia
 * entre los dos "correo" del formulario, no volver obligatorio el de
 * contacto.
 */
class StorefrontInvoiceFieldsLabelingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_two_email_fields_are_clearly_distinguished_on_the_checkout_form(): void
    {
        $company = Company::where('slug', 'dpikeo')->firstOrFail();
        WhatsappBusinessProfile::create([
            'company_id' => $company->id, 'business_name' => 'DPIKEOS', 'display_name' => 'DPIKEOS',
            'phone_number' => '593997100001', 'phone_number_id' => 'DPIKEOS-INVOICE-LABEL',
            'access_token' => 'test-token', 'status' => WhatsappBusinessProfile::STATUS_CONNECTED, 'is_primary' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        // El de contacto sigue diciendo "opcional" -- nunca se volvió obligatorio.
        $response->assertSee('Correo de contacto <span>(opcional)</span>', false);
        // El de facturación ahora deja explícito que es obligatorio (solo si se pide factura).
        $response->assertSee('Correo de facturación <span>(obligatorio)</span>', false);
        $response->assertSee('Cédula, RUC o pasaporte <span>(obligatorio)</span>', false);
        $response->assertSee('estos campos son <strong>obligatorios</strong>', false);
    }
}
