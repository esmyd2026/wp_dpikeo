<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDailyCounter extends Model
{
    protected $fillable = ['business_date', 'last_number', 'reset_at', 'reset_by_user_id'];

    protected $casts = [
        'business_date' => 'date',
        'reset_at' => 'datetime',
    ];
}
