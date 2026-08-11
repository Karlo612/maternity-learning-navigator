<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['slug', 'label_en', 'description_en', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class)->withPivot('display_order')->orderByPivot('display_order');
    }

    public function labelFor(string $locale): string
    {
        if ($locale === 'en') {
            return $this->label_en;
        }

        return $this->translations
            ->first(fn (CategoryTranslation $translation) => $translation->locale === $locale && $translation->review_status === 'approved')
            ?->label ?? $this->label_en;
    }
}
