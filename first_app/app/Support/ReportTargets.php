<?php

namespace App\Support;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Str;

class ReportTargets
{
    public static function find(string $type, int $id): Post|Comment|User
    {
        return match ($type) {
            'post' => Post::with('user')->findOrFail($id),
            'comment' => Comment::with(['user', 'post.user'])->findOrFail($id),
            'account' => User::findOrFail($id),
            default => abort(404),
        };
    }

    public static function authorize(Post|Comment|User $target, User $viewer): void
    {
        if ($target instanceof Post) {
            abort_unless($target->visibleTo($viewer), 404);
        } elseif ($target instanceof Comment) {
            abort_unless($target->post && $target->post->visibleTo($viewer), 404);
            abort_unless($target->post->comments_enabled || $target->post->canBeManagedBy($viewer), 404);
        }
    }

    public static function label(Post|Comment|User $target): string
    {
        return match (true) {
            $target instanceof Post => $target->title,
            $target instanceof Comment => 'Comment by '.($target->user->username ?? 'Former member').': '.Str::limit($target->body, 120),
            default => $target->username,
        };
    }

    public static function snapshot(Post|Comment|User $target): string
    {
        return match (true) {
            $target instanceof Post => $target->title.' — '.($target->user->username ?? 'Former member'),
            $target instanceof Comment => $target->body,
            default => $target->username,
        };
    }

    public static function url(Post|Comment|User $target): string
    {
        return match (true) {
            $target instanceof Post => route('posts.show', $target),
            $target instanceof Comment => route('comments.show', $target),
            default => route('profiles.show', $target),
        };
    }

    /** @param iterable<Report> $rows
     * @return array<string, Post|Comment|User>
     */
    public static function forRows(iterable $rows): array
    {
        $ids = ['post' => [], 'comment' => [], 'account' => []];
        foreach ($rows as $row) {
            $ids[$row->target_type][] = $row->target_id;
        }
        $targets = [];
        foreach (['post' => Post::class, 'comment' => Comment::class, 'account' => User::class] as $type => $class) {
            $query = $class::whereIn('id', $ids[$type]);
            if ($type === 'comment') {
                $query->with('user');
            }
            foreach ($query->get() as $model) {
                $targets[$type.':'.$model->id] = $model;
            }
        }

        return $targets;
    }
}
