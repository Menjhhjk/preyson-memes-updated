<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    protected $fillable = ['user_id', 'title', 'media_path', 'media_type'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canBeManagedBy(User $user): bool
    {
        return $user->is_admin || ($this->user_id !== null && (int) $this->user_id === (int) $user->id);
    }
}
