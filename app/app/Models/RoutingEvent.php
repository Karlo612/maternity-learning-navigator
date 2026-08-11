<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingEvent extends Model
{
    protected $fillable = [
        'request_id', 'mode', 'demo_sample_id', 'category_id', 'model_version_id', 'locale', 'status',
        'routing_confidence', 'confidence_band', 'question_fingerprint', 'latency_ms',
    ];

    protected $hidden = ['question_fingerprint'];

    protected $casts = ['routing_confidence' => 'float'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(ModelVersion::class);
    }

    public function demoSample(): BelongsTo
    {
        return $this->belongsTo(DemoSample::class);
    }
}
