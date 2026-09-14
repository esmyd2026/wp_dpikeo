<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyStorefrontSetting extends Model
{
    protected $fillable = [
        'company_id', 'logo_path', 'hero_image_path', 'primary_color',
        'secondary_color', 'accent_color', 'custom_domain',
        'google_maps_api_key', 'google_maps_map_id', 'google_oauth_client_id',
        'google_oauth_client_secret', 'storefront_enabled',
    ];

    protected $casts = [
        'google_maps_api_key' => 'encrypted',
        'google_oauth_client_id' => 'encrypted',
        'google_oauth_client_secret' => 'encrypted',
        'storefront_enabled' => 'boolean',
    ];

    protected $hidden = ['google_maps_api_key', 'google_oauth_client_id', 'google_oauth_client_secret'];

    public function googleLoginEnabled(): bool
    {
        return filled($this->google_oauth_client_id) && filled($this->google_oauth_client_secret);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assetUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return asset(ltrim($path, '/'));
    }

    public function logoUrl(): ?string
    {
        return $this->assetUrl($this->logo_path);
    }

    public function heroImageUrl(): ?string
    {
        return $this->assetUrl($this->hero_image_path);
    }
}
