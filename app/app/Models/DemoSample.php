<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemoSample extends Model
{
    protected $fillable = [
        'sample_id', 'category_id', 'source_id', 'locale', 'question', 'split',
        'paraphrase_family_id', 'authoring_method', 'translation_status',
        'review_status', 'reviewer_name', 'reviewer_role', 'reviewed_at',
        'content_checksum',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function routingEvents(): HasMany
    {
        return $this->hasMany(RoutingEvent::class);
    }

    public function scopeRecruiterVisible(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale)
            ->where('split', 'visible')
            ->where('review_status', 'approved')
            ->when($locale === 'ckb', fn (Builder $builder) => $builder->where('translation_status', 'human_reviewed'));
    }
}
