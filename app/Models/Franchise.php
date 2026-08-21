<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Franchise extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_default', 'is_active'];

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean'];

    public function products(): HasMany
    {
        return $this->hasMany(WhatsappPrice::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(WhatsappMenuItem::class);
    }
}
