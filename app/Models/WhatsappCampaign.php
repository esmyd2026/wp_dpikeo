<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_profile_id',
        'name',
        'description',
        'message_type',
        'message_content',
        'image_path',
        'product_id',
        'button_text',
        'template_id',
        'template_variables',
        'recipient_type',
        'recipient_filters',
        'selected_contacts',
        'status',
        'scheduled_at',
        'sent_at',
        'total_recipients',
        'sent_count',
        'failed_count',
        'delivered_count',
        'read_count',
        'metadata',
        'error_details'
    ];

    protected $casts = [
        'template_variables' => 'array',
        'recipient_filters' => 'array',
        'selected_contacts' => 'array',
        'metadata' => 'array',
        'error_details' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime'
    ];

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessProfile::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(WhatsappPrice::class, 'product_id');
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->total_recipients === 0) {
            return 0;
        }
        return round(($this->sent_count / $this->total_recipients) * 100, 2);
    }

    public function getDeliveryRateAttribute(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }
        return round(($this->delivered_count / $this->sent_count) * 100, 2);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/' . ltrim($this->image_path, '/')) : null;
    }

    public function getReadRateAttribute(): float
    {
        if ($this->delivered_count === 0) {
            return 0;
        }
        return round(($this->read_count / $this->delivered_count) * 100, 2);
    }
}
