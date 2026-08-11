<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelVersion extends Model
{
    protected $fillable = [
        'model_id', 'version', 'role', 'status', 'artifact_checksum',
        'metrics', 'limitations', 'serving_default',
    ];

    protected $casts = ['metrics' => 'array', 'serving_default' => 'boolean'];
}
