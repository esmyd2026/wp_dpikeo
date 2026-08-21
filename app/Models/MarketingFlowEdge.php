<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingFlowEdge extends Model
{
    protected $fillable = [
        'flow_id',
        'source_node_uuid',
        'source_handle',
        'target_node_uuid',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(MarketingFlow::class, 'flow_id');
    }

    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(MarketingFlowNode::class, 'source_node_uuid', 'node_uuid');
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(MarketingFlowNode::class, 'target_node_uuid', 'node_uuid');
    }
}
