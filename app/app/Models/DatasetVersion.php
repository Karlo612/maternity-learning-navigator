<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatasetVersion extends Model
{
    protected $fillable = [
        'version', 'checksum', 'eligible_rows', 'status', 'metrics', 'released_at',
    ];

    protected $casts = [
        'metrics' => 'array',
        'released_at' => 'datetime',
    ];
}
