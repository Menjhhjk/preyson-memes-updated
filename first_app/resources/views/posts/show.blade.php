@extends('layouts.site')
@section('title', $post->title)
@section('content')
<div class="page-heading"><div><span class="eyebrow">THE CONVERSATION</span><h1>Good memes. Good company.</h1></div><a class="button secondary" href="{{ route('home') }}">← Back to posts</a></div>
<div class="discussion-layout">
<div>@include('partials.post-card')</div>
<section class="panel discussion-panel" id="comments" aria-labelledby="comments-heading">
    <div class="section-heading"><h2 id="comments-heading">Comments</h2>@if($comments)<span class="badge">{{ $comments->total() }}</span>@endif</div>
    @if(!$post->comments_enabled)
        <div class="notice">The owner has turned comments off.@if($comments) <span class="small">As the owner or a moderator, you can still review and remove existing comments below.</span>@endif</div>
    @else
        @auth
        <form class="comment-composer" method="POST" action="{{ route('comments.store', $post) }}">@csrf
            <label class="field" for="new-comment">Add to the conversation<textarea id="new-comment" name="body" rows="3" maxlength="2000" required placeholder="Keep it kind. Keep it human." data-character-count="comment-count">{{ old('body') }}</textarea></label>
            <div class="form-actions"><small class="muted" id="comment-count" aria-live="polite">0 / 2,000</small><button class="button small" type="submit">Post comment</button></div>
        </form>
        @else
        <div class="notice"><a href="{{ route('login') }}">Sign in</a> to join the conversation.</div>
        @endauth
    @endif
    @if($comments)
    <div class="comment-list">
        @forelse($comments as $comment)
        <article class="comment" id="comment-{{ $comment->id }}">
            <header class="comment-heading"><a href="{{ route('profiles.show', $comment->user) }}"><img class="avatar" src="{{ $comment->user->avatarUrl() }}" alt="{{ $comment->user->username }}"></a><div><div class="comment-author"><a href="{{ route('profiles.show', $comment->user) }}" @class(['premium-owner' => $comment->user->hasPremium()])>{{ $comment->user->username }}</a>@if($comment->user->hasPremium())<span class="badge premium comment-premium" role="img" aria-label="Premium member" title="Premium member">✦</span>@endif @if($comment->user_id === $post->user_id)<span class="badge author-badge">Post owner</span>@endif</div><time class="small muted" datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->format('M j, Y · g:i a') }} UTC</time>@if($comment->edited_at)<small class="muted"> · edited</small>@endif</div></header>
            <p class="plain-text comment-body">{{ $comment->body }}</p>
            @auth
            <div class="comment-actions">
                <a class="report-link" href="{{ route('reports.create', ['type' => 'comment', 'id' => $comment->id]) }}">⚑ Report</a>
                @if($comment->canBeDeletedBy(auth()->user()))<form method="POST" action="{{ route('comments.destroy', $comment) }}" data-confirm="Delete this comment? This cannot be undone.">@csrf @method('DELETE')<button type="submit" class="text-button danger-text">Delete</button></form>@endif
            </div>
            @if($post->comments_enabled && $comment->user_id === auth()->id())
                <details class="comment-edit"><summary>Edit comment</summary><form method="POST" action="{{ route('comments.update', $comment) }}">@csrf @method('PATCH')<label class="field">Your comment<textarea name="body" rows="3" required maxlength="2000">{{ $comment->body }}</textarea></label><button class="button secondary small" type="submit">Save comment</button></form></details>
            @endif
            @endauth
        </article>
        @empty
        <div class="empty-state compact-empty"><p>No comments yet. A good conversation starts somewhere.</p></div>
        @endforelse
    </div>
    @include('partials.pagination', ['items' => $comments])
    @endif
</section>
</div>
@endsection
