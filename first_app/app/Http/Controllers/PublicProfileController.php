<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use App\Support\PostListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicProfileController extends Controller
{
    public function show(Request $request, User $member, PostListing $listing): View
    {
        $visible = $member->profileVisibleTo($request->user());
        $data = $visible ? $listing->data($request, $member) : [];
        $stats = $visible ? [
            'posts' => $member->posts()->count(),
            'reactions' => Reaction::whereIn('post_id', $member->posts()->select('id'))->count(),
            'comments' => Comment::whereIn('post_id', $member->posts()->where('comments_enabled', true)->select('id'))->count(),
        ] : [];

        return view('profiles.show', [...$data, 'member' => $member, 'visible' => $visible, 'stats' => $stats,
            'profileBackground' => $visible ? $member->profileBackground() : null]);
    }

    public function pin(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);
        $request->validate(['pinned' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $post) {
            $member = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            // Only this post's unpin button may clear its pin.
            if ($request->boolean('pinned')) {
                Post::query()->where('user_id', $member->id)->findOrFail($post->id);
                $member->forceFill(['pinned_post_id' => $post->id])->save();
            } elseif ($member->pinned_post_id === $post->id) {
                $member->forceFill(['pinned_post_id' => null])->save();
            }
        });

        return to_route('profiles.show', $request->user()->refresh())->with('success', $request->boolean('pinned') ? 'Post pinned. It appears first in your default profile order.' : 'Post unpinned.');
    }
}
