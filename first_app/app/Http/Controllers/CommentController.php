<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Support\CommentLinks;
use App\Support\MemberInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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
        $data = $request->validate(['body' => ['required', 'string', 'max:2000'], 'reply_to_id' => ['nullable', 'integer']]);
        $comment = DB::transaction(function () use ($request, $post, $data) {
            $post = Post::query()->lockForUpdate()->findOrFail($post->id);
            abort_unless($post->visibleTo($request->user()), 404);
            abort_unless($post->comments_enabled, 403, 'Comments are turned off for this post.');
            $replyTo = isset($data['reply_to_id']) ? $post->comments()->where('id', $data['reply_to_id'])->first() : null;
            if (isset($data['reply_to_id']) && ! $replyTo) {
                throw ValidationException::withMessages(['reply_to_id' => 'That comment is no longer available on this post.']);
            }
            $comment = $post->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body'],
                'parent_id' => $replyTo ? ($replyTo->parent_id ?? $replyTo->id) : null, 'reply_to_id' => $replyTo?->id]);
            $recipients = array_unique(array_filter([$post->user_id, $replyTo?->user_id]));
            foreach ($recipients as $recipient) {
                if ($recipient !== $request->user()->id) {
                    MemberInbox::send($recipient, $replyTo ? 'reply' : 'comment', $replyTo ? 'A new reply' : 'A new comment on your post',
                        $request->user()->username.($replyTo ? ' replied in a conversation you’re part of.' : ' commented on your post.'), 'comment', $comment->id);
                }
            }

            return $comment;
        }, 3);

        return $this->discussion($post, $comment)->with('success', 'Comment added.');
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
        return redirect(CommentLinks::url($post, $comment));
    }

    public function replies(Request $request, Comment $comment): View
    {
        abort_unless($comment->parent_id === null && $comment->post && $comment->post->visibleTo($request->user()), 404);
        $post = $comment->post;
        abort_unless($post->comments_enabled || ($request->user() && $post->canBeManagedBy($request->user())), 404);
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $comment->load('user')->loadCount('replies');
        $replies = $comment->replies()->with(['user', 'replyTo.user'])->oldest('id')->paginate(20);

        return view('posts.replies', compact('post', 'comment', 'replies'));
    }

    public function heart(Request $request, Comment $comment): RedirectResponse
    {
        $data = $request->validate(['hearted' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $comment, $data) {
            $post = Post::lockForUpdate()->findOrFail($comment->post_id);
            abort_unless($post->user_id === $request->user()->id, 403);
            $comment = Comment::findOrFail($comment->id);
            $changed = $comment->hearted_by_owner !== (bool) $data['hearted'];
            $comment->update(['hearted_by_owner' => $data['hearted']]);
            if ($changed && $data['hearted'] && $comment->user_id !== $request->user()->id) {
                MemberInbox::send($comment->user_id, 'comment_heart', 'The post owner loved your comment', $request->user()->username.' gave your comment a heart.', 'comment', $comment->id, 'heart:'.$comment->id);
            }
        }, 3);

        return back()->with('success', $data['hearted'] ? 'Comment hearted.' : 'Heart removed.');
    }

    public function pin(Request $request, Comment $comment): RedirectResponse
    {
        $data = $request->validate(['pinned' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $comment, $data) {
            $post = Post::lockForUpdate()->findOrFail($comment->post_id);
            abort_unless($post->user_id === $request->user()->id, 403);
            $comment = $post->comments()->findOrFail($comment->id);
            if ($data['pinned']) {
                $post->forceFill(['pinned_comment_id' => $comment->id])->save();
                if ($comment->user_id !== $request->user()->id) {
                    MemberInbox::send($comment->user_id, 'comment_pin', 'Your comment was pinned', $request->user()->username.' pinned your comment.', 'comment', $comment->id, 'pin:'.$comment->id);
                }
            } elseif ($post->pinned_comment_id === $comment->id) {
                $post->forceFill(['pinned_comment_id' => null])->save();
            }
        }, 3);

        return back()->with('success', $data['pinned'] ? 'Comment pinned above the discussion.' : 'Comment unpinned.');
    }
}
