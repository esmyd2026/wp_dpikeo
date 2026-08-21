<?php

namespace App\Services;

use App\Models\PricingSetting;
use App\Models\WhatsappBusinessProfile;

class OrderPdfSettingsService
{
    public function __construct(
        private PlanLimitsService $planLimits
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $config = config('order_pdf', []);
        $company = $config['company'] ?? [];

        return [
            'legal_name' => $company['legal_name'] ?? config('app.name', 'Mi Empresa'),
            'trade_name' => $company['trade_name'] ?? '',
            'ruc' => $company['ruc'] ?? '',
            'address' => $company['address'] ?? '',
            'city' => $company['city'] ?? 'Ecuador',
            'phone' => $company['phone'] ?? '',
            'email' => $company['email'] ?? '',
            'website' => $company['website'] ?? '',
            'document_title' => $config['document_title'] ?? 'ORDEN DE PEDIDO',
            'document_subtitle' => $config['document_subtitle'] ?? 'Documento de pedido comercial',
            'legal_footer' => $config['legal_footer'] ?? '',
            'iva_rate_percent' => (int) round(((float) ($config['iva_rate'] ?? 0.15)) * 100),
            'prices_include_iva' => (bool) ($config['prices_include_iva'] ?? false),
            'timezone' => $config['timezone'] ?? 'America/Guayaquil',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $stored = $this->storedRaw();
        $defaults = $this->defaults();
        $profile = WhatsappBusinessProfile::query()->first();
        $meta = is_array($profile?->metadata) ? $profile->metadata : [];

        $merged = array_merge($defaults, array_filter(
            $stored,
            fn ($value) => $value !== null && $value !== ''
        ));

        if (empty($merged['legal_name']) || $merged['legal_name'] === $defaults['legal_name']) {
            $merged['legal_name'] = $meta['legal_name']
                ?? $profile?->business_name
                ?? $merged['legal_name'];
        }

        if (empty($merged['trade_name'])) {
            $merged['trade_name'] = $meta['trade_name']
                ?? $profile?->display_name
                ?? $profile?->business_name
                ?? '';
        }

        if (empty($merged['phone'])) {
            $merged['phone'] = $profile?->phone_number ?? '';
        }

        foreach (['ruc', 'address', 'city', 'email', 'website'] as $field) {
            if (empty($merged[$field]) && !empty($meta[$field])) {
                $merged[$field] = $meta[$field];
            }
        }

        $merged['iva_rate_percent'] = max(0, min(100, (int) ($merged['iva_rate_percent'] ?? 15)));
        $merged['prices_include_iva'] = (bool) ($merged['prices_include_iva'] ?? false);

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function companyProfile(): array
    {
        $settings = $this->get();

        return [
            'legal_name' => $settings['legal_name'],
            'trade_name' => $settings['trade_name'],
            'ruc' => $settings['ruc'],
            'address' => $settings['address'],
            'city' => $settings['city'],
            'phone' => $settings['phone'],
            'email' => $settings['email'],
            'website' => $settings['website'],
        ];
    }

    public function ivaRate(): float
    {
        return max(0, min(1, $this->get()['iva_rate_percent'] / 100));
    }

    public function pricesIncludeIva(): bool
    {
        return (bool) $this->get()['prices_include_iva'];
    }

    public function timezone(): string
    {
        return $this->get()['timezone'] ?: 'America/Guayaquil';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        $payload = [
            'legal_name' => trim((string) ($data['legal_name'] ?? '')),
            'trade_name' => trim((string) ($data['trade_name'] ?? '')),
            'ruc' => trim((string) ($data['ruc'] ?? '')),
            'address' => trim((string) ($data['address'] ?? '')),
            'city' => trim((string) ($data['city'] ?? 'Ecuador')),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'website' => trim((string) ($data['website'] ?? '')),
            'document_title' => trim((string) ($data['document_title'] ?? 'ORDEN DE PEDIDO')),
            'document_subtitle' => trim((string) ($data['document_subtitle'] ?? '')),
            'legal_footer' => trim((string) ($data['legal_footer'] ?? '')),
            'iva_rate_percent' => max(0, min(100, (int) ($data['iva_rate_percent'] ?? 15))),
            'prices_include_iva' => !empty($data['prices_include_iva']),
            'timezone' => trim((string) ($data['timezone'] ?? 'America/Guayaquil')) ?: 'America/Guayaquil',
        ];

        $limits = $this->planLimits->platformLimitsRaw();
        $limits['order_pdf'] = $payload;

        PricingSetting::current()->update(['platform_limits' => $limits]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storedRaw(): array
    {
        $raw = $this->planLimits->platformLimitsRaw()['order_pdf'] ?? [];

        return is_array($raw) ? $raw : [];
    }
}
