<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReactionController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'emoji' => ['required', 'string', Rule::in([...Reaction::DEFAULT_EMOJIS, ...Reaction::PREMIUM_EMOJIS])],
        ]);

        DB::transaction(function () use ($request, $post, $data) {
            // Lock the account even when no reaction exists yet; the unique index
            // and this lock prevent concurrent requests creating duplicate votes.
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $reaction = Reaction::where('post_id', $post->id)->where('user_id', $user->id)->first();

            if ($reaction?->emoji === $data['emoji']) {
                $reaction->delete();

                return;
            }

            if (in_array($data['emoji'], Reaction::PREMIUM_EMOJIS, true) && ! $user->hasPremium()) {
                throw ValidationException::withMessages(['emoji' => 'More reactions are available with an active PreySON Premium subscription.']);
            }

            Reaction::updateOrCreate(['post_id' => $post->id, 'user_id' => $user->id], ['emoji' => $data['emoji']]);
        });

        if ($request->expectsJson()) {
            $counts = Reaction::where('post_id', $post->id)->select('emoji')
                ->selectRaw('COUNT(*) AS total')->groupBy('emoji')->pluck('total', 'emoji')
                ->map(fn ($count) => (int) $count);

            return response()->json([
                'counts' => $counts,
                'selected' => Reaction::where('post_id', $post->id)->where('user_id', $request->user()->id)->value('emoji'),
                'total' => $counts->sum(),
            ]);
        }

        return back()->with('success', 'Your reaction was updated.');
    }
}
