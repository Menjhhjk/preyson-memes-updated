<?php

namespace App\Support;

use App\Models\Comment;
use App\Models\Post;

class CommentLinks
{
    public static function url(Post $post, ?Comment $comment = null): string
    {
        if ($comment?->parent_id) {
            $count = Comment::where('parent_id', $comment->parent_id)->where('id', '<=', $comment->id)->count();

            return route('comments.replies', ['comment' => $comment->parent_id, 'page' => max(1, (int) ceil($count / 20))]).'#comment-'.$comment->id;
        }
        $count = $post->comments()->whereNull('parent_id')->when($comment, fn ($query) => $query->where('id', '<=', $comment->id))->count();

        return route('posts.show', ['post' => $post, 'page' => max(1, (int) ceil($count / 20))]).'#comments';
    }
}
