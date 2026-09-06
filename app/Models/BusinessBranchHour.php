<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessBranchHour extends Model
{
    public const DAYS = [
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
    ];

    protected $fillable = [
        'business_branch_id',
        'day_of_week',
        'is_closed',
        'opens_at',
        'closes_at',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_closed' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessBranch::class, 'business_branch_id');
    }

    public function dayLabel(): string
    {
        return self::DAYS[$this->day_of_week] ?? (string) $this->day_of_week;
    }
}
