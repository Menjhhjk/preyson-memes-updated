        <article class="comment" id="comment-{{ $comment->id }}">
            <header class="comment-heading"><a href="{{ route('profiles.show', $comment->user) }}"><img class="avatar" src="{{ $comment->user->avatarUrl() }}" alt="{{ $comment->user->username }}"></a><div><div class="comment-author"><a href="{{ route('profiles.show', $comment->user) }}" @class(['premium-owner' => $comment->user->hasPremium()])>{{ $comment->user->username }}</a>@if($comment->user->hasPremium())<span class="badge premium comment-premium" role="img" aria-label="Premium member" title="Premium member">✦</span>@endif @if($comment->user_id === $post->user_id)<span class="badge author-badge">Post owner</span>@endif</div><time class="small muted" datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->format('M j, Y · g:i a') }} UTC</time>@if($comment->edited_at)<small class="muted"> · edited</small>@endif</div></header>
            @if($comment->reply_to_id && $comment->replyTo)<p class="small muted">Replying to {{ $comment->replyTo->user?->username ?? 'a former member' }}</p>@endif
            <p class="plain-text comment-body">{{ $comment->body }}</p>
            @if($comment->hearted_by_owner)<p class="owner-heart">&#9829; Loved by the post owner</p>@endif
            @auth
            <div class="comment-actions">
                @if($post->user_id === auth()->id())
                <form method="POST" action="{{ route('comments.heart', $comment) }}">@csrf @method('PUT')<input type="hidden" name="hearted" value="{{ $comment->hearted_by_owner ? 0 : 1 }}"><button class="text-button" type="submit" aria-pressed="{{ $comment->hearted_by_owner ? 'true' : 'false' }}">{{ $comment->hearted_by_owner ? 'Remove heart' : 'Heart comment' }}</button></form>
                <form method="POST" action="{{ route('comments.pin', $comment) }}">@csrf @method('PUT')<input type="hidden" name="pinned" value="{{ $post->pinned_comment_id === $comment->id ? 0 : 1 }}"><button class="text-button" type="submit">{{ $post->pinned_comment_id === $comment->id ? 'Unpin comment' : 'Pin comment' }}</button></form>
                @endif
                <a class="report-link" href="{{ route('reports.create', ['type' => 'comment', 'id' => $comment->id]) }}">⚑ Report</a>
                @if($comment->canBeDeletedBy(auth()->user()))<form method="POST" action="{{ route('comments.destroy', $comment) }}" data-confirm="{{ $comment->parent_id ? 'Delete this reply?' : 'Delete this comment and all its replies?' }} This cannot be undone.">@csrf @method('DELETE')<button type="submit" class="text-button danger-text">Delete</button></form>@endif
            </div>
            @if($post->comments_enabled && $comment->user_id === auth()->id())
                <details class="comment-edit"><summary>Edit comment</summary><form method="POST" action="{{ route('comments.update', $comment) }}">@csrf @method('PATCH')<label class="field">Your comment<textarea name="body" rows="3" required maxlength="2000">{{ $comment->body }}</textarea></label><button class="button secondary small" type="submit">Save comment</button></form></details>
            @endif
            @if($post->comments_enabled)
            <details class="reply-composer"><summary>Reply</summary><form method="POST" action="{{ route('comments.store', $post) }}">@csrf<input type="hidden" name="reply_to_id" value="{{ $comment->id }}"><label class="field">Reply to {{ $comment->user->username }}<textarea name="body" rows="2" maxlength="2000" required></textarea></label><button class="button small" type="submit">Post reply</button></form></details>
            @endif
            @endauth
        </article>
