<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $table = 'feedback';

    protected $fillable = ['routing_event_id', 'helpful', 'reason_code'];

    protected $casts = ['helpful' => 'boolean'];
}
