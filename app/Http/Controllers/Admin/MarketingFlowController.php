<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarketingButtonAction;
use App\Enums\MarketingStepKey;
use App\Http\Controllers\Controller;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowStep;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappChatbotConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MarketingFlowController extends Controller
{
    public function edit()
    {
        $profile = WhatsappBusinessProfile::first();
        $flow = null;

        if ($profile) {
            $flow = MarketingFlow::query()
                ->where('business_profile_id', $profile->id)
                ->where('is_default', true)
                ->with('steps')
                ->first()
                ?? MarketingFlow::query()
                    ->where('business_profile_id', $profile->id)
                    ->with('steps')
                    ->first();
        }

        return view('admin.marketing-flow.edit', [
            'profile' => $profile,
            'flow' => $flow,
            'chatbotConfig' => $profile
                ? WhatsappChatbotConfig::where('business_profile_id', $profile->id)->first()
                : WhatsappChatbotConfig::first(),
            'stepLabels' => MarketingStepKey::all(),
            'scenarioGroups' => MarketingStepKey::scenarioGroups(),
            'stepIcons' => MarketingStepKey::icons(),
            'buttonActions' => MarketingButtonAction::labels(),
            'interactiveTypes' => [
                'button' => 'Botones (máx. 3)',
                'list' => 'Lista interactiva (hasta 10 opciones)',
                'flow' => 'WhatsApp Flow / Formulario Meta',
                'cta_url' => 'Botón con enlace URL',
                'text' => 'Solo texto',
            ],
        ]);
    }

    public function update(Request $request)
    {
        $profile = WhatsappBusinessProfile::firstOrFail();

        $data = $request->validate([
            'flow_name' => 'required|string|max:255',
            'steps' => 'required|array',
            'steps.*.step_key' => 'required|string',
            'steps.*.name' => 'required|string|max:255',
            'steps.*.message_template' => 'nullable|string',
            'steps.*.sort_order' => 'required|integer|min:0',
            'steps.*.is_enabled' => 'nullable|boolean',
            'steps.*.interactive_type' => 'nullable|string|in:button,list,flow,cta_url,text',
            'steps.*.buttons' => 'nullable|string',
            'steps.*.list_button' => 'nullable|string|max:20',
            'steps.*.list_sections' => 'nullable|string',
            'steps.*.flow_id' => 'nullable|string|max:255',
            'steps.*.flow_token' => 'nullable|string|max:255',
            'steps.*.flow_cta' => 'nullable|string|max:20',
            'steps.*.quick_order_flow_id' => 'nullable|string|max:255',
            'steps.*.quick_order_flow_cta' => 'nullable|string|max:20',
            'steps.*.cta_button_text' => 'nullable|string|max:20',
            'steps.*.cta_url' => 'nullable|url|max:500',
            'steps.*.header_type' => 'nullable|string|in:default,none,text,image',
            'steps.*.header_text' => 'nullable|string|max:60',
            'steps.*.header_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'steps.*.remove_header_image' => 'nullable|boolean',
            'steps.*.message_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'steps.*.remove_message_image' => 'nullable|boolean',
            'steps.*.footer_text' => 'nullable|string|max:60',
            'steps.*.success_message' => 'nullable|string',
            'steps.*.custom_actions' => 'nullable|string',
            'steps.*.catalog_source' => 'nullable|string|in:products,categories,manual',
            'steps.*.max_product_rows' => 'nullable|integer|min:1|max:8',
            'steps.*.include_navigation' => 'nullable|boolean',
            'steps.*.require_proof' => 'nullable|boolean',
            'steps.*.require_for_methods' => 'nullable|array',
            'steps.*.require_for_methods.*' => 'string|in:transferencia,efectivo,tarjeta',
        ]);

        $flow = MarketingFlow::firstOrCreate(
            ['business_profile_id' => $profile->id, 'is_default' => true],
            ['name' => $data['flow_name'], 'is_active' => true]
        );

        $flow->update(['name' => $data['flow_name'], 'is_active' => true]);

        foreach ($data['steps'] as $stepData) {
            $existingStep = MarketingFlowStep::where('flow_id', $flow->id)
                ->where('step_key', $stepData['step_key'])
                ->first();

            $headerBundle = $this->buildStepHeader($request, $stepData, $existingStep, (int) $profile->id);
            $messageImagePath = $this->buildStepMessageImage($request, $stepData, $existingStep, (int) $profile->id);

            $config = [
                'interactive_type' => $stepData['interactive_type'] ?? 'button',
                'buttons' => $this->parseButtons($stepData['buttons'] ?? ''),
                'header_mode' => $headerBundle['mode'],
                'header' => $headerBundle['header'],
                'footer' => $stepData['footer_text'] ?? null,
                'success_message' => $stepData['success_message'] ?? null,
                'message_image_path' => $messageImagePath,
            ];

            if (($stepData['interactive_type'] ?? 'button') === 'list') {
                $config['list'] = [
                    'button' => $stepData['list_button'] ?? 'Ver opciones',
                    'sections' => $this->parseListSections($stepData['list_sections'] ?? ''),
                ];
            }

            if (($stepData['interactive_type'] ?? '') === 'flow') {
                $config['flow'] = array_filter([
                    'flow_id' => $stepData['flow_id'] ?? null,
                    'flow_token' => $stepData['flow_token'] ?? null,
                    'cta' => $stepData['flow_cta'] ?? 'Continuar',
                    'flow_message_version' => '3',
                    'flow_action' => 'navigate',
                ]);
            }

            if (($stepData['interactive_type'] ?? '') === 'cta_url') {
                $config['cta_url'] = [
                    'button_text' => $stepData['cta_button_text'] ?? 'Abrir enlace',
                    'url' => $stepData['cta_url'] ?? '',
                ];
            }

            $customActions = $this->parseCustomActions($stepData['custom_actions'] ?? '');
            if ($customActions !== []) {
                $config['custom_actions'] = $customActions;
            }

            if ($stepData['step_key'] === MarketingStepKey::PRODUCTS_MENU) {
                $config['catalog_source'] = $stepData['catalog_source'] ?? 'products';
                $config['max_product_rows'] = (int) ($stepData['max_product_rows'] ?? 8);
                $config['include_navigation'] = !empty($stepData['include_navigation']);
                $config['quick_order_flow'] = array_filter([
                    'flow_id' => $stepData['quick_order_flow_id'] ?? null,
                    'cta' => $stepData['quick_order_flow_cta'] ?? 'Pedir ahora',
                    'flow_token' => 'dpikeos_quick_order',
                ]);
            }

            if ($stepData['step_key'] === MarketingStepKey::PAYMENT_PROOF) {
                $config['require_proof'] = !empty($stepData['require_proof']);
                $config['require_for_methods'] = array_values(
                    $stepData['require_for_methods'] ?? ['transferencia', 'tarjeta']
                );
            }

            MarketingFlowStep::updateOrCreate(
                ['flow_id' => $flow->id, 'step_key' => $stepData['step_key']],
                [
                    'name' => $stepData['name'],
                    'message_template' => $stepData['message_template'] ?? '',
                    'sort_order' => (int) $stepData['sort_order'],
                    'is_enabled' => !empty($stepData['is_enabled']),
                    'config' => array_filter($config, fn ($v) => $v !== null),
                ]
            );
        }

        return redirect()
            ->route('admin.marketing-flow.edit')
            ->with('success', 'Flujo del bot actualizado correctamente.');
    }

    protected function buildStepHeader(Request $request, array $stepData, ?MarketingFlowStep $existingStep, int $profileId): array
    {
        $stepKey = $stepData['step_key'];
        $headerType = $stepData['header_type'] ?? 'default';

        if ($headerType === 'default') {
            return ['mode' => 'default', 'header' => null];
        }

        if ($headerType === 'none') {
            $this->deleteHeaderImage($existingStep);

            return ['mode' => 'none', 'header' => null];
        }

        if ($headerType === 'text') {
            $this->deleteHeaderImage($existingStep);
            $text = trim($stepData['header_text'] ?? '');

            return [
                'mode' => 'text',
                'header' => $text !== '' ? ['type' => 'text', 'text' => $text] : null,
            ];
        }

        if ($headerType === 'image') {
            $file = $request->file("steps.{$stepKey}.header_image");

            if ($file) {
                $this->deleteHeaderImage($existingStep);
                $path = $file->store("marketing-flow-headers/{$profileId}", 'public');

                return [
                    'mode' => 'image',
                    'header' => ['type' => 'image', 'image_path' => $path],
                ];
            }

            if (!empty($stepData['remove_header_image'])) {
                $this->deleteHeaderImage($existingStep);

                return ['mode' => 'none', 'header' => null];
            }

            $existingPath = $existingStep?->config['header']['image_path'] ?? null;
            if ($existingPath) {
                return [
                    'mode' => 'image',
                    'header' => ['type' => 'image', 'image_path' => $existingPath],
                ];
            }

            return ['mode' => 'none', 'header' => null];
        }

        return ['mode' => 'default', 'header' => null];
    }

    protected function deleteHeaderImage(?MarketingFlowStep $step): void
    {
        $path = $step?->config['header']['image_path'] ?? null;
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function buildStepMessageImage(Request $request, array $stepData, ?MarketingFlowStep $existingStep, int $profileId): ?string
    {
        $stepKey = $stepData['step_key'];
        $file = $request->file("steps.{$stepKey}.message_image");

        if ($file) {
            $this->deleteMessageImage($existingStep);

            return $file->store("marketing-flow-messages/{$profileId}", 'public');
        }

        if (!empty($stepData['remove_message_image'])) {
            $this->deleteMessageImage($existingStep);

            return null;
        }

        return $existingStep?->config['message_image_path'] ?? null;
    }

    protected function deleteMessageImage(?MarketingFlowStep $step): void
    {
        $path = $step?->config['message_image_path'] ?? null;
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function parseButtons(string $raw): array
    {
        $buttons = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $buttons[] = [
                'id' => $parts[0] ?? '',
                'title' => $parts[1] ?? '',
                'action' => $parts[2] ?? ($parts[0] ?? ''),
                'response_message' => $parts[3] ?? null,
            ];
        }

        return $buttons;
    }

    protected function parseListSections(string $raw): array
    {
        $sections = [];
        $current = null;

        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '## ')) {
                if ($current) {
                    $sections[] = $current;
                }
                $current = ['title' => trim(substr($line, 3)), 'rows' => []];
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $row = [
                'id' => $parts[0] ?? '',
                'title' => $parts[1] ?? '',
                'description' => $parts[2] ?? '',
                'action' => $parts[3] ?? ($parts[0] ?? ''),
            ];
            if (!empty($parts[4])) {
                $row['response_message'] = $parts[4];
            }
            if (!$current) {
                $current = ['title' => 'Opciones', 'rows' => []];
            }
            $current['rows'][] = $row;
        }

        if ($current) {
            $sections[] = $current;
        }

        return $sections;
    }

    protected function parseCustomActions(string $raw): array
    {
        $actions = [];

        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, '|')) {
                continue;
            }

            [$key, $message] = array_map('trim', explode('|', $line, 2));
            if ($key !== '' && $message !== '') {
                $actions[$key] = $message;
            }
        }

        return $actions;
    }
}
