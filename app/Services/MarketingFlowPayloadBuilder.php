<?php

namespace App\Services;

use App\Models\MarketingFlowStep;
use App\Services\Whatsapp\WhatsappMessagePayload;

class MarketingFlowPayloadBuilder
{
    public function build(MarketingFlowStep $step, array $vars, ?string $bodyOverride = null): array
    {
        $body = $bodyOverride ?? $step->renderMessage($vars);
        $header = $step->getRenderedHeader($vars);
        $footer = $step->getRenderedFooter($vars);

        if ($step->getHeaderMode() === 'default') {
            $header = [
                'type' => 'text',
                'text' => $vars['nombre_empresa'] ?? 'Asistente de ventas',
            ];
        }

        return match ($step->getInteractiveType()) {
            'list' => WhatsappMessagePayload::list(
                $body,
                $step->getListConfig()['button'] ?? 'Ver opciones',
                $step->getListConfig()['sections'] ?? [],
                $header,
                $footer
            ),
            'flow' => $this->buildFlowPayload($step, $body, $header, $footer),
            'cta_url' => WhatsappMessagePayload::ctaUrl(
                $body,
                $step->getCtaConfig()['button_text'] ?? 'Abrir enlace',
                $step->getCtaConfig()['url'] ?? 'https://example.com',
                $header,
                $footer
            ),
            'text' => ($imageUrl = $step->getMessageImageUrl())
                ? WhatsappMessagePayload::image($imageUrl, $body !== '' ? $body : null)
                : WhatsappMessagePayload::text($body),
            default => !empty($step->getButtons())
                ? WhatsappMessagePayload::buttons($body, $step->getButtons(), $header, $footer)
                : WhatsappMessagePayload::text($body),
        };
    }

    protected function buildFlowPayload(MarketingFlowStep $step, string $body, ?array $header, ?string $footer): array
    {
        $flow = $step->getFlowConfig();
        $flowId = $flow['flow_id'] ?? '';
        if ($flowId === '') {
            return WhatsappMessagePayload::text($body . "\n\n⚠️ Configure el Flow ID de Meta en el panel.");
        }

        return WhatsappMessagePayload::flow(
            $body,
            $flowId,
            $flow['flow_token'] ?? ('marketing_' . $step->step_key),
            $flow['cta'] ?? 'Continuar',
            $flow['flow_message_version'] ?? '3',
            $flow['flow_action'] ?? 'navigate',
            $flow['flow_action_payload'] ?? null,
            $header,
            $footer
        );
    }
}
