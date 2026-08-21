<?php

namespace App\Services\Concerns;

use App\Enums\MarketingButtonAction;
use App\Enums\MarketingStepKey;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowStep;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Services\MarketingFlowPayloadBuilder;

trait UsesMarketingFlow
{
    protected ?MarketingFlow $marketingFlowCache = null;

    protected function resolveMarketingFlow(): ?MarketingFlow
    {
        if ($this->marketingFlowCache) {
            return $this->marketingFlowCache;
        }

        if (!$this->businessProfile) {
            return null;
        }

        $this->marketingFlowCache = MarketingFlow::query()
            ->where('business_profile_id', $this->businessProfile->id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->with('steps')
            ->first();

        if (!$this->marketingFlowCache) {
            $this->marketingFlowCache = MarketingFlow::query()
                ->where('business_profile_id', $this->businessProfile->id)
                ->where('is_active', true)
                ->with('steps')
                ->first();
        }

        return $this->marketingFlowCache;
    }

    protected function getMarketingStep(string $stepKey): ?MarketingFlowStep
    {
        $flow = $this->resolveMarketingFlow();
        if (!$flow) {
            return null;
        }

        return $flow->steps->firstWhere('step_key', $stepKey);
    }

    protected function marketingFlowVariables(?WhatsappContact $contact = null): array
    {
        $chatbotConfig = $this->businessProfile
            ? WhatsappChatbotConfig::where('business_profile_id', $this->businessProfile->id)->first()
            : WhatsappChatbotConfig::first();

        $meta = is_array($this->businessProfile?->metadata) ? $this->businessProfile->metadata : [];

        return [
            'nombre' => $contact?->name ?? 'Cliente',
            'nombre_bot' => $chatbotConfig?->bot_name ?: 'Asistente virtual',
            'nombre_empresa' => $this->businessProfile?->business_name ?? 'Tienda',
            'telefono_soporte' => $meta['whatsapp']
                ?? $this->businessProfile?->phone_number
                ?? config('whatsapp.demo_whatsapp_number', ''),
            'horario_atencion' => $meta['business_hours'] ?? 'Lunes a viernes 9:00 - 18:00',
            'total' => '0.00',
            'moneda' => 'USD',
            'cantidad_items' => '0',
            'numero_pedido' => '-',
            'estado_pedido' => '-',
        ];
    }

    protected function buildMarketingStepPayload(string $stepKey, ?WhatsappContact $contact = null, ?string $bodyOverride = null): ?array
    {
        $step = $this->getMarketingStep($stepKey);
        if (!$step || !$step->is_enabled) {
            return null;
        }

        return app(MarketingFlowPayloadBuilder::class)->build(
            $step,
            $this->marketingFlowVariables($contact),
            $bodyOverride
        );
    }

    protected function findFlowMenuRow(string $buttonId): ?array
    {
        $flow = $this->resolveMarketingFlow();
        if (!$flow) {
            return null;
        }

        foreach ($flow->steps as $step) {
            $row = $step->findMenuRow($buttonId);
            if ($row) {
                return array_merge($row, ['_step_key' => $step->step_key]);
            }
        }

        return null;
    }

    protected function resolveFlowInlineResponse(string $buttonId, ?WhatsappContact $contact = null): ?array
    {
        $row = $this->findFlowMenuRow($buttonId);
        if (!$row) {
            return null;
        }

        if (!empty($row['response_message'])) {
            return [
                'type' => 'text',
                'text' => ['body' => MarketingFlowStep::interpolate($row['response_message'], $this->marketingFlowVariables($contact))],
            ];
        }

        $action = MarketingButtonAction::resolve($buttonId, $row['action'] ?? null);
        if (str_starts_with($action, 'custom:')) {
            $customKey = substr($action, 7);
            $mainStep = $this->getMarketingStep(MarketingStepKey::MAIN_MENU);
            $message = $mainStep?->getCustomActions()[$customKey] ?? null;
            if ($message) {
                return [
                    'type' => 'text',
                    'text' => ['body' => MarketingFlowStep::interpolate($message, $this->marketingFlowVariables($contact))],
                ];
            }
        }

        return null;
    }

    protected function resolveFlowButtonAction(string $buttonId): ?string
    {
        $row = $this->findFlowMenuRow($buttonId);

        return $row ? MarketingButtonAction::resolve($buttonId, $row['action'] ?? null) : null;
    }
}
