@extends('layouts.site')
@section('title', $post->title)
@section('content')
<div class="page-heading"><div><span class="eyebrow">THE CONVERSATION</span><h1>Good memes. Good company.</h1></div><a class="button secondary" href="{{ route('home') }}">← Back to posts</a></div>
<div class="discussion-layout">
<div>@include('partials.post-card')</div>
<section class="panel discussion-panel" id="comments" aria-labelledby="comments-heading">
    <div class="section-heading"><h2 id="comments-heading">Comments</h2>@if($comments)<span class="badge">{{ $post->comments_count }}</span>@endif</div>
    @if($comments && $post->pinnedComment)@include('partials.pinned-comment')@endif
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
        @include('partials.comment')
        @if($comment->replies_count > 0)
        <div class="comment-replies" aria-label="Replies to {{ $comment->user->username }}">
            @foreach($comment->replies as $reply)@include('partials.comment', ['comment' => $reply])@endforeach
            <a class="thread-link" href="{{ route('comments.replies', $comment) }}">View all {{ $comment->replies_count }} {{ Str::plural('reply', $comment->replies_count) }} &rarr;</a>
        </div>
        @endif
        @empty
        <div class="empty-state compact-empty"><p>No comments yet. A good conversation starts somewhere.</p></div>
        @endforelse
    </div>
    @include('partials.pagination', ['items' => $comments])
    @endif
</section>
</div>
@endsection
