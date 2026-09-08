<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public static function ensureDefaultForProfile(WhatsappBusinessProfile $profile): self
    {
        return static::firstOrCreate(
            ['business_profile_id' => $profile->id, 'code' => 'MATRIZ'],
            [
                'name' => 'Matriz',
                'phone' => $profile->phone_number,
                'is_default' => true,
                'is_active' => true,
                'dine_in_enabled' => true,
            ]
        );
    }

    public function scopeForUserAccess($query, User $user, ?int $businessProfileId)
    {
        $query->where('business_profile_id', $businessProfileId);
        $branchIds = $user->accessibleBranchIds($businessProfileId);

        return $branchIds === null ? $query : $query->whereIn('id', $branchIds);
    }

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessProfile::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(WhatsappCart::class, 'branch_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_branch_user')->withTimestamps();
    }

    public function hours(): HasMany
    {
        return $this->hasMany(BusinessBranchHour::class)->orderBy('day_of_week');
    }

    /** Tramos de precio de delivery por km ("desde X hasta Y km = $"), en orden. */
    public function deliveryFeeTiers(): HasMany
    {
        return $this->hasMany(BusinessBranchDeliveryFeeTier::class)->orderBy('from_km');
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
