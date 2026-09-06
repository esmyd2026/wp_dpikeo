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

        // Meta rechaza (#131009) un encabezado de imagen en mensajes tipo
        // "list" (solo admite encabezado de texto ahí, a diferencia de los
        // mensajes de botones) -- si el paso configuró una imagen igual, se
        // manda como mensaje de imagen aparte, justo antes de la lista, para
        // no perder la imagen que cargó el admin en el flujo.
        if ($step->getInteractiveType() === 'list' && $step->getHeaderMode() === 'image' && $contact?->phone_number) {
            $imageUrl = $step->getHeaderImageUrl();
            if ($imageUrl) {
                $this->sendMessage($contact->phone_number, \App\Services\Whatsapp\WhatsappMessagePayload::image($imageUrl));
            }
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

            // "horario_atencion" es un botón especial: en vez de depender de
            // un texto fijo que el admin tiene que mantener sincronizado a
            // mano, se arma en vivo desde las Sucursales (ver
            // BusinessBranch::hoursByDay()). Si el admin igual escribió un
            // texto propio para esta clave en "Acciones personalizadas", ese
            // gana -- esto es solo el valor por defecto.
            if (!$message && $customKey === 'horario_atencion') {
                $message = $this->renderBusinessHoursMessage();
            }

            if ($message) {
                return [
                    'type' => 'text',
                    'text' => ['body' => MarketingFlowStep::interpolate($message, $this->marketingFlowVariables($contact))],
                ];
            }
        }

        return null;
    }

    /** @return string|null Null si esta empresa todavía no tiene ninguna sucursal activa configurada. */
    protected function renderBusinessHoursMessage(): ?string
    {
        if (!$this->businessProfile) {
            return null;
        }

        $branches = \App\Models\BusinessBranch::where('business_profile_id', $this->businessProfile->id)
            ->where('is_active', true)
            ->with('hours')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        if ($branches->isEmpty()) {
            return null;
        }

        $showBranchNames = $branches->count() > 1;
        $lines = ['⏰ *Horario de atención*'];

        foreach ($branches as $branch) {
            if ($showBranchNames) {
                $lines[] = '';
                $lines[] = "*{$branch->name}*";
            }

            foreach ($branch->hoursByDay() as $hour) {
                $label = $hour->dayLabel();
                if ($hour->is_closed) {
                    $lines[] = "{$label}: Cerrado";
                } elseif ($hour->opens_at && $hour->closes_at) {
                    $opens = \Illuminate\Support\Carbon::parse($hour->opens_at)->format('H:i');
                    $closes = \Illuminate\Support\Carbon::parse($hour->closes_at)->format('H:i');
                    $lines[] = "{$label}: {$opens} - {$closes}";
                } else {
                    $lines[] = "{$label}: Sin horario definido";
                }
            }
        }

        return implode("\n", $lines);
    }

    protected function resolveFlowButtonAction(string $buttonId): ?string
    {
        $row = $this->findFlowMenuRow($buttonId);

        return $row ? MarketingButtonAction::resolve($buttonId, $row['action'] ?? null) : null;
    }
}
