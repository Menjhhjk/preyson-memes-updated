<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\MemberNotification;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['filter' => ['nullable', Rule::in(['all', 'unread'])], 'page' => ['nullable', 'integer', 'min:1']]);
        $filter = $data['filter'] ?? 'all';
        $notifications = MemberNotification::where('user_id', $request->user()->id)
            ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))->latest('id')->paginate(20)->withQueryString();

        return view('activity.notifications', compact('notifications', 'filter'));
    }

    public function count(Request $request): JsonResponse
    {
        return response()->json(['unread' => MemberNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count()])->header('Cache-Control', 'no-store');
    }

    public function readAll(Request $request): RedirectResponse
    {
        MemberNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Notifications marked as read.');
    }

    public function open(Request $request, MemberNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }
        if ($notification->target_type === 'post') {
            $post = Post::find($notification->target_id);
            if ($post?->visibleTo($request->user())) {
                return to_route('posts.show', $post);
            }
        } elseif ($notification->target_type === 'comment') {
            $comment = Comment::with('post.user')->find($notification->target_id);
            if ($comment?->post?->visibleTo($request->user()) && ($comment->post->comments_enabled || $comment->post->canBeManagedBy($request->user()))) {
                return to_route('comments.show', $comment);
            }
        } elseif (in_array($notification->target_type, ['rewards', 'corner'], true)) {
            return to_route('rewards.index');
        } elseif ($notification->target_type === 'warning') {
            return to_route('warnings.mine');
        } elseif ($notification->target_type === 'corner_admin' && $request->user()->isAdmin()) {
            return to_route('corners.review');
        }

        return to_route('notifications.index')->with('status', 'Notification read. The linked content is no longer available to you.');
    }
}
