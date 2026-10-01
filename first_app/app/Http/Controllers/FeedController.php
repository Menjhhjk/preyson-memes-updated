<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Reaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(['all', 'image', 'gif', 'video'])],
            'sort' => ['nullable', Rule::in(['random', 'newest', 'oldest', 'reactions'])],
            'seed' => ['nullable', 'integer', 'min:1', 'max:2147483646'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $search = trim($data['q'] ?? '');
        $category = $data['category'] ?? 'all';
        $sort = $data['sort'] ?? 'random';
        $seed = (int) ($data['seed'] ?? random_int(1, 2147483646));

        $query = Post::query()->with('user')->withCount('reactions')
            ->with(['reactions' => fn ($query) => $query->where('user_id', $request->user()->id ?? 0)])
            ->when($category !== 'all', fn ($query) => $query->where('media_type', $category));

        if ($search !== '') {
            $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $query->whereRaw("title LIKE ? ESCAPE '!'", ['%'.$literal.'%']);
        }

        match ($sort) {
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'reactions' => $query->orderByDesc('reactions_count')->orderByDesc('id'),
            // A seeded integer permutation works identically on MariaDB and SQLite.
            // Keeping the seed in pagination avoids duplicates when loading page two.
            default => $query->orderByRaw('(((posts.id % 2147483647) * ?) + ?) % 2147483647', [
                100000 + (crc32('feed:'.$seed) % 1000000000), $seed,
            ])->orderBy('id'),
        };

        $posts = $query->paginate(12)->appends([
            'q' => $search, 'category' => $category, 'sort' => $sort, 'seed' => $seed,
        ]);
        $reactionCounts = [];
        foreach (DB::table('reactions')->select('post_id', 'emoji')
            ->selectRaw('COUNT(*) AS total')->whereIn('post_id', $posts->getCollection()->modelKeys())
            ->groupBy('post_id', 'emoji')->get() as $count) {
            $reactionCounts[$count->post_id][$count->emoji] = (int) $count->total;
        }

        return view('blog', [
            'posts' => $posts, 'reactionCounts' => $reactionCounts,
            'defaultReactions' => Reaction::DEFAULT_EMOJIS,
            'premiumReactions' => Reaction::PREMIUM_EMOJIS,
            'search' => $search, 'category' => $category, 'sort' => $sort, 'seed' => $seed,
        ]);
    }
}
