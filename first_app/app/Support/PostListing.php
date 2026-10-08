<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PostListing
{
    /** @return array<string, mixed> */
    public function data(Request $request, ?User $profile = null): array
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(['all', 'image', 'gif', 'video'])],
            'sort' => ['nullable', Rule::in(['default', 'random', 'newest', 'oldest', 'reactions'])],
            'seed' => ['nullable', 'integer', 'min:1', 'max:2147483646'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'as_of' => ['nullable', 'integer', 'min:1', 'max:'.now()->timestamp],
        ]);
        $search = trim($data['q'] ?? '');
        $category = $data['category'] ?? 'all';
        $sort = $data['sort'] ?? ($profile ? 'default' : 'random');
        $seed = (int) ($data['seed'] ?? random_int(1, 2147483646));
        $asOf = (int) ($data['as_of'] ?? now()->timestamp);
        if (! $profile && $sort === 'default') {
            $sort = 'random';
        }

        $query = Post::query()->visibleTo($request->user())->when($profile, fn ($query) => $query->where('user_id', $profile->id))
            ->with('user')->withCount(['reactions', 'comments'])
            ->with(['reactions' => fn ($query) => $query->where('user_id', $request->user()->id ?? 0)])
            ->when($category !== 'all', fn ($query) => $query->where('media_type', $category));

        if (! $profile && $sort === 'random') {
            $entries = DB::table('posts')->selectRaw('id AS feed_post_id, 0 AS occurrence')
                ->unionAll(DB::table('post_power_uses')->selectRaw('post_id AS feed_post_id, id AS occurrence')
                    ->where('kind', 'boost')->whereNotNull('post_id')->where('expires_at', '>', now())
                    ->where('created_at', '<=', date('Y-m-d H:i:s', $asOf)));
            $query->joinSub($entries, 'feed_entries', 'feed_entries.feed_post_id', '=', 'posts.id')
                ->addSelect('posts.*', 'feed_entries.occurrence as feed_occurrence');
        }

        if ($search !== '') {
            $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $query->whereRaw("title LIKE ? ESCAPE '!'", ['%'.$literal.'%']);
        }

        if ($profile && $sort === 'default' && $profile->pinned_post_id) {
            $query->orderByRaw('CASE WHEN posts.id = ? THEN 0 ELSE 1 END', [$profile->pinned_post_id]);
        }

        match ($sort) {
            'default' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'reactions' => $query->orderByDesc('reactions_count')->orderByDesc('id'),
            // A seeded integer permutation works identically on MariaDB and SQLite.
            // Keeping the seed in pagination avoids duplicates when loading page two.
            default => $query->orderByRaw('(((posts.id % 2147483647) * ?) + ?'.(! $profile ? ' + ((feed_entries.occurrence % 2147483647) * 48271)' : '').') % 2147483647', [
                100000 + (crc32('feed:'.$seed) % 1000000000), $seed,
            ])->orderBy('id'),
        };

        $posts = $query->paginate(12)->appends([
            'q' => $search, 'category' => $category, 'sort' => $sort, 'seed' => $seed, 'as_of' => $asOf,
        ]);
        $reactionCounts = [];
        foreach (DB::table('reactions')->select('post_id', 'emoji')
            ->selectRaw('COUNT(*) AS total')->whereIn('post_id', $posts->getCollection()->modelKeys())
            ->groupBy('post_id', 'emoji')->get() as $count) {
            $reactionCounts[$count->post_id][$count->emoji] = (int) $count->total;
        }

        return [
            'posts' => $posts, 'reactionCounts' => $reactionCounts,
            'defaultReactions' => Reaction::DEFAULT_EMOJIS,
            'premiumReactions' => Reaction::PREMIUM_EMOJIS,
            'search' => $search, 'category' => $category, 'sort' => $sort, 'seed' => $seed,
        ];
    }
}
