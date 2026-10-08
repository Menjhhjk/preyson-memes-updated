@extends('layouts.site')
@section('title', $member->username.'’s profile')
@section('content')
<div class="profile-hero panel">
    <img class="avatar public-profile-avatar" src="{{ $member->avatarUrl() }}" alt="{{ $member->username }}’s profile picture" width="112" height="112">
    <div class="profile-intro"><span class="eyebrow">A CORNER OF PREYSON</span><h1 @class(['premium-owner' => $member->hasPremium()])>{{ $member->username }}</h1><div class="profile-badges"><span class="badge">{{ $member->isAdmin() ? 'Administrator' : ucfirst($member->role) }}</span>@if($member->hasPremium())<span class="badge premium">✦ Premium</span>@endif @if($member->profile_visibility === 'private')<span class="badge">Private profile</span>@endif</div>
    @if($visible)
        <p class="plain-text profile-description">{{ $member->description ?: 'No bio yet. Letting the memes do the talking.' }}</p>
        <p class="small muted">Joined {{ $member->created_at->format('F Y') }}@if($member->email_visible)<span class="profile-email"> · {{ $member->email }}</span>@endif</p>
    @endif
    </div>
    <div class="profile-actions">@if(auth()->check() && !auth()->user()->is($member) && (auth()->user()->isAdmin() || (auth()->user()->canModerate() && !$member->canModerate())))<a class="button secondary small" href="{{ route('warnings.create', $member) }}">Moderation warnings</a>@endif
@if(auth()->id() === $member->id)<a class="button secondary" href="{{ route('profile.edit') }}">Edit profile</a>@endif @auth<a class="report-link" href="{{ route('reports.create', ['type' => 'account', 'id' => $member->id]) }}">⚑ Report account</a>@endauth</div>
</div>
@if($visible)
    @if($member->profile_visibility === 'private')<div class="notice">This profile and its posts are private. Only the owner and moderation team can view them.</div>@endif
    <div class="profile-stats"><div class="panel"><strong>{{ number_format($stats['posts']) }}</strong><span>Posts</span></div><div class="panel"><strong>{{ number_format($stats['reactions']) }}</strong><span>Reactions received</span></div><div class="panel"><strong>{{ number_format($stats['comments']) }}</strong><span>Comments on open posts</span></div></div>
    <div class="page-heading"><div><h2>{{ auth()->id() === $member->id ? 'Your posts' : $member->username.'’s posts' }}</h2><p class="muted">{{ $sort === 'default' ? 'Pinned first, then the latest laughs.' : 'Sorted your way. Choose Default to bring the pinned post back to the top.' }}</p></div>@if(auth()->id() === $member->id)<a class="button" href="{{ route('dashboard') }}">＋ Create post</a>@endif</div>
    <form class="panel profile-post-filters" method="GET" action="{{ route('profiles.show', $member) }}" role="search">
        <label class="field">Search posts<input name="q" type="search" maxlength="100" value="{{ $search }}" placeholder="Find a meme…"></label>
        <label class="field">Media<select name="category">@foreach(['all' => 'All media', 'image' => 'Images', 'gif' => 'GIFs', 'video' => 'Videos'] as $value => $label)<option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="field">Sort<select name="sort" data-auto-submit>@foreach(['default' => 'Default · pinned first', 'newest' => 'Newest first', 'oldest' => 'Oldest first', 'reactions' => 'Most reactions', 'random' => 'Shuffle'] as $value => $label)<option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>@endforeach</select></label>
        <button class="button secondary" type="submit">Apply</button><a class="small" href="{{ route('profiles.show', $member) }}">Reset</a>
    </form>
    <section class="profile-posts" aria-label="Profile posts">
        @forelse($posts as $post)@include('partials.post-card', ['profileMember' => $member])@empty<div class="panel empty-state"><h2>No posts here yet.</h2><p>{{ $search !== '' || $category !== 'all' ? 'Try changing your search or media filter.' : 'The next good laugh could land here any time.' }}</p></div>@endforelse
    </section>
    @include('partials.pagination', ['items' => $posts])
@else
    <section class="panel empty-state"><div class="empty-symbol" aria-hidden="true">◇</div><h2>A little privacy, please.</h2><p>This member’s profile, posts, and stats are private.</p><a class="button secondary" href="{{ route('home') }}">Back to the feed</a></section>
@endif
@endsection
