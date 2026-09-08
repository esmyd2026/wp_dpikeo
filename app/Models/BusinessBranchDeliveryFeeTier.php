<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessBranchDeliveryFeeTier extends Model
{
    protected $fillable = [
        'business_branch_id',
        'from_km',
        'to_km',
        'price',
    ];

    protected $casts = [
        'from_km' => 'float',
        'to_km' => 'float',
        'price' => 'float',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessBranch::class, 'business_branch_id');
    }
}
