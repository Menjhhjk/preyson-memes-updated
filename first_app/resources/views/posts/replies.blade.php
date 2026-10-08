@extends('layouts.site')
@section('title', 'Replies')
@section('content')
<div class="page-heading"><div><span class="eyebrow">KEEP THE CONVERSATION GOING</span><h1>Replies</h1><p>{{ $post->title }}</p></div><a class="button secondary" href="{{ route('posts.show', $post) }}#comments">← All comments</a></div>
<section class="panel thread-panel">
    @unless($post->comments_enabled)<div class="notice">Comments are turned off. You can review this discussion as its owner or a moderator.</div>@endunless
    @include('partials.comment')
    <div class="comment-replies">
        @forelse($replies as $reply)@include('partials.comment', ['comment' => $reply])@empty<p class="muted">No replies yet.</p>@endforelse
    </div>
    @include('partials.pagination', ['items' => $replies])
</section>
@endsection
