<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryTranslation extends Model
{
    public $timestamps = false;

    protected $fillable = ['category_id', 'locale', 'label', 'description', 'review_status', 'reviewed_at'];

    protected $casts = ['reviewed_at' => 'datetime'];
}
