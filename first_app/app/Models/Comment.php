<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    protected $fillable = ['post_id', 'user_id', 'body', 'edited_at', 'parent_id', 'reply_to_id', 'hearted_by_owner'];

    protected function casts(): array
    {
        return ['edited_at' => 'datetime', 'hearted_by_owner' => 'boolean'];
    }

    /** @return HasMany<Comment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    /** @return BelongsTo<Comment, $this> */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'reply_to_id');
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canBeDeletedBy(User $viewer): bool
    {
        return $viewer->canModerate() || $this->user_id === $viewer->id || $this->post?->user_id === $viewer->id;
    }
}
