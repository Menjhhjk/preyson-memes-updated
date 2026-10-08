<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = ['user_id', 'title', 'media_path', 'media_type', 'comments_enabled'];

    protected function casts(): array
    {
        return ['comments_enabled' => 'boolean', 'ten_reactions_notified_at' => 'datetime'];
    }

    /** @return BelongsTo<Comment, $this> */
    public function pinnedComment(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'pinned_comment_id');
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function visibleTo(?User $viewer): bool
    {
        return $this->user === null || $this->user->profileVisibleTo($viewer);
    }

    /** @param Builder<Post> $query */
    public function scopeVisibleTo(Builder $query, ?User $viewer): void
    {
        if ($viewer?->canModerate()) {
            return;
        }
        $query->where(fn ($query) => $query->whereNull('user_id')
            ->orWhereHas('user', fn ($owner) => $owner->where('profile_visibility', 'public'))
            ->when($viewer, fn ($query) => $query->orWhere('user_id', $viewer->id)));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Reaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function canBeManagedBy(User $user): bool
    {
        return $user->canModerate() || ($this->user_id !== null && (int) $this->user_id === (int) $user->id);
    }
}
