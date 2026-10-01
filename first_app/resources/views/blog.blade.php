@extends('layouts.site')
@section('title', 'The meme feed')
@section('content')
<div class="page-heading"><div><span class="eyebrow">GOOD SCROLLS AHEAD</span><h1>Your daily dose of nonsense.</h1><p>A fresh mix of memes, questionable humor, and very good GIFs.</p></div><a class="button" href="{{ auth()->check() ? route('dashboard') : route('login') }}">＋ Create post</a></div>
<div class="feed-layout">
<section class="feed-column" aria-label="Posts">
<form class="panel filter-panel" method="GET" action="{{ route('home') }}" role="search">
<div class="search-row"><label class="sr-only" for="feed-search">Search post titles</label><input class="control" id="feed-search" name="q" type="search" value="{{ $search }}" placeholder="Find your next laugh…" maxlength="100"><button class="button secondary" type="submit">Search</button></div>
<div class="filter-row"><div class="filter-tabs" aria-label="Media category">
@foreach(['all' => 'All posts', 'image' => 'Images', 'gif' => 'GIFs', 'video' => 'Videos'] as $key => $label)
<a href="{{ route('home', ['q' => $search, 'category' => $key, 'sort' => $sort]) }}" @class(['filter-tab', 'active' => $category === $key]) @if($category === $key) aria-current="true" @endif>{{ $label }}</a>
@endforeach
</div><label class="sort-label">Sort by <select name="sort" data-auto-submit><option value="random" @selected($sort === 'random')>Surprise me</option><option value="newest" @selected($sort === 'newest')>Newest first</option><option value="oldest" @selected($sort === 'oldest')>Oldest first</option><option value="reactions" @selected($sort === 'reactions')>Most reactions</option></select></label></div>
<input type="hidden" name="category" value="{{ $category }}">
</form>
<div class="feed-meta"><span>{{ number_format($posts->total()) }} {{ $posts->total() === 1 ? 'post' : 'posts' }} · {{ $sort === 'random' ? 'A little shuffle, a lot of fun' : 'Your feed, your order' }}</span><a href="{{ route('home', ['category' => $category, 'q' => $search]) }}">↻ Shuffle the feed</a></div>
@forelse($posts as $post)
@php
$selectedReaction = $post->reactions->first()?->emoji;
$counts = $reactionCounts[$post->id] ?? [];
$reactionNames = ['👍' => 'Like', '❤️' => 'Love', '😂' => 'Haha', '😮' => 'Wow', '😢' => 'Sad', '😡' => 'Angry', '🔥' => 'Fire', '🎉' => 'Celebrate', '🤯' => 'Mind blown', '👏' => 'Applause', '💀' => 'Dead funny', '🥰' => 'Adore'];
@endphp
<article class="post-card" id="post-{{ $post->id }}">
<header class="post-header"><div class="post-heading"><h2>{{ $post->title }}</h2><span class="badge type">{{ strtoupper($post->media_type) }}</span></div><div class="post-owner"><img class="avatar" src="{{ $post->user?->avatarUrl() ?? asset('avatar-default.svg') }}" alt=""><div class="owner-details"><strong @class(['premium-owner' => $post->user?->hasPremium()])>{{ $post->user?->username ?? 'Former member' }}</strong>@if($post->user?->hasPremium())<span class="badge premium">✦ Premium</span>@endif<span aria-hidden="true">·</span><time datetime="{{ $post->created_at->toIso8601String() }}" title="{{ $post->created_at->format('F j, Y g:i A').' UTC' }}">{{ $post->created_at->format('M j, Y') }}</time></div>@if(auth()->check() && $post->canBeManagedBy(auth()->user()))<a class="post-actions" href="{{ route('posts.edit', ['post' => $post, 'return_to' => 'feed']) }}">Edit ↗</a>@endif</div></header>
<div class="post-media">@if($post->media_type === 'video')<video controls playsinline preload="metadata" aria-label="{{ $post->title }}"><source src="{{ asset('storage/'.$post->media_path) }}">Your browser cannot play this video.</video>@else<img src="{{ asset('storage/'.$post->media_path) }}" alt="{{ $post->title }}" loading="lazy" decoding="async">@endif</div>
<footer class="post-footer">
<div class="reaction-bar" aria-label="React to this post">
@foreach($defaultReactions as $emoji)
@auth
<form class="reaction-form" method="POST" action="{{ route('posts.react', $post) }}">@csrf<input type="hidden" name="emoji" value="{{ $emoji }}"><button class="reaction-button" data-emoji="{{ $emoji }}" type="submit" aria-label="{{ $reactionNames[$emoji] }}" title="{{ $reactionNames[$emoji] }}" aria-pressed="{{ $selectedReaction === $emoji ? 'true' : 'false' }}"><span aria-hidden="true">{{ $emoji }}</span><span class="reaction-count">{{ $counts[$emoji] ?? '' }}</span></button></form>
@else
<a class="reaction-button" href="{{ route('login') }}" aria-label="Sign in to react: {{ $reactionNames[$emoji] }}"><span aria-hidden="true">{{ $emoji }}</span><span class="reaction-count">{{ $counts[$emoji] ?? '' }}</span></a>
@endauth
@endforeach
@if(auth()->check() && auth()->user()->hasPremium())
<details class="more-reactions"><summary>✦ More Reaction</summary><div class="reaction-picker">
@foreach($premiumReactions as $emoji)<form class="reaction-form" method="POST" action="{{ route('posts.react', $post) }}">@csrf<input type="hidden" name="emoji" value="{{ $emoji }}"><button class="reaction-button" data-emoji="{{ $emoji }}" type="submit" aria-label="{{ $reactionNames[$emoji] }}" title="{{ $reactionNames[$emoji] }}" aria-pressed="{{ $selectedReaction === $emoji ? 'true' : 'false' }}"><span aria-hidden="true">{{ $emoji }}</span><span class="reaction-count">{{ $counts[$emoji] ?? '' }}</span></button></form>@endforeach
</div></details>
@else<a class="small" href="{{ route('premium.index') }}" title="Unlock extra reactions with Premium">✦ More</a>@endif
</div>
<div class="reaction-summary"><span><span class="reaction-total">{{ $post->reactions_count }}</span> reactions <span class="extra-reaction-summary">@foreach($premiumReactions as $emoji)@if(($counts[$emoji] ?? 0) > 0)<span title="{{ $reactionNames[$emoji] }}">{{ $emoji }} {{ $counts[$emoji] }}</span> @endif @endforeach</span></span><span>One mood per meme.</span></div>
<div class="reaction-feedback small" role="status" aria-live="polite"></div>
@if(auth()->check() && $post->canBeManagedBy(auth()->user()))
<details class="inline-edit"><summary>Edit this post here</summary><form method="POST" action="{{ route('posts.update', $post) }}" enctype="multipart/form-data">@csrf @method('PUT')<input type="hidden" name="return_to" value="feed"><label class="field">Post title<input name="title" value="{{ $post->title }}" required maxlength="255"></label><label class="field">Replace media <span class="muted small">Optional · up to 100 MB</span><input type="file" name="media" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime"></label><button class="button small" type="submit">Save changes</button></form><form method="POST" action="{{ route('posts.destroy', $post) }}" data-confirm="Delete this post and its reactions? This cannot be undone." style="margin-top:12px">@csrf @method('DELETE')<input type="hidden" name="return_to" value="feed"><button class="button danger small" type="submit">Delete post</button></form></details>
@endif
</footer></article>
@empty
<div class="panel empty-state"><div class="empty-symbol" aria-hidden="true">☺</div><h2>{{ $search !== '' || $category !== 'all' ? 'No memes in this corner yet.' : 'Fresh start. First laugh is yours.' }}</h2><p>{{ $search !== '' || $category !== 'all' ? 'Try another search or browse all posts for a little inspiration.' : 'The feed is ready for its first meme. Share an image, GIF, or video and get things rolling.' }}</p><a class="button" href="{{ $search !== '' || $category !== 'all' ? route('home') : (auth()->check() ? route('dashboard') : route('login')) }}">{{ $search !== '' || $category !== 'all' ? 'Show all posts' : '＋ Share the first meme' }}</a></div>
@endforelse
@if($posts->hasPages())<nav class="pagination" aria-label="Post pages">@if($posts->onFirstPage())<span>← Previous</span>@else<a class="button secondary small" href="{{ $posts->previousPageUrl() }}">← Previous</a>@endif<span>Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>@if($posts->hasMorePages())<a class="button secondary small" href="{{ $posts->nextPageUrl() }}">Next →</a>@else<span>Next →</span>@endif</nav>@endif
</section>
<aside class="feed-aside" aria-label="Community information"><div class="panel premium-card"><div class="premium-star" aria-hidden="true">✦</div><span class="eyebrow">A LITTLE EXTRA</span><h2>Go full PreySON.</h2><p>Serious perks.<br>Entirely unserious billing.</p><ul><li>Room for 30 posts</li><li>More ways to react</li><li>A name that shines</li></ul><a class="button full-width" href="{{ route('premium.index') }}">Meet Premium ↗</a><p class="small" style="text-align:center;margin:12px 0 0">30 days. Zero real payments.</p></div><div class="panel"><h3>A good place to laugh.</h3><div class="community-rule"><span>♡</span>Be kind to the humans behind the memes.</div><div class="community-rule"><span>↗</span>Share things you have permission to post.</div><div class="community-rule"><span>☺</span>Keep personal information to yourself.</div></div><p class="small muted">Images · GIFs · Videos<br>One community. Many questionable jokes.</p></aside>
</div>
@endsection