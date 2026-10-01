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
@include('partials.post-card')
@empty
<div class="panel empty-state"><div class="empty-symbol" aria-hidden="true">☺</div><h2>{{ $search !== '' || $category !== 'all' ? 'No memes in this corner yet.' : 'Fresh start. First laugh is yours.' }}</h2><p>{{ $search !== '' || $category !== 'all' ? 'Try another search or browse all posts for a little inspiration.' : 'The feed is ready for its first meme. Share an image, GIF, or video and get things rolling.' }}</p><a class="button" href="{{ $search !== '' || $category !== 'all' ? route('home') : (auth()->check() ? route('dashboard') : route('login')) }}">{{ $search !== '' || $category !== 'all' ? 'Show all posts' : '＋ Share the first meme' }}</a></div>
@endforelse
@include('partials.pagination', ['items' => $posts])
</section>
<aside class="feed-aside" aria-label="Community information"><div class="panel premium-card"><div class="premium-star" aria-hidden="true">✦</div><span class="eyebrow">A LITTLE EXTRA</span><h2>Go full PreySON.</h2><p>Serious perks.<br>Entirely unserious billing.</p><ul><li>Room for 30 posts</li><li>More ways to react</li><li>A name that shines</li></ul><a class="button full-width" href="{{ route('premium.index') }}">Meet Premium ↗</a><p class="small" style="text-align:center;margin:12px 0 0">30 days. Zero real payments.</p></div><div class="panel"><h3>A good place to laugh.</h3><div class="community-rule"><span>♡</span>Be kind to the humans behind the memes.</div><div class="community-rule"><span>↗</span>Share things you have permission to post.</div><div class="community-rule"><span>☺</span>Keep personal information to yourself.</div></div><p class="small muted">Images · GIFs · Videos<br>One community. Many questionable jokes.</p></aside>
</div>
@endsection