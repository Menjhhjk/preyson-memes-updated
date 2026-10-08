<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostingDay extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['posted_on' => 'date', 'reward' => 'array'];
    }
}
