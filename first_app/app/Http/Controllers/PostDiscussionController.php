<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Reaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PostDiscussionController extends Controller
{
    public function show(Request $request, Post $post): View
    {
        abort_unless($post->visibleTo($request->user()), 404);
        $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:1000000']]);
        $post->loadCount(['comments', 'reactions'])->load(['reactions' => fn ($query) => $query->where('user_id', $request->user()->id ?? 0)]);
        $canReadComments = $post->comments_enabled || ($request->user() && $post->canBeManagedBy($request->user()));
        $comments = $canReadComments ? $post->comments()->with('user')->oldest()->orderBy('id')->paginate(20)->fragment('comments') : null;
        $counts = $post->reactions()->select('emoji')->selectRaw('COUNT(*) AS total')->groupBy('emoji')->pluck('total', 'emoji')->all();

        return view('posts.show', [
            'post' => $post, 'comments' => $comments, 'reactionCounts' => [$post->id => $counts],
            'defaultReactions' => Reaction::DEFAULT_EMOJIS, 'premiumReactions' => Reaction::PREMIUM_EMOJIS,
        ]);
    }

    public function media(Request $request, Post $post): BinaryFileResponse
    {
        abort_unless($post->visibleTo($request->user()), 404);
        $disk = Storage::disk('local');
        if (! $disk->exists($post->media_path)) {
            // Compatibility while an installation's legacy public files are migrated.
            $disk = Storage::disk('public');
        }
        abort_unless($disk->exists($post->media_path), 404);
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        $response = $request->boolean('download')
            ? response()->download($disk->path($post->media_path), basename($post->media_path), $headers)
            : response()->file($disk->path($post->media_path), $headers);

        // BinaryFileResponse defaults to public even when custom headers are supplied.
        $response->setPrivate();

        return $response;
    }
}
