<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappTemplateApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'status',
        'rejection_reason'
    ];

    public function template()
    {
        return $this->belongsTo(WhatsappTemplate::class);
    }
}
