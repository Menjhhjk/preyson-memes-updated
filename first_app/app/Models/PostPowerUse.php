<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** @property Carbon|null $expires_at */
class PostPowerUse extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effect' => 'array', 'expires_at' => 'datetime'];
    }
}
