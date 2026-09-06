<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDailyCounter extends Model
{
    protected $fillable = ['business_date', 'last_number', 'reset_at', 'reset_by_user_id'];

    protected $casts = [
        // Formato explícito Y-m-d: sin esto, el cast 'date' genérico puede
        // serializar con hora incluida al guardar (según el driver), y el
        // firstOrCreate()/where() de DailyOrderNumberService que compara
        // contra un string "Y-m-d" plano nunca encontraba la fila que él
        // mismo acababa de crear.
        'business_date' => 'date:Y-m-d',
        'reset_at' => 'datetime',
    ];
}
