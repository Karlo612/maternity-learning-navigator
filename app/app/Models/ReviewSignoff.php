<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewSignoff extends Model
{
    protected $fillable = ['gate', 'reviewer_role', 'status', 'evidence_checksum', 'reviewed_at'];

    protected $casts = ['reviewed_at' => 'datetime'];
}
