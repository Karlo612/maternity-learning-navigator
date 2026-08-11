<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Source extends Model
{
    protected $fillable = [
        'source_id', 'organisation', 'title', 'url', 'language', 'category',
        'authority', 'reuse_status', 'allowed_use', 'last_verified',
    ];

    protected $casts = ['last_verified' => 'date'];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot('display_order');
    }
}
