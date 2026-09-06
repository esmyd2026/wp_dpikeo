<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessBranch extends Model
{
    protected $fillable = [
        'business_profile_id', 'name', 'code', 'phone', 'address', 'is_default', 'is_active',
        'latitude', 'longitude', 'delivery_fee_per_unit', 'delivery_fee_km_unit', 'delivery_fee_minimum',
        'dine_in_enabled',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'dine_in_enabled' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'delivery_fee_per_unit' => 'float',
        'delivery_fee_km_unit' => 'float',
        'delivery_fee_minimum' => 'float',
    ];

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessProfile::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(WhatsappCart::class, 'branch_id');
    }

    public function hours(): HasMany
    {
        return $this->hasMany(BusinessBranchHour::class)->orderBy('day_of_week');
    }

    /** Los 7 días siempre presentes y en orden, aunque todavía no tengan fila guardada (sucursal recién creada). */
    public function hoursByDay(): array
    {
        $existing = $this->hours->keyBy('day_of_week');

        $result = [];
        foreach (array_keys(BusinessBranchHour::DAYS) as $day) {
            $result[$day] = $existing->get($day) ?? new BusinessBranchHour([
                'business_branch_id' => $this->id,
                'day_of_week' => $day,
                'is_closed' => false,
            ]);
        }

        return $result;
    }
}
