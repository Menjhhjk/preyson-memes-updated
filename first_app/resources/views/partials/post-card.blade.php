@php
$selectedReaction = $post->reactions->first()?->emoji;
$counts = $reactionCounts[$post->id] ?? [];
$reactionNames = ['👍' => 'Like', '❤️' => 'Love', '😂' => 'Haha', '😮' => 'Wow', '😢' => 'Sad', '😡' => 'Angry', '🔥' => 'Fire', '🎉' => 'Celebrate', '🤯' => 'Mind blown', '👏' => 'Applause', '💀' => 'Dead funny', '🥰' => 'Adore'];
@endphp
<article class="post-card" data-post-id="{{ $post->id }}" id="post-{{ $post->id }}{{ ($post->feed_occurrence ?? 0) ? '-boost-'.$post->feed_occurrence : '' }}">
<header class="post-header"><div class="post-heading"><h2><a class="post-title-link" href="{{ route('posts.show', $post) }}">{{ $post->title }}</a></h2><span class="badge type">{{ strtoupper($post->media_type) }}</span></div><div class="post-owner"><img class="avatar" src="{{ $post->user?->avatarUrl() ?? asset('avatar-default.svg') }}" alt=""><div class="owner-details">@if($post->user)<a class="owner-profile-link" href="{{ route('profiles.show', $post->user) }}">@endif<strong @class(['premium-owner' => $post->user?->hasPremium()])>{{ $post->user?->username ?? 'Former member' }}</strong>@if($post->user)</a>@endif
@if($post->user?->hasPremium())<span class="badge premium">✦ Premium</span>@endif<span aria-hidden="true">·</span><time datetime="{{ $post->created_at->toIso8601String() }}" title="{{ $post->created_at->format('F j, Y g:i A').' UTC' }}">{{ $post->created_at->format('M j, Y') }}</time></div>@if(auth()->check() && $post->canBeManagedBy(auth()->user()))<a class="post-actions" href="{{ route('posts.edit', ['post' => $post, 'return_to' => 'feed']) }}">Edit ↗</a>@endif</div></header>
<div class="post-media">@if($post->media_type === 'video')@include('partials.video-playback')@else<img src="{{ route('posts.media', $post) }}" alt="{{ $post->title }}" loading="lazy" decoding="async">@endif</div>
<footer class="post-footer">
@if(($post->feed_occurrence ?? 0) > 0)<span class="badge boost-appearance">Boosted appearance &middot; extra time in the spotlight</span>@endif
@if(isset($profileMember) && $profileMember->pinned_post_id === $post->id)<span class="badge pinned-badge">&#8982; Pinned post</span>@endif
<div class="post-community-actions">
<a href="{{ route('posts.show', $post) }}#comments">{{ $post->comments_enabled ? ($post->comments_count.' '.Str::plural('comment', $post->comments_count)) : 'Comments off' }}</a>
@auth
<a class="report-link" href="{{ route('reports.create', ['type' => 'post', 'id' => $post->id]) }}">&#9873; Report post</a>
@if($post->user_id === auth()->id())
<form method="POST" action="{{ route('posts.pin', $post) }}">@csrf @method('PUT')<input type="hidden" name="pinned" value="{{ auth()->user()->pinned_post_id === $post->id ? '0' : '1' }}"><button class="text-button" type="submit">{{ auth()->user()->pinned_post_id === $post->id ? 'Unpin from profile' : 'Pin to profile' }}</button></form>
@endif
@endauth
</div>
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
@auth @include('partials.post-powers') @endauth
@if(auth()->check() && $post->canBeManagedBy(auth()->user()))
<details class="inline-edit"><summary>Edit this post here</summary><form method="POST" action="{{ route('posts.update', $post) }}" enctype="multipart/form-data" data-media-upload>@csrf @method('PUT')<input type="hidden" name="return_to" value="feed"><label class="field">Post title<input name="title" value="{{ $post->title }}" required maxlength="255"></label><label class="field">Replace media <span class="muted small">Optional · up to 100 MB</span>@include('partials.media-input', ['inputId' => 'feed-media-'.$post->id.'-'.($post->feed_occurrence ?? 0)])</label>@include('partials.comment-toggle', ['commentsEnabled' => $post->comments_enabled])<button class="button small" type="submit">Save changes</button></form><form method="POST" action="{{ route('posts.destroy', $post) }}" data-confirm="Delete this post, comments, and reactions? This cannot be undone." style="margin-top:12px">@csrf @method('DELETE')<input type="hidden" name="return_to" value="feed"><button class="button danger small" type="submit">Delete post</button></form></details>
@endif
</footer></article>
