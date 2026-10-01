<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    public function show(Request $request, Comment $comment): RedirectResponse
    {
        abort_unless($comment->post && $comment->post->visibleTo($request->user()), 404);
        abort_unless($comment->post->comments_enabled || ($request->user() && $comment->post->canBeManagedBy($request->user())), 404);

        return $this->discussion($comment->post, $comment);
    }

    public function store(Request $request, Post $post): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $post, $data) {
            $post = Post::query()->lockForUpdate()->findOrFail($post->id);
            abort_unless($post->visibleTo($request->user()), 404);
            abort_unless($post->comments_enabled, 403, 'Comments are turned off for this post.');
            $post->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        });

        return $this->discussion($post)->with('success', 'Comment added.');
    }

    public function update(Request $request, Comment $comment): RedirectResponse
    {
        abort_unless($comment->post && $comment->post->visibleTo($request->user()), 404);
        abort_unless($comment->user_id === $request->user()->id && $comment->post->comments_enabled, 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $comment->update([...$data, 'edited_at' => now()]);

        return $this->discussion($comment->post, $comment)->with('success', 'Comment updated.');
    }

    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        abort_unless($comment->post && $comment->post->visibleTo($request->user()), 404);
        abort_unless($comment->canBeDeletedBy($request->user()), 403);
        $post = $comment->post;
        $comment->delete();

        return $this->discussion($post)->with('success', 'Comment deleted.');
    }

    private function discussion(Post $post, ?Comment $comment = null): RedirectResponse
    {
        $count = $post->comments()->when($comment, fn ($query) => $query->where('id', '<=', $comment->id))->count();

        return redirect(route('posts.show', ['post' => $post, 'page' => max(1, (int) ceil($count / 20))]).'#comments');
    }
}
