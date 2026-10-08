<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostPowerUse;
use App\Models\SuperReactionType;
use App\Models\User;
use App\Support\MemberInbox;
use App\Support\PowerCharges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostPowerController extends Controller
{
    public function boost(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        return $this->usePower($request, $post, 'boost');
    }

    public function superReact(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        return $this->usePower($request, $post, 'super');
    }

    private function usePower(Request $request, Post $post, string $kind): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['request_key' => ['required', 'uuid'], 'confirmed' => ['accepted'],
            'super_reaction_type_id' => [$kind === 'super' ? 'required' : 'nullable', 'integer']]);
        $use = DB::transaction(function () use ($request, $post, $kind, $data) {
            $member = User::lockForUpdate()->findOrFail($request->user()->id);
            $post = Post::lockForUpdate()->findOrFail($post->id);
            abort_unless($post->visibleTo($member), 404);
            $existing = PostPowerUse::where('user_id', $member->id)->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->kind !== $kind || $existing->post_id !== $post->id
                    || ($kind === 'super' && $existing->super_reaction_type_id !== (int) $data['super_reaction_type_id'])) {
                    throw ValidationException::withMessages(['request_key' => 'This action key was already used. Refresh the page before trying again.']);
                }

                return $existing;
            }
            $type = $kind === 'super' ? SuperReactionType::where('is_active', true)->where('id', $data['super_reaction_type_id'])->first() : null;
            if ($kind === 'super' && ! $type) {
                throw ValidationException::withMessages(['super_reaction_type_id' => 'This Super-reaction is no longer available. Please choose another.']);
            }
            $source = PowerCharges::spend($member, $kind);
            $use = PostPowerUse::create(['user_id' => $member->id, 'post_id' => $post->id, 'kind' => $kind,
                'super_reaction_type_id' => $type?->id, 'source' => $source, 'request_key' => $data['request_key'],
                'effect' => $type?->effect() ?? ['name' => 'Boost'], 'expires_at' => $kind === 'boost' ? now()->addHours(24) : null]);
            if ($post->user_id !== $member->id) {
                MemberInbox::send($post->user_id, $kind, $kind === 'boost' ? 'Your post got a boost!' : 'A Super-reaction for your post!',
                    $member->username.($kind === 'boost' ? ' boosted your post for 24 hours.' : ' sent '.$type->name.' to your post.'), 'post', $post->id);
            }

            return $use;
        }, 3);
        $balance = PowerCharges::balance($request->user()->refresh(), $kind);
        $payload = ['kind' => $kind, 'effect' => $use->effect, 'balance' => $balance, 'expires_at' => $use->expires_at?->toIso8601String()];
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return back()->with('power_effect', $payload)->with('success', $kind === 'boost' ? 'You boosted a post! It gets one extra feed appearance for 24 hours.' : 'Your Super-reaction was sent!');
    }
}
